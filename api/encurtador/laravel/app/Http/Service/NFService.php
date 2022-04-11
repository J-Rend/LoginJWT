<?php

namespace App\Http\Service;

use App\Http\Dao\ConfigDao;
use App\Http\Dao\ConfigNotasDao;
use App\Http\Dao\Movimentacao\MovConferenciaDao;
use App\Http\Dao\MovimentacaoDao;
use App\Http\Dao\MovimentacaoItemDao;
use App\Http\Dao\MovimentacaoRastreioDao;
use App\Http\Dao\NotaFiscalDao;
use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;
use NFePHP\NFe\Complements;
use NFePHP\DA\NFe\Danfe;
use NFePHP\DA\Legacy\FilesFolders;
use NFePHP\Common\Soap\SoapCurl;
use stdClass;

error_reporting(E_ALL);
ini_set('display_errors', 'On');

class NFService
{

	private $config;
	private $tools;

	public function __construct($config, $id_empresa)
	{

		$lista = \App\Http\Dao\ArquivoDao::getList("pfx_file", $id_empresa, " and p.arquivo like '%.pfx%'");

		$item_arquivo = $lista[0];

		$final_arquivo = \App\Http\Dao\ArquivoDao::getArquivoPhysical($item_arquivo->arquivo, $id_empresa, "pfx_file");

		$reg_config = \App\Http\Dao\ConfigNotasDao::getByEmpresa($id_empresa);

		$temp = file_get_contents($final_arquivo);

		$this->config = $config;

		$this->tools = new Tools(json_encode($config), Certificate::readPfx($temp, $reg_config->senha_pfx));
		$this->tools->model(55);
	}

	public function getLastNumero($config, $id_cliente)
	{

		$lastNumero = $config->ultimo_numero_nfe;

		$sequencial = \App\Http\Dao\ConfigDao::executeScalar("select max(sequencial) from nota_fiscal where id_cliente = " . $id_cliente . " and sequencial is not null ");

		if ($sequencial != "") {
			$seq = (int)$sequencial;
			$lastNumero = $seq;
		}

		//$lastNumero++;

		return $lastNumero;
	}

	public function isCompra($id, $tipo_movimentacao)
	{

		if ($tipo_movimentacao == "NOTAFISCAL") {

			$id_mov_pai = MovConferenciaDao::getIdPai($id, "NOTAFISCAL");
			$tipo_movimentacao = ConfigDao::executeScalar("select tipo as res from movimentacao where id = " . $id_mov_pai);
		}

		if ($tipo_movimentacao == "CO" ||  $tipo_movimentacao == "PC") {
			return true;
		}

		return false;
	}

	public static function IsMovCompra($id, $tipo_movimentacao)
	{

		if ($tipo_movimentacao == "NOTAFISCAL") {

			$id_mov_pai = MovConferenciaDao::getIdPai($id, "NOTAFISCAL");
			$tipo_movimentacao = ConfigDao::executeScalar("select tipo as res from movimentacao where id = " . $id_mov_pai);
		}

		if ($tipo_movimentacao == "CO" ||  $tipo_movimentacao == "PC") {
			return true;
		}

		return false;
	}

	public function limpaCNPJ($cnpj)
	{
		$cnpj = str_replace(".", "", $cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);

		return $cnpj;
	}

