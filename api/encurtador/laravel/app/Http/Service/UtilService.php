<?php

namespace App\Http\Service;

use Exception;

class  UtilService
{
	public static $SAVE = "SAVE";
	public static $LOAD = "LOAD";
	public static $DEL = "DEL";




	public static function hasArrayValue($array, $colunm, $value)
	{
		for ($i = 0; $i < count($arr); $i++) {

			if ($arr[$colunm] == $value) {
				return true;
			}
		}

		return false;
	}



	static function getDescByCOD($arr, $campoid, $campotext, $valor)
	{

		for ($i = 0; $i < count($arr); $i++) {


			if ($arr[$i][$campoid] == $valor)
				return 	$arr[$i][$campotext];
		}

		return "";
	}

	static function getOnlyDate($data)
	{

		if ($data != "") {
			$ar = explode(" ", $data);
			return $ar[0];
		}

		return "";
	}






	static function converteSegundos_ParaHoraMinuto($seconds)
	{

		//Math.floor
		$hours = floor($seconds / 3600);
		$seconds -= $hours * 3600;
		$minutes = floor($seconds / 60);
		$seconds -= $minutes * 60;

		return str_pad($hours, 2, '0', STR_PAD_LEFT) . ":" . str_pad($minutes, 2, '0', STR_PAD_LEFT);
	}

	static function geraHidden($nome, $valor)
	{
		echo "
				<input type='hidden' name='" . $nome . "' value='" . $valor . "' > 
				";
	}

	public static function romano($N)
	{
		$N1 = $N;
		$Y = "";
		while ($N / 1000 >= 1) {
			$Y .= "M";
			$N = $N - 1000;
		}
		if ($N / 900 >= 1) {
			$Y .= "CM";
			$N = $N - 900;
		}
		if ($N / 500 >= 1) {
			$Y .= "D";
			$N = $N - 500;
		}
		if ($N / 400 >= 1) {
			$Y .= "CD";
			$N = $N - 400;
		}
		while ($N / 100 >= 1) {
			$Y .= "C";
			$N = $N - 100;
		}
		if ($N / 90 >= 1) {
			$Y .= "XC";
			$N = $N - 90;
		}
		if ($N / 50 >= 1) {
			$Y .= "L";
			$N = $N - 50;
		}
		if ($N / 40 >= 1) {
			$Y .= "XL";
			$N = $N - 40;
		}
		while ($N / 10 >= 1) {
			$Y .= "X";
			$N = $N - 10;
		}
		if ($N / 9 >= 1) {
			$Y .= "IX";
			$N = $N - 9;
		}
		if ($N / 5 >= 1) {
			$Y .= "V";
			$N = $N - 5;
		}
		if ($N / 4 >= 1) {
			$Y .= "IV";
			$N = $N - 4;
		}
		while ($N >= 1) {
			$Y .= "I";
			$N = $N - 1;
		}
		return $Y;
	}


	public static function UltimoDiaDoMes($mes, $ano)
	{

		return date("d", mktime(0, 0, 0, ($mes + 1), 0, $ano));
	}

	public static function fileNameClean($var)
	{

		return preg_replace('/[^A-Za-z0-9 _ .-]/', '', $var);
	}

	public static function removeAcentos($var)
	{

		$ant = $var;

		// Variavel recebendo a string já fazendo as substituições

		$var = @preg_replace("/[ÁÀÂÃÂ]/", "A", $var);

		$var = @preg_replace("/[áàâãª]/", "a", $var);

		$var = @preg_replace("/[ÉÈÊ]/", "E", $var);

		$var = @preg_replace("/[éèê]/", "e", $var);

		$var = @preg_replace("/[ÓÒÔÕ]/", "O", $var);

		$var = @preg_replace("/[óòôõº]/", "o", $var);

		$var = @preg_replace("/[ÚÙÛ]/", "U", $var);

		$var = @preg_replace("/[úùû]/", "u", $var);

		$var = str_replace("Ç", "C", $var);

		$var = str_replace("ç", "c", $var);

		return $var;
	}

	static function comboBoxJqueryComplete($nomeCombo, $mostraInclude = true)
	{

		$script = "";
		$flt = ' 
		
		<script>
		   window.dhx_globalImgPath = "javascript/htmlxcombo/imgs/";
		</script>
		
          <link rel="STYLESHEET" type="text/css" href="javascript/htmlxcombo/dhtmlxcombo.css">
          <script  src="javascript/htmlxcombo/dhtmlxcommon.js"></script>
          <script  src="javascript/htmlxcombo/dhtmlxcombo.js"></script>';

		if (!$mostraInclude)
			$flt = '';

		$script .= $flt . '  <script>
		
		        var combo = dhtmlXComboFromSelect("' . $nomeCombo . '");
				combo.enableFilteringMode(true);
				
				function htmlxcombo_refilt(){
				
				  var combo = dhtmlXComboFromSelect("' . $nomeCombo . '");
				  combo.enableFilteringMode(true);
				
				}
				
			</script>	
				';
		return $script;
	}



