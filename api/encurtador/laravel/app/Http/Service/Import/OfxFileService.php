<?php

namespace App\Http\Service\Import;

use App\Http\Service\UtilService;
use stdClass;

class OfxFileService 
{
    
    function import_a($xmlFile){
        
         $xml = new \XMLReader();
         $xml->open($xmlFile);
         
         
         try {
                        while ($xml->read()) {
                            if ($xml->nodeType == \XMLReader::ELEMENT) {
                                //assuming the values you're looking for are for each "item" element as an example
                                if ($xml->name == 'BANKMSGSRSV1') {
                                    $variable[++$counter] = new \stdClass();
                                    $variable[$counter]->thevalueyouwanttoget = '';
                                }
                                if ($xml->name == 'thevalueyouwanttoget') {
                                    $variable[$counter]->thevalueyouwanttoget = $xml->readString();
                                }
                            }
                        }
                    } catch (Exception $e) {
                        
                    } finally {
                    $xml->close();
                    }
        
    }


    function testXMLFile($xmlFile ){

      $res = new stdClass();
      $res->error = false;
      $res->msg = "";

      libxml_use_internal_errors(true);

            $doc = simplexml_load_file($xmlFile);

            if (!$doc) {
                $errors = libxml_get_errors();
                //$res->msg = $errors;
                $res->error = true;
                $msg = "";

                foreach( $errors as $error ){
                    $msg .= "\n". $error->message; 
                }
                $res->msg = UtilService::left(  $msg , 200) ;
                libxml_clear_errors();
            }

            return  $res;

    }
    
    function import($xmlFile, $options){
        
         $oxml =  simplexml_load_file( $xmlFile );
         $saida = array();
         
         if ( !is_null($oxml)){
              if ( !is_null( $oxml->BANKMSGSRSV1->STMTTRNRS->STMTRS->BANKTRANLIST ) ){
                  
                  $transacoes = $oxml->BANKMSGSRSV1->STMTTRNRS->STMTRS->BANKTRANLIST->STMTTRN;
                  
                  for ( $i = 0; $i < count($transacoes); $i++ ){
                      $item = $transacoes[ $i ];
                      
                      
                      $reg = new \App\FinExtrato();
                      $valor = floatval( $item->TRNAMT );
                      
                      $reg->origem = "import_ofx";
                      
                      if ( $valor > 0 ){                           
                           $reg->tipo = \App\Http\Dao\ConfigDao::executeScalar("select codigo from fin_tipo_lancamento where codigo in ( 'R', 'D') and fator > 0 ");
                       }
                       
                       if ( $valor < 0 ){ 
                           $reg->tipo = \App\Http\Dao\ConfigDao::executeScalar("select codigo from fin_tipo_lancamento where codigo in ( 'R', 'D') and fator < 0 ");
                       }
                     
                       if ( $reg->tipo == ""){
                                $reg->tipo = "R";
                                if ( $valor < 0 ){
                                    $reg->tipo = "D";
                                }
                       }
                      
                      
                      $reg->valor = abs( $valor );
                      $reg->meta_dados = json_encode($item);
                      $reg->titulo = $item->MEMO;
                      $reg->data = substr($item->DTPOSTED, 0, 4)."-".substr($item->DTPOSTED, 4, 2)."-".substr($item->DTPOSTED, 6, 2);
                      
                      \App\Http\Dao\UsuarioDao::addUsuario($reg, "fin_extrato");
                      
                      foreach ($options as $key => $value) {
                            $reg->$key = $value;
                      }
                      
                      $reg->fator = 1;
                      if ( $valor < 0 ){
                          
                          $reg->fator = -1;
                      }
                      
                      $reg->save();
                      $reg->cad_tipo = 1;
                      
                      $saida[count($saida)] = $reg->id;
                      
                      /*
                      $reg->valor_pago = abs( $valor );
                      $reg->titulo = $item->MEMO;
                      $reg->data = substr($item->DTPOSTED, 0, 4)."-".substr($item->DTPOSTED, 4, 2)."-".substr($item->DTPOSTED, 6, 2);
                      $reg->data_primeira_parcela = $reg->data;
                      $reg->id_origem = $item->FITID;
                      
                      if ( strlen($item->FITID) > 50 ){
                          $reg->id_origem = substr($item->FITID, 0 , 49);
                      }
                      
                      
                      $reg->ano = substr($item->DTPOSTED, 0, 4); $reg->mes = substr($item->DTPOSTED, 4, 2); $reg->dia = substr($item->DTPOSTED, 6, 2);
                      
                      $reg->obs = $item->FITID;
                      $reg->nr_parcelas = 1;
                      
                      \App\Http\Dao\UsuarioDao::addUsuario($reg, "fin_pagamento");
                      
                      
                      
                      
                      \App\Http\Dao\ConfigDao::blankToNull($reg);
                      $reg->save();
                      
                      $parcela = new \App\FinPagamentoParcela();                      
                      $parcela->id_pagamento = $reg->id;               
                      $parcela->nr_parcela = 1;           
                      $parcela->data_vencimento = $reg->data;
                      $parcela->data_pagamento = $reg->data;
                      $parcela->valor = $reg->valor;
                      $parcela->valor_pago = $reg->valor;
                      $parcela->situacao = "PAGO";
                      $parcela->save();
                       */
                  }
                  
                 // $saida = count($transacoes);
                  
                  
                  
                  //  echo("<xmp>"); print_r( $oxml->BANKMSGSRSV1->STMTTRNRS->STMTRS->BANKTRANLIST ); echo("</xmp>");
                  
              }
         }
         
        return $saida;
        
    }
    
    function correctFile($file, $final_name){
       $txt =  $this->closeTags($file);
       
       $ar = explode("<OFX>", $txt );
       $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<OFX>\n" . $ar[1];
       \App\Http\Service\UtilService::escreveArquivo($final_name, $xml );
    }


    public function closeTags($ofx=null) {
        $buffer = '';
        $source = fopen($ofx, 'r') or die("Unable to open file!");
        while(!feof($source)) {
            $line = trim(fgets($source));
            if ($line === '') continue;
            if (substr($line, -1, 1) !== '>') {
                list($tag) = explode('>', $line, 2);
                $line .= '</' . substr($tag, 1) . '>';
            }
            $buffer .= $line ."\n";
        }
        $xmlOut =   explode("<OFX>", $buffer);
        //$name = realpath(dirname($ofx)) . '/' . date('Ymd') . '.ofx';
        //$file = fopen($name, "w") or die("Unable to open file!");
        //fwrite($file, $buffer);
        //fclose($file);
        return isset($xmlOut[1])?"<OFX>".$xmlOut[1]:$buffer;
    }
    
    
    
}