	public function gerarNFe($idVenda, &$problemas = array())
	{

		MovimentacaoItemDao::salvarTotal($idVenda);

		$venda = \App\Movimentacao::where('id', $idVenda)->first();

		$tipo_movimentacao = $venda->tipo;

		if ($tipo_movimentacao == "NOTAFISCAL") {

			$id_mov_pai = MovConferenciaDao::getIdPai($idVenda, "NOTAFISCAL");
			$tipo_pai = ConfigDao::executeScalar("select tipo as res from movimentacao where id = " . $id_mov_pai);

			$tipo_movimentacao = $tipo_pai;
		}

		$id_config = \App\Http\Dao\ConfigDao::executeScalar("select id as res from config_notas where id_empresa = " . $venda->id_cliente);

		if ($id_config == "") {
			$problemas[count($problemas)] = "Configure a nota fiscal no cadastro do emitente ";
			return false;
		}

		$reg_config = \App\Http\Dao\ConfigNotasDao::getByEmpresa($venda->id_cliente); // iniciando os dados do emitente NF
		$reg_nota_fiscal = \App\Http\Dao\NotaFiscalDao::getNotaFiscal($idVenda);

		$config = json_decode($reg_config->meta_dados);

		$objMovMeta = new stdClass();
		if (@$venda->meta_dados != "" && strpos(" " . @$venda->meta_dados, "}")) {
			$objMovMeta = json_decode($venda->meta_dados);
		}
		$objComp = MovimentacaoDao::getComp(@$venda->id);
		if (!is_null($objComp) && $objComp->meta_dados != "" && strpos(" " . @$objComp->meta_dados, "}")) {

			$objMovMeta = json_decode($objComp->meta_dados);
		}


		$reg_parametros =  $config;
		$tributacao = $config; // iniciando tributos

		$empresa = \App\Cliente::find($venda->id_cliente);

		$meta_dados_acao = self::geraColunaAcao($reg_config);
		if (@$meta_dados_acao->VENDA) {
			$reg_config = $meta_dados_acao->VENDA;
		}

		$id_destinatario = $venda->id_destinatario;
		$is_devolucao = false;
		$is_compra = false;
		$is_transporte = false;

		if (strpos(" " . $tipo_movimentacao, "DEVOLUCAO")) {
			$id_destinatario =  $venda->id_fornecedor;
			$is_devolucao = true;
			if (@$meta_dados_acao->DEVOLUCAO) {
				$reg_parametros =  $meta_dados_acao->DEVOLUCAO;
			}
		}

		if (strpos(" " . $tipo_movimentacao, "TRANSPORTE")) {
			$id_destinatario =  $venda->id_destinatario;
			$is_transporte = true;
			if (@$meta_dados_acao->TRANSPORTE) {
				$reg_parametros =  $meta_dados_acao->TRANSPORTE;
			}
		}

		$is_compra = $this->isCompra($idVenda, $tipo_movimentacao);

		if ($is_compra) {
			$id_destinatario =  $venda->id_fornecedor;
			if (@$meta_dados_acao->COMPRA) {
				$reg_parametros =  $meta_dados_acao->COMPRA;
			}
		}

		$destinatario = null;

		if ($id_destinatario != "") {
			$destinatario = \App\Cliente::find($id_destinatario);

			$reg_nota_fiscal->id_destinatario = $id_destinatario;
			$reg_nota_fiscal->save();
		} else {

			$problemas[count($problemas)] = "Não há destinatário para esta nota fiscal ";
		}


		$transportadora = null;

		if ($venda->id_transportadora != "") {
			$transportadora = \App\Cliente::find($venda->id_transportadora);
		}



		$stdMuni = new \stdClass();


		$nfe = new Make();
		$stdInNFe = new \stdClass();
		$stdInNFe->versao = '4.00';
		$stdInNFe->Id = null;
		$stdInNFe->pk_nItem = '';

		$infNFe = $nfe->taginfNFe($stdInNFe);

		$lastNumero = $reg_nota_fiscal->sequencial;

		if ($lastNumero == "") {
			$lastNumero = $this->getLastNumero($config, $venda->id_cliente); // $config->ultimo_numero_nfe;

		}

		$stdIde = new \stdClass();
		$stdIde->cUF = "";
		$codMun = "";

		if ($empresa->meta_dados !== null && $empresa->meta_dados != "") {
			$obj_meta = json_decode($empresa->meta_dados);
			//print_r( $obj_meta); die(" ");
			$stdIde->cUF = $obj_meta->uf_ibge;
			$stdIde->cMunFG = $obj_meta->cidade_igbe;
			$codMun = $obj_meta->cidade_igbe;
		}




		$stdIde->cNF = rand(11111, 99999);
		// $stdIde->natOp = $venda->natureza->natureza;
		$stdIde->natOp = 1; //$venda->natureza->natureza;

		if ($is_devolucao) {
			$stdIde->natOp = "DEVOLUCAO DE COMPRA PARA COMERCIALIZACAO";
		}
		if ($is_compra) {
			$stdIde->natOp = "COMPRAS PARA COMERCIALIZACAO (PRAZO)";
		}

		// $stdIde->indPag = 1; //NÃO EXISTE MAIS NA VERSÃO 4.00 // forma de pagamento

		$stdIde->mod = 55;
		$stdIde->serie = $config->numero_serie_nfe;
		$stdIde->nNF = (int)$lastNumero + 1;
		$stdIde->dhEmi = date("Y-m-d\TH:i:sP");
		$stdIde->dhSaiEnt = date("Y-m-d\TH:i:sP");
		$stdIde->tpNF = 1;

		$stdIde->idDest = 1;
		$cUfDestinario = ""; //$obj_meta->uf_ibge;
		$codMunDestinario = ""; //$obj_meta->uf_ibge;

		if ($destinatario->meta_dados !== null && $destinatario->meta_dados != "") {
			$obj_meta = json_decode($destinatario->meta_dados);
			$stdIde->idDest = $obj_meta->uf_ibge != $stdIde->cUF ? 2 : 1;
			$cUfDestinario = $obj_meta->uf_ibge;
			$codMunDestinario = $obj_meta->cidade_igbe;
		}



		$stdIde->tpImp = 1;
		$stdIde->tpEmis = 1;
		$stdIde->cDV = 0;
		$stdIde->tpAmb = $config->ambiente;
		$stdIde->finNFe = 1;
		$stdIde->indFinal = 1; //$venda->cliente->consumidor_final;
		$stdIde->indPres = 1;
		$stdIde->procEmi = '0';
		$stdIde->verProc = 'GONDOLA_S01';
		// $stdIde->dhCont = null;
		// $stdIde->xJust = null;

		if ($is_devolucao) {
			$stdIde->cDV = 3;
			$stdIde->finNFe = 4;
			$stdIde->indFinal = 0;
		}

		if ($is_compra) {
			$stdIde->cDV = 0;
			$stdIde->finNFe = 1;
			$stdIde->indFinal = 0;
			$stdIde->tpNF = 0;
		}

		if (@!is_null($reg_parametros)) {
			$stdIde->natOp = $reg_parametros->natOp;
			$stdIde->cDV = $reg_parametros->cDV;
			$stdIde->finNFe = $reg_parametros->finNFe;
			$stdIde->indFinal = $reg_parametros->indFinal;
			$stdIde->tpNF = $reg_parametros->tpNF;
		}


		//
		$tagide = $nfe->tagide($stdIde);

		$stdEmit = new \stdClass();
		$stdEmit->xNome = $empresa->razaosocial;
		$stdEmit->xFant = $empresa->nome;

		$ie = str_replace(".", "", @$empresa->inscricao_estadual);
		$ie = str_replace("/", "", $ie);
		$ie = str_replace("-", "", $ie);
		$stdEmit->IE = $ie;
		$stdEmit->CRT = $tributacao->regime == 0 ? 1 : 3;

		$cnpj = $this->limpaCNPJ($empresa->cnpj); // str_replace(".", "", $empresa->cnpj);
		//$cnpj = str_replace("/", "", $cnpj);
		//$cnpj = str_replace("-", "", $cnpj);
		$stdEmit->CNPJ = $cnpj;
		// $stdEmit->IM = $ie;

		$emit = $nfe->tagemit($stdEmit);

		// ENDERECO EMITENTE
		$stdEnderEmit = new \stdClass();
		$stdEnderEmit->xLgr = $empresa->endereco;
		$stdEnderEmit->nro = $empresa->numero;
		$stdEnderEmit->xCpl = "";

		$stdEnderEmit->xBairro = $empresa->bairro;
		$stdEnderEmit->cMun = $codMun;
		$stdEnderEmit->xMun = $empresa->cidade;
		$stdEnderEmit->UF =  $empresa->estado; //$stdIde->cUF;

		$cep = str_replace("-", "", $empresa->cep);
		$cep = str_replace(".", "", $cep);
		$stdEnderEmit->CEP = $cep;
		$stdEnderEmit->cPais = "1058";
		$stdEnderEmit->xPais = "BRASIL";

		$enderEmit = $nfe->tagenderEmit($stdEnderEmit);

		// DESTINATARIO
		$stdDest = new \stdClass();
		$stdDest->xNome = $destinatario->razaosocial;

		if (@$destinatario->contribuinte) {
			if ($destinatario->inscricao_estadual == 'ISENTO') {
				$stdDest->indIEDest = "2";
			} else {
				$stdDest->indIEDest = "1";
				$stdDest->IE = $this->limpaCNPJ($destinatario->inscricao_estadual);
			}
		} else {
			if ($destinatario->inscricao_estadual == 'ISENTO') {
				$stdDest->indIEDest = "2";
			} else if ($destinatario->inscricao_estadual != "") {

				$stdDest->indIEDest = "1";
				$stdDest->IE = $this->limpaCNPJ($destinatario->inscricao_estadual);
			} else {

				$stdDest->indIEDest = "9";
			}
		}

		$cnpj_cpf = $this->limpaCNPJ($destinatario->cnpj);

		if (strlen($cnpj_cpf) == 14) {
			$stdDest->CNPJ = $cnpj_cpf;
			$ie = str_replace(".", "", $destinatario->inscricao_estadual);
			$ie = str_replace("/", "", $ie);
			$ie = str_replace("-", "", $ie);
			$stdDest->IE = $ie;
		} else {
			$stdDest->CPF = $cnpj_cpf;
		}

		$dest = $nfe->tagdest($stdDest);

		$stdEnderDest = new \stdClass();
		$stdEnderDest->xLgr = $destinatario->endereco;
		$stdEnderDest->nro = $destinatario->numero;
		$stdEnderDest->xCpl = "";
		$stdEnderDest->xBairro = $destinatario->bairro;
		$stdEnderDest->cMun = $codMunDestinario;
		$stdEnderDest->xMun = $destinatario->cidade; // strtoupper($venda->cliente->cidade->nome);
		$stdEnderDest->UF = $destinatario->estado; // $cUfDestinario; // $venda->cliente->cidade->uf;

		$cep = str_replace("-", "", $destinatario->cep);
		$cep = str_replace(".", "", $cep);
		$stdEnderDest->CEP = $cep;
		$stdEnderDest->cPais = "1058";
		$stdEnderDest->xPais = "BRASIL";

		$enderDest = $nfe->tagenderDest($stdEnderDest);

		$somaProdutos = 0;
		$somaICMS = 0;
		$somaIPI = 0;
		//PRODUTOS
		$itemCont = 0;


		$itens = \App\Http\Dao\MovimentacaoItemDao::getListByMovimentacao($idVenda);

		$totalItens = count($itens);
		$somaFrete = 0;
		$somaDesconto = 0;
		$somaISS = 0;
		$somaServico = 0;

		$VBC = 0;
		foreach ($itens as $i) {

			if (is_null($i->qtde) || $i->qtde == 0) {
				continue;
			}

			$itemCont++;
			$stdProd = new \stdClass();
			$stdProd->item = $itemCont;
			//$stdProd->cEAN = 8 ;// $i->id_produto;
			//$stdProd->cEANTrib = $i->id_produto;
			$stdProd->cProd = UtilService::NVL($i->produto_codigo, $i->id_produto);
			$stdProd->xProd = $i->produto_nome;
			$ncm = UtilService::NVL($i->ncm, @$reg_config->NCM_PADRAO);
			$ncm = str_replace(".", "", $ncm);
			if ($config->CST_CSOSN_padrao == '500' || $config->CST_CSOSN_padrao == '60') {
				$stdProd->cBenef = 'SEM CBENEF';
			}

			//if($i->produto->perc_iss > 0){
			//	$stdProd->NCM = '00';
			//}else{
			//	$stdProd->NCM = $ncm;
			//}
			$stdProd->NCM = $ncm;

			$stdProd->CFOP = UtilService::NVL($i->cfop, @$reg_config->CFOP_PADRAO);

			if ($is_devolucao && !is_null($reg_parametros)) {
				$stdProd->CFOP = UtilService::NVL(@$reg_parametros->CFOP_PADRAO,  5202);
			}
			if ($is_compra && !is_null($reg_parametros)) {
				$stdProd->CFOP = UtilService::NVL(@$reg_parametros->CFOP_PADRAO,  1102);
			}

			$stdProd->uCom = $i->unidade_medida;
			$stdProd->qCom = $i->qtde;
			$stdProd->vUnCom = $this->format($i->valor_unidade);
			$stdProd->vProd = $this->format(($i->qtde * $i->valor_unidade));
			$stdProd->uTrib = $i->unidade_medida;
			$stdProd->qTrib = $i->qtde;
			$stdProd->vUnTrib = $this->format($i->valor_unidade);
			$stdProd->indTot = 1; //$i->produto->perc_iss > 0 ? 0 : 1;
			$somaProdutos += $stdProd->vProd;

			if ($i->cod_ean != "") {
				$stdProd->cEAN = $i->cod_ean;
				$stdProd->cEANTrib = $i->cod_ean;
			} else {
				$stdProd->cEAN = "SEM GTIN";
				$stdProd->cEANTrib = "SEM GTIN";
			}

			//<cEAN>SEM GTIN</cEAN>
			//<cEANTrib>SEM GTIN</cEANTrib>

			if (@$i->desconto !== null) {
				$i->desconto = 0;
			}

			$somaDesconto += $i->desconto;
			if ($i->desconto > 0) {

				$stdProd->vDesc = $this->format($i->desconto);
			}

			$vDesc = 0;

			/*
			<rastro>
<nLote>FE 23/06</nLote>
<qLote>220.000</qLote>
<dFab>2021-06-22</dFab>
<dVal>2021-09-20</dVal>
</rastro>  */


			if (@$venda->valor_frete !== null) {
				if ($venda->valor_frete > 0) {
					$vFt = $venda->valor_frete / $totalItens;
					$somaFrete += $vFt;
					$stdProd->vFrete = $this->format($vFt);
				}
			}

			$prod = $nfe->tagprod($stdProd);

			//TAG IMPOSTO
			$stdImposto = new \stdClass();
			$stdImposto->item = $itemCont;
			if (false && $i->produto->perc_iss > 0) {
				$stdImposto->vTotTrib = 0.00;
			}

			$imposto = $nfe->tagimposto($stdImposto);

			// ICMS
			if (true) {  //if($i->produto->perc_iss == 0){
				// regime normal
				if ($config->regime == 1) {

					//$venda->produto->CST  CST

					$stdICMS = new \stdClass();
					$stdICMS->item = $itemCont;
					$stdICMS->orig = 0;
					$stdICMS->CST = $config->CST_CSOSN_padrao;
					$stdICMS->modBC = 0;
					$stdICMS->vBC = $stdProd->vProd;
					$stdICMS->pICMS = $this->format($config->ICMS);
					$stdICMS->vICMS = $stdICMS->vBC * ($stdICMS->pICMS / 100);

					if ($config->CST_CSOSN_padrao == '500' || $config->CST_CSOSN_padrao == '60' || $config->CST_CSOSN_padrao == '40' || $is_compra  || $is_devolucao) {
						$stdICMS->pRedBCEfet = 0.00;
						$stdICMS->vBCEfet = 0.00;
						$stdICMS->pICMSEfet = 0.00;
						$stdICMS->vICMSEfet = 0.00;
					} else {
						$VBC += $stdProd->vProd;
					}

					$somaICMS += (($i->valor_unidade * $i->qtde)
						* ($stdICMS->pICMS / 100));
					$ICMS = $nfe->tagICMS($stdICMS);
					// regime simples
				} else {

					//$venda->produto->CST CSOSN

					$stdICMS = new \stdClass();

					$stdICMS->item = $itemCont;
					$stdICMS->orig = 0;
					$stdICMS->CSOSN = $config->CST_CSOSN_padrao;

					if ($config->CST_CSOSN_padrao == '500') {
						$stdICMS->vBCSTRet = 0.00;
						$stdICMS->pST = 0.00;
						$stdICMS->vICMSSTRet = 0.00;
					}

					$stdICMS->pCredSN = $this->format($config->ICMS);
					$stdICMS->vCredICMSSN = $this->format($config->ICMS);
					$ICMS = $nfe->tagICMSSN($stdICMS);

					$somaICMS = 0;
				}
			} else {
				$valorIss = ($i->valor * $i->quantidade) - $vDesc;
				$somaServico += $valorIss;
				$valorIss = $valorIss * ($i->produto->perc_iss / 100);
				$somaISS += $valorIss;


				$std = new \stdClass();
				$std->item = $itemCont;
				$std->vBC = $stdProd->vProd;
				$std->vAliq = $i->produto->perc_iss;
				$std->vISSQN = $this->format($valorIss);
				$std->cMunFG = $config->codMun;
				$std->cListServ = $i->produto->cListServ;
				$std->indISS = 1;
				$std->indIncentivo = 1;

				$nfe->tagISSQN($std);
			}

			//PIS
			$stdPIS = new \stdClass();
			$stdPIS->item = $itemCont;
			$stdPIS->CST = $config->CST_PIS_padrao;
			$stdPIS->vBC = $this->format($config->PIS) > 0 ? $stdProd->vProd : 0.00;
			$stdPIS->pPIS = $this->format($config->PIS);
			$stdPIS->vPIS = $this->format(($stdProd->vProd * $i->qtde) *
				($config->PIS / 100));
			$PIS = $nfe->tagPIS($stdPIS);

			//COFINS
			$stdCOFINS = new \stdClass();
			$stdCOFINS->item = $itemCont;
			$stdCOFINS->CST = $config->CST_COFINS_padrao;
			$stdCOFINS->vBC = $this->format($config->COFINS) > 0 ? $stdProd->vProd : 0.00;
			$stdCOFINS->pCOFINS = $this->format($config->COFINS);
			$stdCOFINS->vCOFINS = $this->format(($stdProd->vProd * $i->qtde) *
				($config->COFINS / 100));
			$COFINS = $nfe->tagCOFINS($stdCOFINS);


			//IPI

			$std = new \stdClass();
			$std->item = $itemCont;
			//999 – para tributação normal IPI
			$std->cEnq = '999';
			$std->CST = $config->CST_IPI_padrao;
			$std->vBC = $this->format($config->IPI) > 0 ? $stdProd->vProd : 0.00;
			$std->pIPI = $this->format($config->IPI);
			if ($config->IPI > 0) {
				$somaIPI += $std->vIPI = $stdProd->vProd * $this->format(($config->IPI / 100));
			}

			if ($this->format($config->IPI) > 0) {
				$nfe->tagIPI($std);
			}



			//TAG ANP

			if (false && strlen($i->produto->descricao_anp) > 5) {
				$stdComb = new \stdClass();
				$stdComb->item = $itemCont;
				$stdComb->cProdANP = $i->produto->codigo_anp;
				$stdComb->descANP = $i->produto->descricao_anp;
				$stdComb->UFCons = $venda->cliente->cidade->uf;

				$nfe->tagcomb($stdComb);
			}


			if (false) {
				$cest = $i->produto->CEST;
				$cest = str_replace(".", "", $cest);
				$stdProd->CEST = $cest;
				if (strlen($cest) > 0) {
					$std = new \stdClass();
					$std->item = $itemCont;
					$std->CEST = $cest;
					$nfe->tagCEST($std);
				}
			}
		}

		$vDesconto = 0.0; //$venda->desconto

		$stdICMSTot = new \stdClass();
		$stdICMSTot->vProd = $this->format($somaProdutos);
		$stdICMSTot->vBC = $this->format($VBC);

		$stdICMSTot->vICMS = $this->format($somaICMS);
		$stdICMSTot->vICMSDeson = 0.00;
		$stdICMSTot->vBCST = 0.00;
		$stdICMSTot->vST = 0.00;
		$stdICMSTot->vFrete = 0.00;

		if ($venda->valor_frete) $stdICMSTot->vFrete = $this->format($venda->valor_frete);
		else $stdICMSTot->vFrete = 0.00;

		$stdICMSTot->vSeg = 0.00;
		$stdICMSTot->vDesc = $somaDesconto;
		$stdICMSTot->vII = 0.00;
		$stdICMSTot->vIPI = 0.00;
		$stdICMSTot->vPIS = 0.00;
		$stdICMSTot->vCOFINS = 0.00;
		$stdICMSTot->vOutro = 0.00;

		if (false && $venda->frete) {
			$stdICMSTot->vNF =
				$this->format(($somaProdutos + $venda->frete->valor + $somaIPI) - $venda->desconto);
		} else $stdICMSTot->vNF = $this->format($somaProdutos + $somaIPI - $vDesconto);

		$stdICMSTot->vTotTrib = 0.00;
		$ICMSTot = $nfe->tagICMSTot($stdICMSTot);

		//inicio totalizao issqn

		if ($somaISS > 0) {
			$std = new \stdClass();
			$std->vServ = $this->format($somaServico + $vDesconto);
			$std->vBC = $this->format($somaServico);
			$std->vISS = $this->format($somaISS);
			$std->dCompet = date('Y-m-d');

			$std->cRegTrib = 6;

			$nfe->tagISSQNTot($std);
		}

		//fim totalizao issqn



		$stdTransp = new \stdClass();
		$stdTransp->modFrete = 0; //$venda->frete->tipo ?? '9';
		if (!is_null(@$objMovMeta->transporte)) {
			if (@$objMovMeta->transporte->id_modalidade_frete != "") {
				$stdTransp->modFrete = @$objMovMeta->transporte->id_modalidade_frete;
			}
		}

		$transp = $nfe->tagtransp($stdTransp);


		if ($transportadora !== null) {
			$std = new \stdClass();
			$std->xNome = UtilService::NVL($transportadora->razaosocial, $transportadora->nome);

			$std->xEnder = $transportadora->endereco;
			$std->xMun = strtoupper($transportadora->cidade);
			$std->UF = strtoupper($transportadora->estado);


			$cnpj_cpf = $this->limpaCNPJ($transportadora->cnpj);

			if (strlen($cnpj_cpf) == 14) $std->CNPJ = $cnpj_cpf;
			else $std->CPF = $cnpj_cpf;

			$nfe->tagtransporta($std);

			if (!is_null(@$objMovMeta->transporte)) {
				$this->setaTransporteData($nfe, $objMovMeta, $stdTransp);
			}
		}


		if (@$venda->valor_frete  != null && $venda->valor_frete > 0  && trim($venda->placa_veiculo) != "") {

			$std = new \stdClass();

			$placa = str_replace("-", "", $venda->placa_veiculo);
			$std->placa = strtoupper($placa);
			if ($transportadora !== null) {

				$std->UF = strtoupper($transportadora->estado);

				//if($config->UF == $venda->cliente->cidade->uf){
				$nfe->tagveicTransp($std);
				//}
			}


			if (
				false && $venda->frete->qtdVolumes > 0 && $venda->frete->peso_liquido > 0
				&& $venda->frete->peso_bruto > 0
			) {
				$stdVol = new \stdClass();
				$stdVol->item = 1;
				$stdVol->qVol = $venda->frete->qtdVolumes;
				$stdVol->esp = $venda->frete->especie;

				$stdVol->nVol = $venda->frete->numeracaoVolumes;
				$stdVol->pesoL = $venda->frete->peso_liquido;
				$stdVol->pesoB = $venda->frete->peso_bruto;
				$vol = $nfe->tagvol($stdVol);
			}
		}



		$std = new \stdClass();
		$std->CNPJ = $this->limpaCNPJ($empresa->cnpj); // getenv('RESP_CNPJ'); //CNPJ da pessoa jurídica responsável pelo sistema utilizado na emissão do documento fiscal eletrônico
		$std->xContato = $empresa->nome; //getenv('RESP_NOME'); //Nome da pessoa a ser contatada
		$std->email = $empresa->email; // getenv('RESP_EMAIL'); //E-mail da pessoa jurídica a ser contatada
		$std->fone = $empresa->fone;
		//$nfe->taginfRespTec($std);


		//Fatura
		if ($somaISS == 0) { //&& $venda->natureza->CFOP_saida_estadual != '5915' && $venda->natureza->CFOP_saida_inter_estadual != '6915'
			$stdFat = new \stdClass();
			$stdFat->nFat = (int)$lastNumero + 1;
			$stdFat->vOrig = $this->format($somaProdutos);
			$stdFat->vDesc = $this->format($venda->desconto);
			$stdFat->vLiq = $this->format($somaProdutos - $venda->desconto);

			if (!$is_transporte) {
				$fatura = $nfe->tagfat($stdFat);
			}
		}

		if (!$is_transporte) {

			//Duplicata
			if ($somaISS == 0) { //&& $venda->natureza->CFOP_saida_estadual != '5915' && $venda->natureza->CFOP_saida_inter_estadual != '6915'
				if (false && count($venda->duplicatas) > 0) {
					$contFatura = 1;
					foreach ($venda->duplicatas as $ft) {
						$stdDup = new \stdClass();
						$stdDup->nDup = "00" . $contFatura;
						$stdDup->dVenc = substr($ft->data_vencimento, 0, 10);
						$stdDup->vDup = $this->format($ft->valor_integral);

						$nfe->tagdup($stdDup);
						$contFatura++;
					}
				} else {
					if (false || $venda->forma_pagamento != 'a_vista') {
						$stdDup = new \stdClass();
						$stdDup->nDup = '001';
						$stdDup->dVenc = Date('Y-m-d');
						$stdDup->vDup =  $this->format($somaProdutos - $vDesconto);

						$nfe->tagdup($stdDup);
					}
				}
			}

			$stdPag = new \stdClass();
			$pag = $nfe->tagpag($stdPag);

			$stdDetPag = new \stdClass();


			$stdDetPag->tPag = "01"; // $venda->tipo_pagamento;
			$stdDetPag->vPag = $stdDetPag->tPag != '90' ? $this->format($somaProdutos - $somaDesconto) : 0.00;

			if ($stdDetPag->tPag  == '03' || $stdDetPag->tPag  == '04') {
				$stdDetPag->CNPJ = '12345678901234';
				$stdDetPag->tBand = '01';
				$stdDetPag->cAut = '3333333';
				$stdDetPag->tpIntegra = 1;
			}
			//$stdDetPag->indPag = $venda->forma_pagamento == 'a_vista' ?  0 : 1; 

			if ($is_compra) {
				$stdDetPag->indPag = 1;
				$stdDetPag->tPag = "05";
			} else if ($is_devolucao) {
				//$stdDetPag->indPag = 1;
				$stdDetPag->tPag = "90";
				$stdDetPag->vPag = 0.00;
			} else {
				$stdDetPag->indPag = 0;
			}



			$detPag = $nfe->tagdetPag($stdDetPag);
		}


		$obs = \App\Http\Dao\ConfigDao::executeScalar("select info_adicional as res from nota_fiscal where id_movimentacao = " .
			$venda->id . " and id_pagamento is null ");

		$stdInfoAdic = new \stdClass();
		$stdInfoAdic->infCpl = UtilService::NVL($obs, @$reg_parametros->infAdFisco);

		if ($is_devolucao) {

			$stdInfoAdic->infAdFisco = UtilService::NVL(
				@$reg_parametros->infAdFisco,
				"Isento de ICMS conforme artigo 8. e anexo I, artigo 36 do RICMS/2000"
			);
			$stdInfoAdic->infCpl = $this->encontraDadosRefDevolucao($idVenda, $nfe); // "Isento de ICMS conforme artigo 8. e anexo I, artigo 36 do RICMS/2000";

			//<infAdFisco>Isento de ICMS conforme artigo 8. e anexo I, artigo 36 do RICMS/2000/</infAdFisco>
			//<infCpl>SANTA FE/Devolucao Referente doc(s): 1/000317053 de 22/06/2021/QUALIDADE</infCpl>
		}

		$infoAdic = $nfe->taginfAdic($stdInfoAdic);



		$std = new \stdClass();
		$std->CNPJ = $empresa->cnpj; // getenv('RESP_CNPJ'); //CNPJ da pessoa jurídica responsável pelo sistema utilizado na emissão do documento fiscal eletrônico
		$std->xContato = $empresa->nome; //getenv('RESP_NOME'); //Nome da pessoa a ser contatada
		$std->email = $empresa->email; // getenv('RESP_EMAIL'); //E-mail da pessoa jurídica a ser contatada
		$std->fone = str_replace(" ", "", str_replace(")", "", str_replace("(", "", $empresa->fone))); // $empresa->fone;
		//$nfe->taginfRespTec($std);

		//die ( $nfe->getXML() );

		$id_nota_fiscal = \App\Http\Dao\ConfigDao::executeScalar("select id as res from nota_fiscal where id_movimentacao = " .
			$venda->id . " and id_pagamento is null ");


		if (getenv("AUTXML")) {
			$std = new \stdClass();
			$std->CNPJ = getenv("AUTXML");
			$std->CPF = null;
			$nfe->tagautXML($std);
		}

		try {
			$nfe->montaNFe();
			$arr = [
				'chave' => $nfe->getChave(),
				'xml' => $nfe->getXML(),
				'nNf' => $stdIde->nNF,
				'sequencial' => $stdIde->nNF
			];
			return $arr;
		} catch (\Exception $e) {
			return [
				'erros_xml' => $nfe->getErrors()
			];
		}
	}