	// Soma períodos de hora e dá um total. -> Retorna no formato HH:Mi
	static function soma_hora(array $times)
	{

		$seconds = 0;

		foreach ($times as $time) {
			list($g, $i) = explode(':', $time);
			$seconds += $g * 3600;
			$seconds += $i * 60;
			//$seconds += $s;
		}

		$hours = floor($seconds / 3600);
		$seconds -= $hours * 3600;
		$minutes = floor($seconds / 60);
		$seconds -= $minutes * 60;

		return str_pad($hours, 2, '0', STR_PAD_LEFT) . ":" . str_pad($minutes, 2, '0', STR_PAD_LEFT);
	}

	public static function getSum($lista, $propriedade)
	{

		$dbl = 0;

		for ($y = 0; $y < count($lista); $y++) {

			$item = (array)$lista[$y];

			if (is_null($item[$propriedade]))
				continue;



			$dbl += 	$item[$propriedade];
		}

		return $dbl;
	}

	public static function dia_da_semana($dia)
	{

		$dias_semana = array(
			'Domingo', 'Segunda-Feira', 'Terça-Feira',
			'Quarta-Feira', 'Quinta-Feira', 'Sexta-Feira', 'Sábado'
		);

		$dt = new DateTime($dia);

		return $dias_semana[$dt->format("w")];
	}

	static function time_to_seconds($origtime)
	{

		if ($origtime == "")
			return 0;

		//if negative then we remove the dash
		if (substr($origtime, 0, 1) == '-')
			$time = str_replace('-', '', $origtime);
		else
			$time = $origtime;

		//do our math
		$temp = explode(':', $time);

		$sec1 = 0; //$temp[ 2 ];//seconds
		$sec2 = $temp[1] * 60; //minutes
		$sec3 = $temp[0] * 3600; //hours
		$total = $sec1 + $sec2 + $sec3;

		//if the original time passed in was negative, then we need to make sure our seconds are negative
		if (substr($origtime, 0, 1) == '-')
			$total = $total - ($total * 2);

		return $total;
	}



	public static function enviarEmail(
		$to,
		$titulo,
		$msg,
		$nome = "Gondola Segura",
		$email = "atendimento@gondolasegura.com.br"
	) {

		// detinatário do email
		$to = $to;
		// assunto 
		$subj = $titulo;
		// a mensagem do email


		// construção do cabecalho
		$headers = "MIME-Version: 1.0\n";
		$headers .= "Content-Type: text/html; charset='ISO-8859-1'\n";
		$headers .= "From: " . $nome . " <" . $email . ">\n";
		$headers .= "Return-Path: <$email>\n";
		$headers .= "Reply-to: $nome <$email>\n";
		$headers .= "X-Priority: 1\n";

		/* Novo bloco de código que permite enviar email autenticado */
		$path = realpath('../'); //Voltando para a pasta /www
		//realpath("../../");
		$windows = true;

		//Testa se o sistema operacional é linux ou windows - Se for linux joga as configurações da ufc.
		if (stristr(PHP_OS, 'WIN')) {
			$windows = true;
		} else {
			$windows = false;
			$sep = "/";
		}
		$sep = DIRECTORY_SEPARATOR;
		//Include na biblioteca de email..

		if (!file_exists($path . $sep . "libsmtp" . $sep . "controllare_email.php")) {
			die("Não foi localizado o arquivo " . $path . $sep . "libsmtp" . $sep . "controllare_email.php");
		}

		require_once($path . $sep . "libsmtp" . $sep . "controllare_email.php");

		//mail($to, $titulo, $msg, $headers);

		//return enviar_email::enviar($to, $subj, $msg, $nome);
	}




