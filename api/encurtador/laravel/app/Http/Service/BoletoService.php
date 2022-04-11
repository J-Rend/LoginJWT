<?php

namespace App\Http\Service;

use App\Http\Dao\ConfigDao;
use App\Http\Dao\ParametersByItemDao;
use App\Log;
use Illuminate\Support\Facades\DB;
use DateTime;
use Illuminate\Support\Facades\Auth;

use OpenBoleto\Agente;
use OpenBoleto\Banco\BancoDoBrasil;
use OpenBoleto\Banco\Itau;
use OpenBoleto\Banco\Bradesco;
use OpenBoleto\Banco\Santander;
use OpenBoleto\Banco\Cecred;
use OpenBoleto\Banco\Sicoob;

class BoletoService
{
     var $banco;

    private function getAgente(array $cedente){

        //$sacado = new Agente('Fernando Maia', '023.434.234-34', 'ABC 302 Bloco N', '72000-000', 'Brasília', 'DF');
        $agente = new Agente($cedente["nome"], $cedente["cnpj"], $cedente["endereco"], $cedente["cep"],$cedente["cidade"], $cedente["estado"]);

        return $agente;
    
    }

    public function __construct($banco)
    {
        $this->banco = $banco;
    }

    //https://github.com/openboleto/openboleto/blob/master/samples/
    public function gerar($bolData)
    {
        $cedente = $this->getAgente( (array)$bolData->cedente );
        $sacado = $this->getAgente( (array)$bolData->sacado );

        $params = [ 'dataVencimento' => $bolData->vencimento,
                                'valor' =>  $bolData->valor,
                                'sequencial' => $bolData->nosso_numero,
                                'sacado' => $sacado,
                                'cedente' => $cedente,
                                'agencia' => $bolData->agencia, // Até 4 dígitos
                                'carteira' => $bolData->carteira,
                                'conta' => $bolData->conta, // Até 8 dígitos
                                'convenio' => $bolData->convenio, // 4, 6 ou 7 dígitos
                            
                                // Caso queira um número sequencial de 17 dígitos, a cobrança deverá:
                                // - Ser sem registro (Carteiras 16 ou 17)
                                // - Convênio com 6 dígitos
                                // Para isso, defina a carteira como 21 (mesmo sabendo que ela é 16 ou 17, isso é uma regra do banco)
                            
                                // Parâmetros recomendáveis
                                //'logoPath' => 'http://empresa.com.br/logo.jpg', // Logo da sua empresa
                                'contaDv' => $bolData->conta_dv,
                                'agenciaDv' => $bolData->agencia_dv,
                                'descricaoDemonstrativo' => $bolData->demonstrativo_txt,
                                'instrucoes' => $bolData->instrucoes_txt 
         ];

         if ( @$bolData->logo != "" ){
             $params['logoPath'] = @$bolData->logo;
         }


         $boleto = null;

         if ( $this->banco == "bb"){
             $boleto = new BancoDoBrasil($params);
         }
         if ( $this->banco == "bradesco"){
            $boleto = new  Bradesco($params);
         }
         if ( $this->banco == "itau"){
            $boleto = new  Itau($params);
         }
         if ( $this->banco == "sandanter_banespa"){
            $boleto = new  Santander($params);
         }
         if ( $this->banco == "cecred"){
             if ( @$bolData->cod_banco != ""){
              //  $params['modalidade'] = @$bolData->modalidade;
             }
            $boleto = new  Cecred($params);
         }
         if ( $this->banco == "sicoob"){
            if ( @$bolData->modalidade != ""){
                $params['modalidade'] =@$bolData->modalidade;
             }
            $boleto = new  Sicoob($params);
         }

         if ( ! is_null($boleto) ){
             return $boleto->getOutput();
         }

         return "";

         

    

    }

}