	public function encontraDadosRefDevolucao($id_movimentacao_devolucao, &$nfe)
	{

		$id_movimentacao_pai = MovConferenciaDao::getIdPai($id_movimentacao_devolucao);

		$objMov = new MovConferenciaDao($id_movimentacao_pai);

		$ids_nfs = $objMov->getIdsFilhos("'NOTAFISCAL'");

		$conferencia = $objMov->getConferencia();

		if ($conferencia->nf_tipo == "nf_naoemite") {


			if ($ids_nfs != "") {
				$nota_fiscal = NotaFiscalDao::getNotaFiscal($ids_nfs);
				if (!is_null($nota_fiscal) && $nota_fiscal->xml != "") {
					//Devolucao Referente doc(s): 1/000317053 de 22/06/2021/QUALIDADE
					$objRef =	simplexml_load_string($nota_fiscal->xml);
					$data_us = UtilService::left($objRef->NFe->infNFe->ide->dhEmi, 10);
					$nNf = $objRef->NFe->infNFe->ide->nNF;
					//print_r( $objRef );

					$str = "Devolucao Referente doc(s): " . $nNf . " de " . UtilService::PgToOut($data_us);

					if (!is_null(@$objRef->protNFe)) {

						$NFref = new stdClass();
						$NFref->refNFe = $objRef->protNFe->infProt->chNFe;
						//$obj->NFref = $NFref;
						$nfe->tagrefNFe($NFref);

						if ($conferencia->nf_numero == "" ||   $conferencia->nf_serial == "") {
							$conferencia->nf_serial = @$objRef->protNFe->infProt->chNFe;
							$conferencia->nf_numero = $nNf;
							$conferencia->save();
						}
					}
					$objRef = null;
					return $str;
				}
			}
		} else {

			if ($conferencia->nf_numero != "" &&  $conferencia->nf_serial != "") {

				$NFref = new stdClass();
				$NFref->refNFe = $conferencia->nf_serial;
				$nfe->tagrefNFe($NFref);
				$str = "Devolucao Referente doc(s): " . $conferencia->nf_numero;
				return $str;
			}
		}

		return "";
	}