	static function recebe_html($url_origem, $arquivo_destino)
	{

		//dl("php_curl.dll");

		$minha_curl = curl_init($url_origem);

		curl_setopt($minha_curl, CURLOPT_URL, $url_origem);
		//$fs_arquivo = fopen ($arquivo_destino, "w");
		//curl_setopt ($minha_curl, CURLOPT_FILE, $fs_arquivo);
		curl_setopt($minha_curl, CURLOPT_HEADER, 0);

		curl_setopt($minha_curl, CURLOPT_TIMEOUT, (9 * 60 * 60));
		curl_setopt($minha_curl, CURLOPT_CONNECTTIMEOUT, 0);
		curl_setopt($minha_curl, CURLOPT_FOLLOWLOCATION, 1); // allow redirects
		curl_setopt($minha_curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($minha_curl, CURLOPT_FRESH_CONNECT, 1);


		//print_r( $minha_curl );die("<--");
		$result = curl_exec($minha_curl);
		if ($result  === false) {
			echo ("Erro:" . curl_error($minha_curl));
		}
		//echo ("-->". $result  );
		curl_close($minha_curl);
		//fclose ($fs_arquivo);

		return $result;
	}


	public static function excluir(&$model, $obj, $id,  $message = "Não é possível excluir, pois existe(m) registro(s) associado(s)!")
	{
		try {
			$obj->deletereg($id);

			return true;
		} catch (exception $exp) {
			if (strpos($exp->getMessage(), "fk")) {
				$_SESSION["st_Mensagem"] = $message;
				return false;
			}
		}
		return false;
	}


	public static function request($nome, $trataInject = 0)
	{
		if (
			self::getGET($nome) != ""
		) {

			return self::anti_sql_injection(self::getGET($nome));
		} else {
			if ($trataInject)
				return self::anti_sql_injection(self::getPOST($nome));
			else
				return self::getPOST($nome);
		}
	}


	public static function pintaNota($nota, $estilo = "")
	{


		if ($nota < 0) {
			return "<b style='color:#A0A19D;'>N/A</b>";
		}



		if ($nota <= 8)
			return "<b style='color:red;" . $estilo . "'>" . self::NVL(self::numeroTela($nota, 1), " 0") . "</b>";

		//&& $nota > 0
		if ($nota > 8)
			return "<b style='color:blue" . $estilo . "'>" . self::numeroTela($nota, 1) . "</b>";
	}




	public static function anti_sql_injection($str)
	{
		return $str;

		$str = get_magic_quotes_gpc() ? stripslashes($str) : $str;

		//$str = function_exists('pg_real_escape_string') ? pg_real_escape_string($str) : pg_escape_string($str);

		return self::anti_injection($str);
	}

	public static function anti_injection($sql)
	{
		// remove palavras que contenham sintaxe sql
		//$sql = preg_replace(sql_regcase("/(from|select|insert|where|drop table|show tables|#|\*|--|\\\\)/"),"",$sql);
		$sql = trim($sql); //limpa espaços vazio
		$sql = strip_tags($sql); //tira tags html e php
		//$sql = addslashes($sql);//Adiciona barras invertidas a uma string

		$sql = str_replace("'", "", $sql);
		$sql = str_replace('"', "", $sql);

		return $sql;
	}

	public static function testaInteiro(&$num)
	{

		if ($num != "" && !is_numeric($num)) {
			$num = 0;
		}
	}


	//Exibe uma mensagem e executa um comando
	public static function  Alert($mensagem = "", $comando = "")
	{
		echo "<script>";
		if ($mensagem != "")
			echo "alert('$mensagem');";

		if (!empty($comando))
			echo "$comando;";

		echo "</script>";
	}

	public static function dataMySql($valor)
	{
		if ($valor == "")
			return "";

		$arr = explode("/", $valor);

		return $arr[2] . "-" . $arr[1] . "-" . $arr[0];
	}
	public static function MySqlToOut($valor, $semhora = false)
	{

		if ($valor == "")
			return "";

		$ar = explode(" ", $valor);
		$arr = explode("-", $ar[0]);

		$hora = "";
		if (!$semhora)
			$hora = " " . $ar[1];

		return $arr[2] . "/" . $arr[1] . "/" . $arr[0] . $hora;
	}
	public static function dataPg($valor)
	{
		if ($valor == "")
			return "";

		$arr = explode("/", $valor);

		if (count($arr) < 3)
			return "";


		return trim(@$arr[2]) . "-" . trim(@$arr[1]) . "-" . trim(@$arr[0]);
	}
	public static function PgToOut($valor, $semhora = false)
	{

		if ($valor == "")
			return "";


		$valor = str_replace("-", "/", $valor);

		$ar = explode(" ", $valor);
		$arr = explode("/", $ar[0]);

		if (count($arr) < 3)
			return "";

		$hora = "";
		if (!$semhora)
			$hora = " " . @$ar[1];

		return $arr[2] . "/" . $arr[1] . "/" . $arr[0] . $hora;
	}

	public static function BDDateToOut($valor, $semhora =false)
	{
		
		if ($valor == "")
			return "";
	
		
		$valor = str_replace("-","/",$valor);
		
		$ar = explode(" ",$valor);
		$arr = explode("/",$ar[0]);
		
		if ( count($arr) < 3)
			return "";
		
		$hora = "";
		if (! $semhora)
			$hora = " " . @$ar[1];
		$val = $arr[2]."/".$arr[1]."/".$arr[0].$hora;
		return str_replace("00:00:00","",$val);
		
	}
	
	public static function HourToOut($valor, $semhora =false)
	{

		if ($valor == "")
			return "";


		$valor = str_replace("-", "/", $valor);

		$ar = explode(" ", $valor);
		$arr = explode("/", $ar[0]);

		if (count($arr) < 3)
			return "";

		$hora = "";
		//if (! $semhora)
		$hora = "" . @$ar[1];

		return $hora;
		//return $arr[2]."/".$arr[1]."/".$arr[0].$hora;

	}

	public static  function numeroBanco($valor)
	{
		$val = str_replace("R$", "", $valor);
		$val = str_replace(".", "", $val);
		$val = str_replace(",", ".", $val);

		$val  = round($val, 2);

		$val = str_replace(".00", "", $val);
		for ($i = 1; $i <= 9; $i++) {
			$val = str_replace("." . $i . "0", "." . $i, $val);
		}

		return $val;
	}
	public static  function numeroTela($valor, $removeZeros = 1)
	{
		if ($valor == null || $valor == "")
			return "";

		$val = number_format($valor, 2, ",", ".");

		// $val = str_replace(".",",",$valor);
		if ($removeZeros) {
			$val = str_replace(",00", "", $val);
			for ($i = 1; $i <= 9; $i++) {
				$val = str_replace("," . $i . "0", "," . $i, $val);
			}
		}
		if ($removeZeros == 1)
			$val = str_replace(".", "", $val);


		return $val;
	}


	public static function left($string, $count)
	{
		$string = substr($string, 0, $count);
		return $string;
	}

	public static function right($string, $count)
	{
		$string = substr($string, -$count, $count);
		return $string;
	}

	public static function mostraImagem($nomeImg, $onclick = "", $title = "", $ancora = "")
	{

		$st = '<a href="#' . $ancora . '" onClick="' . $onclick . '">' .
			'<img src="' . '/www/assets/images/' . $nomeImg . '"
				title="' . $title . '" style="cursor:pointer;"></a>';

		if ($ancora != "#")
			$st .= "<a name='" . $ancora . "'></a>";


		return $st;
	}

	public static function populaCombo($value, $texto, $sel = "")
	{

		$selec = $value == $sel  ? " selected " : "";

		echo "<option $selec value=\"$value\">" . self::acento_para_html($texto) . "</option>";
	}
	public static function populaCombo2($value, $texto, $sel = "")
	{

		$selec = $value == $sel  ? " selected " : "";

		return "<option $selec value=\"$value\">" . $texto . "</option>";
	}

	public static function ToMoney($valor)
	{

		return number_format($valor, 2, ',', '');
	}

	public  static function getGET($nome)
	{
		if (@$_GET[$nome] != null && @$_GET[$nome]  != "") {
			return $_GET[$nome];
		} else {
			return "";
		}
	}


	public static function getPOST($nome)
	{

		if (@$_POST[$nome] != null &&  @$_POST[$nome] != "") {
			return $_POST[$nome];
		} else {
			return "";
		}
	}


	public static function LimpaSessao($nome)
	{
		if (!empty($_SESSION[$nome])) {
			unset($_SESSION[$nome]);
		}
	}


	public static function devolve_acentos($texto)
	{
		$array1 = array(
			"Ã¡", "Ã£", "Ã©", "Ãª", "Ã­", "Ã³", "Ãµ", "Ã§", "Ã?", "Ã", "Ã", "Ã", "Ã?", "Ã", "Ã", "Ã", "Ã¢", "Ãº", "Ã®", "Ã´", "Ã»", "Âº", "Âª", "Ã" . chr(160), "Ã¨", "Ã¬", "Ã²", "Ã¹", "Ã±", "Ã¤", "Ã«", "Ã¯", "Ã¶", "Ã¼", "Ã?", "Ã", "Ã", "Ã?", "Ã", "Ã", "Ã", "Ã", "Ã", "Ã", "Ã", "Ã", "Ã", "Ã", "Ã", "Ã?", "Ã", "Ã", "Â°"
		);

		$array2 = array(
			"á", "ã", "é", "ê", "í", "ó", "õ", "ç", "Á", "Ã", "É", "Ê", "Í", "Ó", "Õ", "Ç", "â", "ú", "î", "ô", "û", "º", "ª", "à", "è", "ì", "ò", "ù", "ñ", "ä", "ë", "ï", "ö", "ü", "Á", "À", "È", "Í", "Ì", "Ò", "Ú", "Ù", "Â", "Î", "Ô", "Û", "Ñ", "Ä", "Ë", "Ï", "Ö", "Ü", "º"
		);

		$texto =  str_replace($array1, $array2, $texto);
		return $texto;
	}

	//Faz a mesma coisa que o isnull do sql server ou o NVL do oracle	
	public static function NVL($valor, $retorno)
	{


		if (is_null($valor) ||   $valor == "") {
			return $retorno;
		} else {
			return $valor;
		}
	}

	public static function lerArquivo($arquivo)
	{
		//$arquivo = "textos/introducao.txt";

		$func = fopen($arquivo, "r");
		//die ( $arquivo ) ; 
		$cont = fread($func, filesize($arquivo));
		return  $cont;
	}

	public static function flash($arquivo, $largura, $altura)
	{
		echo '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" 
		<codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=7,0,19,0" width="' . $largura . '" height="' . $altura . '" title="arquivo">	
		  <param name="movie" value="' . $arquivo . '" />
		  <param name="quality" value="high" />
		  <embed src="' . $arquivo . '" quality="high" pluginspage="http://www.macromedia.com/go/getflashplayer" type="application/x-shockwave-flash" width="' . $largura . '" height="' . $altura . '"></embed>
		</object>';
	}

	public  static function iif($condicao, $valif, $valelse)
	{
		return ($condicao ? $valif : $valelse);
	}


	public  static function ischecked($condicao)
	{
		return self::iif($condicao, "checked", "");
	}


	public static function LimitaTamanhoString($sString, $tam)
	{
		$tam2 = strlen($sString);
		if ($tam2 > $tam) {
			return substr($sString, 0, $tam) . " ...";
		} else {
			return $sString;
		}
	}

	public static function escreveArquivo($nome, $conteudo)
	{

		$ourFileHandle = fopen($nome, "w") or die("can't open file");

		chmod($nome, 0777);
		fputs($ourFileHandle, $conteudo);
		fclose($ourFileHandle);
	}

	//Vai manter um arquivo txt como contador de visitas  
	public static function contadorTxt($pasta, $arquivo)
	{
		$BOL = file_exists(realpath($pasta) . trim("/ ") . $arquivo);
		//  echo realpath($pasta).trim("/ ").$arquivo;
		// echo  $BOL;

		if ($BOL) {

			$linha = file($pasta . $arquivo); //define arquivo onde ficara gravado os acessos
			clearstatcache();                       //<-+          
			$visitas = $linha[0];          //  |
			$visitas = $visitas + 1;

			$ourFileName = $pasta . $arquivo;
			//echo $ourFileName;
			chmod($ourFileName, 0777);
			$ourFileHandle = fopen($ourFileName, "w") or die("can't open file");
			fputs($ourFileHandle, $visitas);
			fclose($ourFileHandle);                  //  |
			return number_format($visitas, 0, '', '.');               //  |

		} else {
			$ourFileName = $pasta . $arquivo;
			$ourFileHandle = fopen($ourFileName, 'w') or die("can't open file");
			fputs($ourFileHandle, 2);
			fclose($ourFileHandle);
			return 2;
		}
	}

	//Se for menor que 10 ele põe um zero antes do número..
	public static function ZeroAntes($num)
	{
		if ($num < 10) {
			return "0" . $num;
		} else {
			return $num;
		}
	}

	public static function Arredondar($actual_value)
	{
		$temp1 = $actual_value * 2;
		$temp2 = round($temp1, 1);  // 7
		$half_round = $temp2 / 2;   // 3.5
		$half_round = round($half_round);
		return $half_round;
	}

	public static function getCurrentBDdate()
	{
		return date("Y-m-d H:i:s");
	}

	public static function getPtDate()
	{
		return date("d/m/Y");
	}


	public static function getCurrentPTdate()
	{
		return date("d/m/Y H:i:s");
	}

	public static function carregaComboAr(&$obj, $valor, $texto, $total, $sel = "", $addSelecione = 0)
	{
		if ($addSelecione) {
			self::populaCombo("", "-- SELECIONE --", "");
		}
		//self::Alert($total."----");
		for ($z = 0; $z < $total; $z++) {
			$it = &$obj->items[$z];

			self::populaCombo($it->get_data($valor), $it->get_data($texto), $sel);
		}
	}


	public static function CarregaComboArray(&$ar, $Campo1, $Campo2,  $sel, $select = false)
	{
		if ($select)
			self::populaCombo("", " -- SELECIONE -- ", "");

		for ($i = 0; $i < count($ar); $i++) {
			$arr = $ar[$i];

			self::populaCombo($arr[$Campo1], $arr[$Campo2], $sel);
		}
	}


	public static function arrayTipoConta()
	{
		$arr = array();

		$arr[0] = array("id" => "1", "desc" => "Corrente");
		$arr[1] = array("id" => "2", "desc" => "Poupança");

		return $arr;
	}


	public static function getPaginaParametros()
	{


		$parametros = "";
		foreach ($_POST as $key => $value) {

			$parametros = self::AdicionaStr($parametros, $key . "=" . $value, "&");
		}

		foreach ($_GET as $key => $value) {

			$parametros = self::AdicionaStr($parametros, $key . "=" . $value, "&");
		}

		return $parametros;
	}


	public static function  CarregaComboOptGroup(&$RCset, $Campo1, $Campo2, $campoGroup, $comp)
	{
		$temp = "";
		$group = "";
		$cont = "";
		$group = "";
		$cont = 0;
		if (isset($RCset)) {
			// Método alterado para poder receber mais de um campo no valor texto
			//(separado por |)
			$arrayTexto = explode("|", $Campo2);


			for ($i = 0; $i < count($RCset); $i++) {
				$ar = $RCset[$i];

				if ($group != "" && $ar[$campoGroup] != $group) {
					echo "\n" . "</optgroup> \n";
					$cont = 0;
				}

				$group =  $ar[$campoGroup];

				if ($cont == 0) {
					echo "\n " . "<optgroup label='" . str_replace("'", "\'", $group) . "'>";
				}
				$cont++;
				if ($ar[$Campo1] == $comp) {
					$temp = "  selected";
				}
				echo '<option value="' . $ar[$Campo1] . '" ' . $temp . ">\n";
				for ($z = 0; $z < count($arrayTexto); $z++) {
					if (count($arrayTexto) > 1) {
						if ($arrayTexto[$z] != "") {
							echo str_replace("'", "\'", $ar[$Campo2]) . " - ";
						}
					} else {
						echo str_replace("'", "\'", $ar[$Campo2]);
					}
				}
				echo  "</option>";
				$temp = "";
			}

			if ($cont > 0) {
				echo "\n</optgroup>";
			}
		}
	}


	public static function mensagemCadastro($tam = 95, $strvar = "st_Mensagem", $classe = "dvMensagem")
	{
		$msg = "";
		//die("oi");
		try {
			$msg =  @$_SESSION[$strvar];
			$_SESSION[$strvar] = "";

			if ($msg != "") {
				echo '<div style="test-align:center;width:' . $tam . '%" class="' . $classe . '">' .
					$msg . '</div>';
			}
		} catch (Exception $ex) {
		}
	}

	public static function limitaQuebra($string, $num)
	{
		if (substr_count($string, "\n") > $num) {
			//strpos($string,"\n",1)
			return ucfirst(str_replace("\n", "&nbsp;", $string));
		}
		return ucfirst(str_replace("\n", "<br>", $string));
	}

	public static function getParam($parametros, $key)
	{

		try {
			$valor = @$parametros[$key];
			//echo ($valor);
			return $valor;
		} catch (Exception $ex) {
		}
		return "";
	}


	//Retorna a página atual excluindo um valor qualquer que esteja
	// na queryString..
	public static function paginaAtual($parametros, $keycampo = "", $retornocomum = false)
	{

		$pag = $_SERVER['SCRIPT_NAME'];

		$sep = "?";

		$arr = explode(",", $keycampo);

		foreach ($_GET as $key => $value) {
			if (strpos(" " . $key, '"'))
				continue;


			if (strpos(" " . $key, "'"))
				continue;

			if (count($arr) == 0 || !self::existeInArray($arr, $key)) {
				$pag .= $sep . $key . '=' . self::anti_sql_injection($value);
				$sep = "&";
			}
		}
		foreach ($_POST as $key => $value) {
			if (strpos(" " . $key, '"'))
				continue;


			if (strpos(" " . $key, "'"))
				continue;


			if (count($arr) == 0 || !self::existeInArray($arr, $key)) {
				$pag .= $sep . $key . '=' . self::anti_sql_injection($value);
				$sep = "&";
			}
		}
		//return $pag;


		$pag = "";
		$sep = "/";
		if ($keycampo == "")
			$keycampo = "APPLICATION_ENV";
		else
			$keycampo .= ",APPLICATION_ENV";

		$arr = explode(",", $keycampo);
		$arr2 = array();

		$cont = -1;
		if (is_array($parametros)) {
			foreach ($parametros as $key => $value) {
				if ($key == "PHPSESSID")
					continue;


				if (self::getPOST($key) != "") {
					$parametros[$key] = self::request($key);
				}
			}
		}


		if (is_array($parametros)) {
			//print_r( $parametros );
			foreach ($parametros as $key => $value) {

				if ($key == "PHPSESSID")
					continue;

				$cont++;
				if ($cont <= 2)
					continue;

				if ((count($arr) == 0 || !self::existeInArray($arr, $key))
					&&  self::indexParam($key, self::NVL($pag, " ")) < 0
				) {
					$pag .= $sep . $key . '/' . self::anti_sql_injection($value);
					$sep = "/";
				}
				$arrr = array_merge($arr2, array($key => $value));
				$arr2 = $arrr;
			}
		}

		//echo $pag."<br>";
		if (!$retornocomum)
			return $arr2;

		return $pag;
	}


	public static function  indexParam($valor, $pag)
	{
		$var = str_replace("?", "&", $pag);
		$var = str_replace("=", "&", $var);
		$arr = explode("&", $var);

		if (self::existeInArray($arr, $valor))
			return 1;
		else
			return -1;
	}


	public static function arrayToString($arr, $propriedade, $sep = ",", $ehNum = false, $idd = 0, $testaVazio = false, $format = false)
	{
		$str = "";
		for ($i = 0; $i < count($arr); $i++) {
			$item = (array)$arr[$i];

			$vv = $item[$propriedade];

			if ($testaVazio) {
				if (trim($vv) == "")
					continue;
			}

			if ($ehNum)
				$vv = self::numeroTela($vv, $idd);

			if ($format) {
				$vv = str_replace("'", "",	$vv);
				$vv = str_replace($sep, "+",	$vv);
			}

			$str = self::AdicionaStr($str, $vv, $sep);
		}

		return $str;
	}

	public static function arrayToString2($arr, $sep = ",", $ehNum = false, $idd = 0, $testaVazio = false)
	{
		$str = "";
		for ($i = 0; $i < count($arr); $i++) {
			$vv = $arr[$i];

			if ($testaVazio) {
				if (trim($vv) == "")
					continue;
			}

			if ($ehNum)
				$vv = self::numeroTela($vv, $idd);

			$str = self::AdicionaStr($str, $vv, $sep);
		}

		return $str;
	}


	public static function diasemana($data)
	{

		$ar = explode("-", $data);

		$ano = $ar[0];
		$mes =  $ar[1];
		$dia =  $ar[2];

		$diasemana = date("w", mktime(0, 0, 0, $mes, $dia, $ano));

		switch ($diasemana) {
			case "0":
				$diasemana = "Domingo";
				break;
			case "1":
				$diasemana = "Segunda-Feira";
				break;
			case "2":
				$diasemana = "Terça-Feira";
				break;
			case "3":
				$diasemana = "Quarta-Feira";
				break;
			case "4":
				$diasemana = "Quinta-Feira";
				break;
			case "5":
				$diasemana = "Sexta-Feira";
				break;
			case "6":
				$diasemana = "Sábado";
				break;
		}

		return $diasemana;
	}


	public static function mes_nome($nmes = -1)
	{
		$meses = array(
			'',
			'Janeiro',
			'Fevereiro',
			'Março',
			'Abril',
			'Maio',
			'Junho',
			'Julho',
			'Agosto',
			'Setembro',
			'Outubro',
			'Novembro',
			'Dezembro', 'Dezembro 13º'
		);
		if ($nmes > -1)
			return $meses[(int)$nmes];
		return $meses;
	}


	public static function arrayToString3($arr, $propriedade, $sep = ",", $ehNum = false, $idd = 0, $testaVazio = false)
	{
		$str = "";
		for ($i = 0; $i < count($arr); $i++) {
			$item = (array)$arr[$i];

			$vv = $item[$propriedade];

			if ($testaVazio) {
				if (trim($vv) == "")
					continue;
			}

			if ($ehNum)
				$vv = self::numeroTela($vv, $idd);

			$str = self::AdicionaStr($str, $vv, $sep);
		}

		return $str;
	}

	static function acento_para_html($umarray)
	{
		$comacento = array('Á', 'á', 'Â', 'â', 'À', 'à', 'Ã', 'ã', 'É', 'é', 'Ê', 'ê', 'È', 'è', 'Ó', 'ó', 'Ô', 'ô', 'Ò', 'ò', 'Õ', 'õ', 'Í', 'í', 'Î', 'î', 'Ì', 'ì', 'Ú', 'ú', 'Û', 'û', 'Ù', 'ù', 'Ç', 'ç',);
		$acentohtml   = array('&Aacute;', '&aacute;', '&Acirc;', '&acirc;', '&Agrave;', '&agrave;', '&Atilde;', '&atilde;', '&Eacute;', '&eacute;', '&Ecirc;', '&ecirc;', '&Egrave;', '&egrave;', '&Oacute;', '&oacute;', '&Ocirc;', '&ocirc;', '&Ograve;', '&ograve;', '&Otilde;', '&otilde;', '&Iacute;', '&iacute;', '&Icirc;', '&icirc;', '&Igrave;', '&igrave;', '&Uacute;', '&uacute;', '&Ucirc;', '&ucirc;', '&Ugrave;', '&ugrave;', '&Ccedil;', '&ccedil;');
		$umarray  = str_replace($comacento, $acentohtml, $umarray);


		$umarray = utf8_encode($umarray);

		return $umarray;
	}


	public static function  existeInArray($arr, $valor)
	{
		for ($i = 0; $i < count($arr); $i++) {
			if ($valor == $arr[$i])
				return 1;
		}
		return 0;
	}


	public static function  AdicionaStr($Str, $valor, $sep = ",")
	{
		$val = $Str;
		if (empty($val)) {
			return  $valor;
		}

		if (!self::existeInArray(explode($sep, $Str), $valor)) {

			return  $Str . $sep . $valor;
		} else {
			return  $Str;
		}
	}
	/*
Function AdicionaStrSep(Str,valor, Sep)
   Dim val
       val = Str
	   If is_vazio(val) Then
           AdicionaStrSep = valor
		   Exit function
	   End If 

    AdicionaStrSep = Str &Sep & valor
 
End Function
	*/
	public static function RemoveStr($Str, $valor)
	{
		$arr = explode(",", $Str);

		$novoValor = "";

		for ($z = 0; $z < count($arr); $z++) {
			if (trim($arr[$z]) != trim($valor)) {
				$novoValor = self::AdicionaStr($novoValor, $arr[$z]);
			}
		}

		return  $novoValor;
	}



	public static function SetaRsetPaginacao(
		$selQtdeRegistro,
		$selPagina,
		$totalRegistro,
		&$inicio,
		&$fim
	) {


		if (!is_numeric($selQtdeRegistro))
			$selQtdeRegistro = 0;


		if (!is_numeric($totalRegistro))
			$totalRegistro = 0;


		$pageCount =  @($totalRegistro / $selQtdeRegistro);

		if ($pageCount < 1)
			$pageCount = 1;

		if ($pageCount > round($pageCount)) {
			$pageCount++;
		} else {
			$pageCount = round($pageCount);
		}

		$pageCount = (int)$pageCount;


		//echo  $selPagina . "-- ".$pageCount;

		if ($selPagina > (int)$pageCount)
			$selPagina = (int)$pageCount;

		//die ( $selPagina );

		$inicio = $selQtdeRegistro * ($selPagina - 1);
		$fim = $inicio + $selQtdeRegistro;


		if ($fim > $totalRegistro)
			@($fim = $totalRegistro);

		//die($inicio."----".$selQtdeRegistro."-".$selPagina."-".$fim."-".$totalRegistro);

		return $inicio . "_" . $fim;
	}


	public static function sendPost($url, $data)
	{


		// use key 'http' even if you send the request to https://...
		$options = array(
			'http' => array(
				'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
				'method'  => 'POST',
				'content' => http_build_query($data)
			)
		);
		$context  = stream_context_create($options);
		$result = file_get_contents($url, false, $context);

		return $result;
	}

	
	static function get_web_page($url, $timeout = 120)
	{
		$options = array(
			CURLOPT_RETURNTRANSFER => true,   // return web page
			CURLOPT_HEADER         => false,  // don't return headers
			CURLOPT_FOLLOWLOCATION => true,   // follow redirects
			CURLOPT_MAXREDIRS      => 20,     // stop after 10 redirects
			CURLOPT_ENCODING       => "",     // handle compressed
			CURLOPT_USERAGENT      => "test", // name of client
			CURLOPT_AUTOREFERER    => true,   // set referrer on redirect
			CURLOPT_CONNECTTIMEOUT => $timeout,    // time-out on connect
			CURLOPT_TIMEOUT        => $timeout,    // time-out on response
		);

		$ch = curl_init($url);
		curl_setopt_array($ch, $options);

		$content  = curl_exec($ch);

		curl_close($ch);

		return $content;
	}
}
