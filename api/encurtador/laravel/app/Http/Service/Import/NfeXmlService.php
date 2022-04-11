<?php

namespace App\Http\Service\Import;
use Illuminate\Support\Facades\Auth;

class NfeXmlService 
{
    
        function import($xmlFile, $options){
                           
                $user = Auth::user();
                $ids = "";
                
                    if (    mime_content_type($xmlFile) == "application/zip" ){
                         $destination = storage_path() . DIRECTORY_SEPARATOR .   "zip". $user->id_usuario;

                         if (!is_dir($destination)){
                             @mkdir($destination);
                         }

                        $files = \App\Http\Service\ZipService::extract($xmlFile, $destination);
                        
                        for ( $i = 0; $i < count($files); $i++ ){
                            $arquivo_completo = $files[$i];
                            
                            if ( $ids != ""){
                                $ids .= ",";
                            }
                            $folder = $this->getFolderFromFile($arquivo_completo);
                            $correted_file = $folder . DIRECTORY_SEPARATOR.  $user->id."_nfe_".date("YmsHis").".xml";
                            
                            $this->correctFile($arquivo_completo, $correted_file );
                            $ids .= $this->importNfeFile( $correted_file, $options );
                            
                        }
                        //print_r( $files ); die(" ");
                    } else {
                            $folder = $this->getFolderFromFile($xmlFile);
                            $correted_file = $folder . DIRECTORY_SEPARATOR. $user->id."_nfe_".date("YmsHis").".xml";
                            
                            $this->correctFile($xmlFile, $correted_file );
                            
                            $ids .= $this->importNfeFile( $xmlFile, $options );
                    }
                    
                    return $ids;
        
    
        }
        
        function findMovimentacaoByNota($nota, $id_cliente, $data ){
            
            $filtro = explode(" ", $data);
            
            
            
            $sql = "select id as res from movimentacao where id_cliente = ". $id_cliente .
                    " and data >= '". $filtro[0]." 00:00:00' " .
                    " and data <= '". $filtro[0]." 23:59:59' ".
                    " and nota_fiscal='". $nota."' order by id desc limit 0, 1 ";
            
           // die( $sql );
            
            return \App\Http\Dao\ConfigDao::executeScalar($sql);
            
        }
        function findNfeNumber($nfe){
            
            
            $attributes = "@attributes";
            $nota_fiscal = @$nfe->infNFe->$attributes->Id;
            
            if ( $nota_fiscal == ""){
                $ar = (array)$nfe->infNFe;
                
                foreach ($ar as $key => $value){
                      if ( $key == $attributes )  {
                           return $value["Id"];
                      }
                }
            }
            
            return "";
            
        }
        
        function getFolderFromFile($file){
            $pos = strrpos($file, DIRECTORY_SEPARATOR );
            return substr($file, 0 , $pos);
        }
        
         function correctFile($file, $final_name){
            $txt =  \App\Http\Service\UtilService::lerArquivo($file);
            
            if (strpos(" ". $txt, "<?xml")){
                $ar = explode("<?xml", $txt );
                $xml = "<?xml" . $ar[1];
                \App\Http\Service\UtilService::escreveArquivo($final_name, $xml );
                
            }else {
                 $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>".$txt;
                 \App\Http\Service\UtilService::escreveArquivo($final_name, $xml );
                
            }

            
         }

        
        function importNfeFile($xmlFile, $options){
            
        
         $oxml =  simplexml_load_file( $xmlFile );
         $saida = array();
         
         $nfe = $oxml;
         
         if ( ! is_null(  @$oxml->NFe ) ){
             $nfe = $oxml->NFe;
         }
         
         if ( !is_null($nfe)){
             //http://localhost:8081/teste.php
             
             
             if ( !@$nfe->infNFe ){
                 $nfe = $oxml;
             }
             
            // print_r( $nfe );die(" ");
            $emitente =  $nfe->infNFe->emit; //quem emitiu essa nota.
            $dest =  $nfe->infNFe->dest; //quem recebeu essa nota
            $transporta =  $nfe->infNFe->transp->transporta; //quem recebeu essa nota
            $valor = $nfe->infNFe->total->ICMSTot->vNF;
            $ar_data = explode("T" , $nfe->infNFe->ide->dhEmi);
            $data = $ar_data[0];
            $numero_nf = $nfe->infNFe->ide->nNF;
            
            
            
            $descricao = "";
            
            $arrayprod = array();
            $det = @$nfe->infNFe->det;
            if (is_array($det)){
                 $arrayprod = $det;
                 $descricao = $det[0]->prod->xProd;
            }else{
                $arrayprod[count($arrayprod)] = $det;
                 $descricao = @$nfe->infNFe->det->prod->xProd;
            }
            
            
            $attributes = "[@attributes]";
            $nota_fiscal = $this->findNfeNumber($nfe);
            
           
                    $reg = new \App\FinExtrato();
                    $reg->tipo = "R";
                    $reg->origem = "import_nfe";
                    
                    //$nota, $id_cliente, $data
                    $id_movimentacao = $this->findMovimentacaoByNota($nota_fiscal , $options["id_cliente"],  $data  );
                    
                    if ( $id_movimentacao != ""){
                        $reg->id_movimentacao = $id_movimentacao;
                        $reg->importar_para = "movimentacao";
                        
                        $mov = \App\Movimentacao::find($id_movimentacao);
                        $reg->importar_para = "movimentacao";
                        $reg->centro_custo_id = $mov->id_centro_custo;
                    }


                         $reg->valor = abs( $valor );
                         $reg->meta_dados = json_encode($oxml);
                         $reg->titulo = $descricao;
                         $reg->data = $data;
                         
                         $reg->emitente = "Emit: " . $emitente->xNome . " / Dest: " .$dest->xNome;

                        \App\Http\Dao\UsuarioDao::addUsuario($reg, "fin_extrato");

                        foreach ($options as $key => $value) {
                              $reg->$key = $value;
                        }
                        
                        \App\Http\Dao\ConfigDao::blankToNull($reg);

                        $reg->save();
            
                       return $reg->id;
         
         }
        return -1;
    
    
}
}
    