	public function format($number, $dec = 2)
	{
		return number_format((float) $number, $dec, ".", "");
	}

	public function consultaCadastro($cnpj, $uf)
	{
		try {

			$iest = '';
			$cpf = '';
			$response = $this->tools->sefazCadastro($uf, $cnpj, $iest, $cpf);

			$stdCl = new Standardize($response);

			$std = $stdCl->toStd();

			$arr = $stdCl->toArray();

			$json = $stdCl->toJson();

			echo $json;
		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	public function consultaChave($chave)
	{
		$response = $this->tools->sefazConsultaChave($chave);

		$stdCl = new Standardize($response);
		$arr = $stdCl->toArray();
		return $arr;
	}

	public function consultar($vendaId)
	{
		try {
			$venda = Venda::where('id', $vendaId)
				->first();
			$this->tools->model('55');

			$chave = $venda->chave;
			$response = $this->tools->sefazConsultaChave($chave);

			$stdCl = new Standardize($response);
			$arr = $stdCl->toArray();

			// $arr = json_decode($json);
			return json_encode($arr);
		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	public function inutilizar($nInicio, $nFinal, $justificativa)
	{
		try {
			$config = ConfigNota::first();
			$nSerie = $config->numero_serie_nfe;
			$nIni = $nInicio;
			$nFin = $nFinal;
			$xJust = $justificativa;
			$response = $this->tools->sefazInutiliza($nSerie, $nIni, $nFin, $xJust);

			$stdCl = new Standardize($response);
			$std = $stdCl->toStd();
			$arr = $stdCl->toArray();
			$json = $stdCl->toJson();

			return $arr;
		} catch (\Exception $e) {
			echo $e->getMessage();
		}
	}

	public function cancelar(&$nfe)
	{
		try {
			//$venda = Venda::where('id', $vendaId)->first();
			// $this->tools->model('55');

			//die($nfe->serial);
			$chave = $nfe->serial; //$nfe->chave;
			$response = $this->tools->sefazConsultaChave($chave);

			$stdCl = new Standardize($response);
			$arr = $stdCl->toArray();
			sleep(1);
			// return $arr;
			$xJust = $nfe->justificativa;


			$nProt = $arr['protNFe']['infProt']['nProt'];
			//print_r( $response );die(" " . $xJust);
			$response = $this->tools->sefazCancela($chave, $xJust, $nProt);
			sleep(2);
			$stdCl = new Standardize($response);
			$std = $stdCl->toStd();
			$arr = $stdCl->toArray();
			$json = $stdCl->toJson();

			if ($std->cStat != 128) {
				//TRATAR
			} else {
				$cStat = $std->retEvento->infEvento->cStat;
				//$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
				//print_r( $arr );
				//die("stat? ". $cStat );
				if ($cStat == '101' || $cStat == '135' || $cStat == '155') {
					//SUCESSO PROTOCOLAR A SOLICITAÇÂO ANTES DE GUARDAR
					$xml = Complements::toAuthorize($this->tools->lastRequest, $response);
					$nfe->xml = $xml;
					$nfe->status = 3;
					$nfe->info_adicional  = $cStat;
					$nfe->save();


					MovConferenciaDao::atualizaConferenciaByNotaTransmitida($nfe);
					//file_put_contents($public . 'xml_nfe_cancelada/' . $chave . '.xml', $xml);

					return $json;
				} else {

					return ['erro' => true, 'data' => $arr, 'status' => 402];
				}
			}
		} catch (\Exception $e) {
			echo $e->getMessage();
			//TRATAR
		}
	}

	public function cartaCorrecao($id, $correcao)
	{
		try {

			$venda = Venda::where('id', $id)
				->first();

			$chave = $venda->chave;
			$xCorrecao = $correcao;
			$nSeqEvento = $venda->sequencia_cce + 1;
			$response = $this->tools->sefazCCe($chave, $xCorrecao, $nSeqEvento);
			sleep(2);

			$stdCl = new Standardize($response);

			$std = $stdCl->toStd();

			$arr = $stdCl->toArray();

			$json = $stdCl->toJson();

			if ($std->cStat != 128) {
				//TRATAR
			} else {
				$cStat = $std->retEvento->infEvento->cStat;
				if ($cStat == '135' || $cStat == '136') {
					$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
					//SUCESSO PROTOCOLAR A SOLICITAÇÂO ANTES DE GUARDAR
					$xml = Complements::toAuthorize($this->tools->lastRequest, $response);
					file_put_contents($public . 'xml_nfe_correcao/' . $chave . '.xml', $xml);

					$venda->sequencia_cce = $venda->sequencia_cce + 1;
					$venda->save();
					return $json;
				} else {
					//houve alguma falha no evento 
					return ['erro' => true, 'data' => $arr, 'status' => 402];
					//TRATAR
				}
			}
		} catch (\Exception $e) {
			return $e->getMessage();
		}
	}

	/*
	public function simularOrcamento($venda)
	{


		$config = ConfigNota::first(); // iniciando os dados do emitente NF
		$tributacao = Tributacao::first(); // iniciando tributos

		$nfe = new Make();
		$stdInNFe = new \stdClass();
		$stdInNFe->versao = '4.00';
		$stdInNFe->Id = null;
		$stdInNFe->pk_nItem = '';

		$infNFe = $nfe->taginfNFe($stdInNFe);

		$vendaLast = Venda::lastNF();
		$lastNumero = $vendaLast;

		$stdIde = new \stdClass();
		$stdIde->cUF = $config->cUF;
		$stdIde->cNF = rand(11111, 99999);
		// $stdIde->natOp = $venda->natureza->natureza;
		$stdIde->natOp = $venda->natureza ? $venda->natureza->natureza : '';

		// $stdIde->indPag = 1; //NÃO EXISTE MAIS NA VERSÃO 4.00 // forma de pagamento

		$stdIde->mod = 55;
		$stdIde->serie = $config->numero_serie_nfe;
		$stdIde->nNF = (int)$lastNumero + 1;
		$stdIde->dhEmi = date("Y-m-d\TH:i:sP");
		$stdIde->dhSaiEnt = date("Y-m-d\TH:i:sP");
		$stdIde->tpNF = 1;
		$stdIde->idDest = $config->UF != $venda->cliente->cidade->uf ? 2 : 1;
		$stdIde->cMunFG = $config->codMun;
		$stdIde->tpImp = 1;
		$stdIde->tpEmis = 1;
		$stdIde->cDV = 0;
		$stdIde->tpAmb = $config->ambiente;
		$stdIde->finNFe = 1;
		$stdIde->indFinal = $venda->cliente->consumidor_final;
		$stdIde->indPres = 1;
		$stdIde->procEmi = '0';
		$stdIde->verProc = '2.0';
		// $stdIde->dhCont = null;
		// $stdIde->xJust = null;


		//
		$tagide = $nfe->tagide($stdIde);

		$stdEmit = new \stdClass();
		$stdEmit->xNome = $config->razao_social;
		$stdEmit->xFant = $config->nome_fantasia;

		$ie = str_replace(".", "", $config->ie);
		$ie = str_replace("/", "", $ie);
		$ie = str_replace("-", "", $ie);
		$stdEmit->IE = $ie;
		$stdEmit->CRT = $tributacao->regime == 0 ? 1 : 3;

		$cnpj = str_replace(".", "", $config->cnpj);
		$cnpj = str_replace("/", "", $cnpj);
		$cnpj = str_replace("-", "", $cnpj);
		$stdEmit->CNPJ = $cnpj;
		$stdEmit->IM = $ie;

		$emit = $nfe->tagemit($stdEmit);

		// ENDERECO EMITENTE
		$stdEnderEmit = new \stdClass();
		$stdEnderEmit->xLgr = $config->logradouro;
		$stdEnderEmit->nro = $config->numero;
		$stdEnderEmit->xCpl = "";

		$stdEnderEmit->xBairro = $config->bairro;
		$stdEnderEmit->cMun = $config->codMun;
		$stdEnderEmit->xMun = $config->municipio;
		$stdEnderEmit->UF = $config->UF;

		$cep = str_replace("-", "", $config->cep);
		$cep = str_replace(".", "", $cep);
		$stdEnderEmit->CEP = $cep;
		$stdEnderEmit->cPais = $config->codPais;
		$stdEnderEmit->xPais = $config->pais;

		$enderEmit = $nfe->tagenderEmit($stdEnderEmit);

		// DESTINATARIO
		$stdDest = new \stdClass();
		$stdDest->xNome = $venda->cliente->razao_social;

		if ($venda->cliente->contribuinte) {
			if ($venda->cliente->ie_rg == 'ISENTO') {
				$stdDest->indIEDest = "2";
			} else {
				$stdDest->indIEDest = "1";
			}
		} else {
			$stdDest->indIEDest = "9";
		}


		$cnpj_cpf = str_replace(".", "", $venda->cliente->cpf_cnpj);
		$cnpj_cpf = str_replace("/", "", $cnpj_cpf);
		$cnpj_cpf = str_replace("-", "", $cnpj_cpf);

		if (strlen($cnpj_cpf) == 14) {
			$stdDest->CNPJ = $cnpj_cpf;
			$ie = str_replace(".", "", $venda->cliente->ie_rg);
			$ie = str_replace("/", "", $ie);
			$ie = str_replace("-", "", $ie);
			$stdDest->IE = $ie;
		} else {
			$stdDest->CPF = $cnpj_cpf;
		}

		$dest = $nfe->tagdest($stdDest);

		$stdEnderDest = new \stdClass();
		$stdEnderDest->xLgr = $venda->cliente->rua;
		$stdEnderDest->nro = $venda->cliente->numero;
		$stdEnderDest->xCpl = "";
		$stdEnderDest->xBairro = $venda->cliente->bairro;
		$stdEnderDest->cMun = $venda->cliente->cidade->codigo;
		$stdEnderDest->xMun = strtoupper($venda->cliente->cidade->nome);
		$stdEnderDest->UF = $venda->cliente->estado;

		$cep = str_replace("-", "", $venda->cliente->cep);
		$cep = str_replace(".", "", $cep);
		$stdEnderDest->CEP = $cep;
		$stdEnderDest->cPais = "1058";
		$stdEnderDest->xPais = "BRASIL";

		$enderDest = $nfe->tagenderDest($stdEnderDest);

		$somaProdutos = 0;
		$somaICMS = 0;
		//PRODUTOS
		$itemCont = 0;

		$totalItens = count($venda->itens);
		$somaFrete = 0;
		$somaDesconto = 0;
		$somaISS = 0;
		$somaServico = 0;
		foreach ($venda->itens as $i) {
			$itemCont++;

			$stdProd = new \stdClass();
			$stdProd->item = $itemCont;
			$stdProd->cEAN = $i->produto->codBarras;
			$stdProd->cEANTrib = $i->produto->codBarras;
			$stdProd->cProd = UtilService::NVL(  $i->produto->codigo,  $i->produto->id);
			$stdProd->xProd = $i->produto->nome;
			$ncm = $i->produto->NCM;
			$ncm = str_replace(".", "", $ncm);

			if ($i->produto->perc_iss > 0) {
				$stdProd->NCM = '00';
			} else {
				$stdProd->NCM = $ncm;
			}

			$stdProd->CFOP = $config->UF != $venda->cliente->cidade->uf ?
				$i->produto->CFOP_saida_inter_estadual : $i->produto->CFOP_saida_estadual;


			$cest = $i->produto->CEST;
			$cest = str_replace(".", "", $cest);
			$stdProd->CEST = $cest;

			$stdProd->uCom = $i->produto->unidade_venda;
			$stdProd->qCom = $i->quantidade;
			$stdProd->vUnCom = $this->format($i->valor);
			$stdProd->vProd = $this->format(($i->quantidade * $i->valor));
			$stdProd->uTrib = $i->produto->unidade_venda;
			$stdProd->qTrib = $i->quantidade;
			$stdProd->vUnTrib = $this->format($i->desconto);
			$stdProd->indTot = $i->produto->perc_iss > 0 ? 0 : 1;
			$somaProdutos += ($i->quantidade * $i->valor);
			if ($venda->desconto > 0) {
				if ($itemCont < sizeof($venda->itens)) {
					$stdProd->vDesc = $this->format($venda->desconto / $totalItens);
					$somaDesconto += $venda->desconto / $totalItens;
				} else {
					$stdProd->vDesc = $venda->desconto - $somaDesconto;
				}
			}

			if ($venda->frete) {
				if ($venda->frete->valor > 0) {
					$somaFrete += $vFt = $venda->frete->valor / $totalItens;
					$stdProd->vFrete = $this->format($vFt);
				}
			}

			$prod = $nfe->tagprod($stdProd);

			//TAG IMPOSTO

			$stdImposto = new \stdClass();
			$stdImposto->item = $itemCont;
			if ($i->produto->perc_iss > 0) {
				$stdImposto->vTotTrib = 0.00;
			}

			$imposto = $nfe->tagimposto($stdImposto);

			// ICMS
			if ($i->produto->perc_iss == 0) {
				// regime normal
				if ($tributacao->regime == 1) {

					//$venda->produto->CST  CST

					$stdICMS = new \stdClass();
					$stdICMS->item = $itemCont;
					$stdICMS->orig = 0;
					$stdICMS->CST = $i->produto->CST_CSOSN;
					$stdICMS->modBC = 0;
					$stdICMS->vBC = $this->format($i->valor * $i->quantidade);
					$stdICMS->pICMS = $this->format($i->produto->perc_icms);
					$stdICMS->vICMS = $stdICMS->vBC * ($stdICMS->pICMS / 100);

					$somaICMS += (($i->valor * $i->quantidade)
						* ($stdICMS->pICMS / 100));
					$ICMS = $nfe->tagICMS($stdICMS);
					// regime simples
				} else {

					//$venda->produto->CST CSOSN

					$stdICMS = new \stdClass();

					$stdICMS->item = $itemCont;
					$stdICMS->orig = 0;
					$stdICMS->CSOSN = $i->produto->CST_CSOSN;

					if ($i->produto->CST_CSOSN == '500') {
						$stdICMS->vBCSTRet = 0.00;
						$stdICMS->pST = 0.00;
						$stdICMS->vICMSSTRet = 0.00;
					}

					$stdICMS->pCredSN = $this->format($i->produto->perc_icms);
					$stdICMS->vCredICMSSN = $this->format($i->produto->perc_icms);
					$ICMS = $nfe->tagICMSSN($stdICMS);

					$somaICMS = 0;
				}
			} else {
				$valorIss = $i->valor * $i->quantidade;
				$somaServico += $valorIss;
				$valorIss = $valorIss * ($i->produto->perc_iss / 100);
				$somaISS += $valorIss;


				$std = new \stdClass();
				$std->item = $itemCont;
				$std->vBC = $stdProd->vProd;
				$std->vAliq = $i->produto->perc_iss;
				$std->vISSQN = $this->format($valorIss);
				$std->cMunFG = $config->codMun;
				$std->cListServ = $i->produto->cListServ;
				$std->indISS = 1;
				$std->indIncentivo = 1;

				$nfe->tagISSQN($std);
			}

			//PIS
			$stdPIS = new \stdClass();
			$stdPIS->item = $itemCont;
			$stdPIS->CST = $i->produto->CST_PIS;
			$stdPIS->vBC = $this->format($i->produto->perc_pis) > 0 ? $stdProd->vProd : 0.00;
			$stdPIS->pPIS = $this->format($i->produto->perc_pis);
			$stdPIS->vPIS = $this->format(($stdProd->vProd * $i->quantidade) *
				($i->produto->perc_pis / 100));
			$PIS = $nfe->tagPIS($stdPIS);

			//COFINS
			$stdCOFINS = new \stdClass();
			$stdCOFINS->item = $itemCont;
			$stdCOFINS->CST = $i->produto->CST_COFINS;
			$stdCOFINS->vBC = $this->format($i->produto->perc_cofins) > 0 ? $stdProd->vProd : 0.00;
			$stdCOFINS->pCOFINS = $this->format($i->produto->perc_cofins);
			$stdCOFINS->vCOFINS = $this->format(($stdProd->vProd * $i->quantidade) *
				($i->produto->perc_cofins / 100));
			$COFINS = $nfe->tagCOFINS($stdCOFINS);


			//IPI

			$std = new \stdClass();
			$std->item = $itemCont;
			//999 – para tributação normal IPI
			$std->cEnq = '999';
			$std->CST = $i->produto->CST_IPI;
			$std->vBC = $this->format($i->produto->perc_ipi) > 0 ? $stdProd->vProd : 0.00;
			$std->pIPI = $this->format($i->produto->perc_ipi);
			$std->vIPI = $stdProd->vProd * $this->format(($i->produto->perc_ipi / 100));

			$nfe->tagIPI($std);



			//TAG ANP

			if (strlen($i->produto->descricao_anp) > 5) {
				$stdComb = new \stdClass();
				$stdComb->item = 1;
				$stdComb->cProdANP = $i->produto->codigo_anp;
				$stdComb->descANP = $i->produto->descricao_anp;
				$stdComb->UFCons = $venda->cliente->cidade->uf;

				$nfe->tagcomb($stdComb);
			}
		}


		$stdICMSTot = new \stdClass();
		$stdICMSTot->vProd = 0;
		$stdICMSTot->vBC = $tributacao->regime == 1 ? $this->format($somaProdutos) : 0.00;
		$stdICMSTot->vICMS = $this->format($somaICMS);
		$stdICMSTot->vICMSDeson = 0.00;
		$stdICMSTot->vBCST = 0.00;
		$stdICMSTot->vST = 0.00;

		if ($venda->frete) $stdICMSTot->vFrete = $this->format($venda->frete->valor);
		else $stdICMSTot->vFrete = 0.00;

		$stdICMSTot->vSeg = 0.00;
		$stdICMSTot->vDesc = $this->format($venda->desconto);
		$stdICMSTot->vII = 0.00;
		$stdICMSTot->vIPI = 0.00;
		$stdICMSTot->vPIS = 0.00;
		$stdICMSTot->vCOFINS = 0.00;
		$stdICMSTot->vOutro = 0.00;

		if ($venda->frete) {
			$stdICMSTot->vNF =
				$this->format(($somaProdutos + $venda->frete->valor) - $venda->desconto);
		} else $stdICMSTot->vNF = $this->format($somaProdutos - $venda->desconto);

		$stdICMSTot->vTotTrib = 0.00;
		$ICMSTot = $nfe->tagICMSTot($stdICMSTot);

		//inicio totalizao issqn

		if ($somaISS > 0) {
			$std = new \stdClass();
			$std->vServ = $this->format($somaServico);
			$std->vBC = $this->format($somaServico);
			$std->vISS = $this->format($somaISS);
			$std->dCompet = date('Y-m-d');

			$std->cRegTrib = 6;

			$nfe->tagISSQNTot($std);
		}

		//fim totalizao issqn



		$stdTransp = new \stdClass();
		$stdTransp->modFrete = 0; // $venda->frete->tipo ?? '9';

		$transp = $nfe->tagtransp($stdTransp);


		if ($venda->transportadora) {
			$std = new \stdClass();
			$std->xNome = $venda->transportadora->razao_social;

			$std->xEnder = $venda->transportadora->logradouro;
			$std->xMun = strtoupper($venda->transportadora->cidade->nome);
			$std->UF = $venda->transportadora->cidade->uf;


			$cnpj_cpf = $venda->transportadora->cnpj_cpf;
			$cnpj_cpf = str_replace(".", "", $venda->transportadora->cnpj_cpf);
			$cnpj_cpf = str_replace("/", "", $cnpj_cpf);
			$cnpj_cpf = str_replace("-", "", $cnpj_cpf);

			if (strlen($cnpj_cpf) == 14) $std->CNPJ = $cnpj_cpf;
			else $std->CPF = $cnpj_cpf;

			$nfe->tagtransporta($std);
		}


		if ($venda->frete != null) {

			$std = new \stdClass();


			$placa = str_replace("-", "", $venda->frete->placa);
			$std->placa = strtoupper($placa);
			$std->UF = $venda->frete->uf;

			if ($config->UF == $venda->cliente->cidade->uf) {
				$nfe->tagveicTransp($std);
			}


			if (
				$venda->frete->qtdVolumes > 0 && $venda->frete->peso_liquido > 0
				&& $venda->frete->peso_bruto > 0
			) {
				$stdVol = new \stdClass();
				$stdVol->item = 1;
				$stdVol->qVol = $venda->frete->qtdVolumes;
				$stdVol->esp = $venda->frete->especie;

				$stdVol->nVol = $venda->frete->numeracaoVolumes;
				$stdVol->pesoL = $venda->frete->peso_liquido;
				$stdVol->pesoB = $venda->frete->peso_bruto;
				$vol = $nfe->tagvol($stdVol);
			}
		}



		$stdResp = new \stdClass();
		$stdResp->CNPJ = '08543628000145';
		$stdResp->xContato = 'Slym';
		$stdResp->email = 'marcos05111993@gmail.com';
		$stdResp->fone = '43996347016';

		$nfe->taginfRespTec($stdResp);


		//Fatura
		if ($somaISS == 0 && $venda->natureza->CFOP_saida_estadual != '5915' && $venda->natureza->CFOP_saida_inter_estadual != '6915') {
			$stdFat = new \stdClass();
			$stdFat->nFat = (int)$lastNumero + 1;
			$stdFat->vOrig = $this->format($somaProdutos);
			$stdFat->vDesc = $this->format($venda->desconto);
			$stdFat->vLiq = $this->format($somaProdutos - $venda->desconto);

			$fatura = $nfe->tagfat($stdFat);
		}

		//Duplicata
		if ($somaISS == 0 && $venda->natureza->CFOP_saida_estadual != '5915' && $venda->natureza->CFOP_saida_inter_estadual != '6915') {
			if (count($venda->duplicatas) > 0) {
				$contFatura = 1;
				foreach ($venda->duplicatas as $ft) {
					$stdDup = new \stdClass();
					$stdDup->nDup = "00" . $contFatura;
					$stdDup->dVenc = substr($ft->data_vencimento, 0, 10);
					$stdDup->vDup = $this->format($ft->valor_integral);

					$nfe->tagdup($stdDup);
					$contFatura++;
				}
			} else {
				$stdDup = new \stdClass();
				$stdDup->nDup = '001';
				$stdDup->dVenc = Date('Y-m-d');
				$stdDup->vDup =  $this->format($somaProdutos - $venda->desconto);

				$nfe->tagdup($stdDup);
			}
		}
		



		$stdPag = new \stdClass();
		$pag = $nfe->tagpag($stdPag);

		$stdDetPag = new \stdClass();


		$stdDetPag->tPag = $venda->tipo_pagamento;
		$stdDetPag->vPag = $this->format($stdProd->vProd - $venda->desconto);

		if ($venda->tipo_pagamento == '03' || $venda->tipo_pagamento == '04') {
			$stdDetPag->CNPJ = '12345678901234';
			$stdDetPag->tBand = '01';
			$stdDetPag->cAut = '3333333';
			$stdDetPag->tpIntegra = 1;
		}
		$stdDetPag->indPag = $venda->forma_pagamento == 'a_vista' ?  0 : 1;

		$detPag = $nfe->tagdetPag($stdDetPag);



		$stdInfoAdic = new \stdClass();
		$stdInfoAdic->infCpl = $venda->observacao;

		$infoAdic = $nfe->taginfAdic($stdInfoAdic);


		$std = new \stdClass();
		$std->CNPJ = $empresa->cnpj; // getenv('RESP_CNPJ'); //CNPJ da pessoa jurídica responsável pelo sistema utilizado na emissão do documento fiscal eletrônico
		$std->xContato = $empresa->nome; //getenv('RESP_NOME'); //Nome da pessoa a ser contatada
		$std->email = $empresa->email; // getenv('RESP_EMAIL'); //E-mail da pessoa jurídica a ser contatada
		$std->fone = $empresa->fone; //getenv('RESP_FONE'); //Telefone da pessoa jurídica/física a ser contatada
		$nfe->taginfRespTec($std);

		if (false && getenv("AUTXML")) {
			$std = new \stdClass();
			$std->CNPJ = getenv("AUTXML");
			$std->CPF = null;
			$nfe->tagautXML($std);
		}

		// if($nfe->montaNFe()){
		// 	$arr = [
		// 		'chave' => $nfe->getChave(),
		// 		'xml' => $nfe->getXML(),
		// 		'nNf' => $stdIde->nNF
		// 	];
		// 	return $arr;
		// } else {
		// 	throw new Exception("Erro ao gerar NFe");
		// }

		try {
			$nfe->montaNFe();
			$arr = [
				'chave' => $nfe->getChave(),
				'xml' => $nfe->getXML(),
				'nNf' => $stdIde->nNF
			];
			return $arr;
		} catch (\Exception $e) {
			return [
				'erros_xml' => $nfe->getErrors()
			];
		}
	}
	*/

	public function sign($xml)
	{
		return $this->tools->signNFe($xml);
	}

	public function transmitir($signXml, &$reg_nota_fiscal)
	{
		try {
			$chave = $reg_nota_fiscal->id;
			$idLote = str_pad(100, 15, '0', STR_PAD_LEFT);
			$resp = $this->tools->sefazEnviaLote([$signXml], $idLote);

			$st = new Standardize();
			$std = $st->toStd($resp);
			sleep(2);
			if ($std->cStat != 103) {

				$reg_nota_fiscal->status = 4; //Erro
				$reg_nota_fiscal->protocolo = $std->cStat . " - " . $std->xMotivo;
				$reg_nota_fiscal->save();

				return "[$std->cStat] - $std->xMotivo";
			}
			sleep(3);
			$recibo = $std->infRec->nRec;

			$protocolo = $this->tools->sefazConsultaRecibo($recibo);
			sleep(4);
			//return $protocolo;
			//$public = getenv('SERVIDOR_WEB') ? 'public/' : '';
			try {
				$xml = Complements::toAuthorize($signXml, $protocolo);


				$reg_nota_fiscal->status = 2; //Transmitida..
				$reg_nota_fiscal->protocolo = $protocolo;
				$reg_nota_fiscal->recibo = $recibo;
				$reg_nota_fiscal->sefaz_lote = $idLote;
				$reg_nota_fiscal->xml = 	$xml;
				$reg_nota_fiscal->save();

				MovConferenciaDao::atualizaConferenciaByNotaTransmitida($reg_nota_fiscal);



				//header('Content-type: text/xml; charset=UTF-8');
				//file_put_contents($public . 'xml_nfe/' . $chave . '.xml', $xml);
				return $recibo;
				// $this->printDanfe($xml);
			} catch (\Exception $e) {

				$reg_nota_fiscal->status = 4; //Erro
				$reg_nota_fiscal->protocolo = $st->toJson($protocolo);
				$reg_nota_fiscal->save();

				return "Erro: " . $st->toJson($protocolo);
			}
		} catch (\Exception $e) {

			$reg_nota_fiscal->status = 4; //Erro
			$reg_nota_fiscal->protocolo = $e->getMessage();
			$reg_nota_fiscal->save();

			return "Erro: " . $e->getMessage();
		}
	}

	public static function unidadesMedida()
	{
		return [
			"AMPOLA",
			"BALDE",
			"BANDEJ",
			"BARRA",
			"BISNAG",
			"BLOCO",
			"BOBINA",
			"BOMB",
			"CAPS",
			"CART",
			"CENTO",
			"CJ",
			"CM",
			"CM2",
			"CX",
			"CX2",
			"CX3",
			"CX5",
			"CX10",
			"CX15",
			"CX20",
			"CX25",
			"CX50",
			"CX100",
			"DISP",
			"DUZIA",
			"EMBAL",
			"FARDO",
			"FOLHA",
			"FRASCO",
			"GALAO",
			"GF",
			"GRAMAS",
			"JOGO",
			"KG",
			"KIT",
			"LATA",
			"LITRO",
			"M",
			"M2",
			"M3",
			"MILHEI",
			"ML",
			"MWH",
			"PACOTE",
			"PALETE",
			"PARES",
			"PC",
			"POTE",
			"K",
			"RESMA",
			"ROLO",
			"SACO",
			"SACOLA",
			"TAMBOR",
			"TANQUE",
			"TON",
			"TUBO",
			"UNID",
			"VASIL",
			"VIDRO"
		];
	}



	public static function listaCSTCSOSN()
	{
		return [
			'00' => 'Tributa integralmente',
			'10' => 'Tributada e com cobrança do ICMS por substituição tributária',
			'20' => 'Com redução da Base de Calculo',
			'30' => 'Isenta / não tributada e com cobrança do ICMS por substituição tributária',
			'40' => 'Isenta',
			'41' => 'Não tributada',
			'50' => 'Com suspensão',
			'51' => 'Com diferimento',
			'60' => 'ICMS cobrado anteriormente por substituição tributária',
			'70' => 'Com redução da BC e cobrança do ICMS por substituição tributária',
			'90' => 'Outras',

			'101' => 'Tributada pelo Simples Nacional com permissão de crédito',
			'102' => 'Tributada pelo Simples Nacional sem permissão de crédito',
			'103' => 'Isenção do ICMS no Simples Nacional para faixa de receita bruta',
			'201' => 'Tributada pelo Simples Nacional com permissão de crédito e com cobrança do ICMS por substituição tributária',
			'202' => 'Tributada pelo Simples Nacional sem permissão de crédito e com cobrança do ICMS por substituição tributária',
			'203' => 'Isenção do ICMS no Simples Nacional para faixa de receita bruta e com cobrança do ICMS por substituição tributária',
			'300' => 'Imune',
			'400' => 'Não tributada pelo Simples Nacional',
			'500' => 'ICMS cobrado anteriormente por substituição tributária (substituído) ou por antecipação',
			'900' => 'Outros',
		];
	}

	public static function listaCST_PIS_COFINS()
	{
		return [
			'01' => 'Operação Tributável com Alíquota Básica',
			'02' => 'Operação Tributável com Alíquota por Unidade de Medida de Produto',
			'03' => 'Operação Tributável com Alíquota por Unidade de Medida de Produto',
			'04' => 'Operação Tributável Monofásica – Revenda a Alíquota Zero',
			'05' => 'Operação Tributável por Substituição Tributária',
			'06' => 'Operação Tributável a Alíquota Zero',
			'07' => 'Operação Isenta da Contribuição',
			'08' => 'Operação sem Incidência da Contribuição',
			'09' => 'Operação com Suspensão da Contribuição',
			'49' => 'Outras Operações de Saída',

			'70' =>	'Operação de Aquisição sem Direito a Crédito',
			'71' => 'Operação de Aquisição com Isenção',
			'72' =>	'Operação de Aquisição com Suspensão',
			'73' =>	'Operação de Aquisição a Alíquota Zero',
			'74' =>	'Operação de Aquisição sem Incidência da Contribuição',
			'75' =>	'Operação de Aquisição por Substituição Tributária',
			'98' =>	'Outras Operações de Entrada',
			'99' =>   'Outras Operações'
		];
	}

	public static function listaCST_IPI()
	{
		return [
			'50' => 'Saída Tributada',
			'51' => 'Saída Tributável com Alíquota Zero',
			'52' => 'Saída Isenta',
			'53' => 'Saída Não Tributada',
			'54' => 'Saída Imune',
			'55' => 'Saída com Suspensão',
			'99' => 'Outras Saídas'
		];
	}


	public static function geraColunaAcao(&$reg)
	{

		$meta_dados_acao = null;

		if (is_null(@$reg->meta_dados_acao)) {
			$obj  = new stdClass();

			if (@$reg->meta_dados != "") {
				$obj->VENDA = json_decode($reg->meta_dados);
			} else {
				$obj->VENDA = new stdClass();
			}

			$obj->COMPRA = new stdClass();
			$obj->DEVOLUCAO = new stdClass();
			$obj->TRANSPORTE = new stdClass();

			$meta_dados_acao = $obj;
			//$reg->meta_dados_acao = $obj;
		} else {

			$meta_dados_acao = json_decode($reg->meta_dados_acao);
		}

		self::garantePadrao($meta_dados_acao);

		return $meta_dados_acao;
	}

	public static function garantePadrao(&$obj)
	{

		self::setValorProp($obj->VENDA, "natOp", 1);
		self::setValorProp($obj->VENDA, "tpEmis", 1);
		self::setValorProp($obj->VENDA, "cDV", 0);
		self::setValorProp($obj->VENDA, "finNFe", 1);
		self::setValorProp($obj->VENDA, "indFinal", 0);
		self::setValorProp($obj->VENDA, "indPres", 1);
		self::setValorProp($obj->VENDA, "tpNF", 1);
		self::setValorProp($obj->VENDA, "CFOP_PADRAO", "");



		self::setValorProp($obj->COMPRA, "natOp", "COMPRAS PARA COMERCIALIZACAO (PRAZO)");
		self::setValorProp($obj->COMPRA, "tpEmis", 1);
		self::setValorProp($obj->COMPRA, "cDV", 0);
		self::setValorProp($obj->COMPRA, "finNFe", 1);
		self::setValorProp($obj->COMPRA, "indFinal", 0);
		self::setValorProp($obj->COMPRA, "indPres", 1);
		self::setValorProp($obj->COMPRA, "tpNF", 0);
		self::setValorProp($obj->COMPRA, "CFOP_PADRAO", 1102);


		self::setValorProp($obj->DEVOLUCAO, "natOp", "DEVOLUCAO DE COMPRA PARA COMERCIALIZACAO");
		self::setValorProp($obj->DEVOLUCAO, "tpEmis", 1);
		self::setValorProp($obj->DEVOLUCAO, "cDV", 3);
		self::setValorProp($obj->DEVOLUCAO, "finNFe", 4);
		self::setValorProp($obj->DEVOLUCAO, "indFinal", 0);
		self::setValorProp($obj->DEVOLUCAO, "indPres", 1);
		self::setValorProp($obj->DEVOLUCAO, "tpNF", 0);
		self::setValorProp($obj->DEVOLUCAO, "CFOP_PADRAO", 5202);
		self::setValorProp($obj->DEVOLUCAO, "infAdFisco", "Isento de ICMS conforme artigo 8. e anexo I, artigo 36 do RICMS/2000");



		self::setValorProp($obj->TRANSPORTE, "natOp", "TRANSFERENCIA DE PRODUCAO DO ESTABELECIMENTO");
		self::setValorProp($obj->TRANSPORTE, "tpEmis", 1);
		self::setValorProp($obj->TRANSPORTE, "cDV", 0);
		self::setValorProp($obj->TRANSPORTE, "finNFe", 1);
		self::setValorProp($obj->TRANSPORTE, "indFinal", 0);
		self::setValorProp($obj->TRANSPORTE, "indPres", 1);
		self::setValorProp($obj->TRANSPORTE, "tpNF", 1);
		self::setValorProp($obj->TRANSPORTE, "CFOP_PADRAO", 5151);
		self::setValorProp($obj->TRANSPORTE, "infAdFisco", "OPERACAO DE TRANFERENCIA INTERNA ENTRE ESTABELECIMENTOS DO MESMO
		CONTRIBUINTE INOCORRENCIA DE CIRCULACAO ECONOMICA NAO INCIDENCIA DE ICMS");
	}

	public static function setValorProp(&$obj, $prop, $value)
	{
		//@$obj->$prop = $value;
		if ((is_null(@$obj->$prop) || @$obj->$prop == "") && @$obj->$prop !== 0) {
			@$obj->$prop = $value;
			//	die("aaa ");
		}
	}

	public function setaTransporteData(&$nfe, &$objMovMeta, &$stdTransp)
	{
		if (!is_null(@$objMovMeta->transporte)) {

			if (@$objMovMeta->transporte->placa != "" || @$objMovMeta->transporte->rntc != "" || @$objMovMeta->transporte->uf_veiculo != "") {

				$stdVeicTransp = new \stdClass();
				$stdVeicTransp->placa =  @$objMovMeta->transporte->placa;
				$stdVeicTransp->RNTC =  @$objMovMeta->transporte->rntc;
				$stdVeicTransp->UF =  @$objMovMeta->transporte->uf_veiculo;
				$nfe->tagveicTransp($stdVeicTransp);
			}


			if (
				@$objMovMeta->transporte->numero_volumes != "" || @$objMovMeta->transporte->volume_especial != "" ||
				@$objMovMeta->transporte->marca != ""  ||  @$objMovMeta->transporte->qtde_volume != ""
			) {


				$stdVol = new \stdClass();
				$stdVol->qVol = @$objMovMeta->transporte->qtde_volume;
				$stdVol->nVol = @$objMovMeta->transporte->numero_volumes;
				$stdVol->marca =  @$objMovMeta->transporte->marca;
				$stdVol->esp =  @$objMovMeta->transporte->especie;
				$stdVol->pesoB = $this->format(@$objMovMeta->transporte->peso_bruto);
				$stdVol->pesoL = $this->format(@$objMovMeta->transporte->peso_liquido);
				//<esp>CAIXA</esp>
				$nfe->tagvol($stdVol);
			}

			if (@$objMovMeta->transporte->id_modalidade_frete != "") {
				$stdTransp->modFrete = @$objMovMeta->transporte->id_modalidade_frete;
			}
		}
	}
}
