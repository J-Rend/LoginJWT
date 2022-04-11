<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    
        /**
     * Returns serialized in 'json'.
     *
     * @param $data
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function jsonResponse($data, $message = '')
    {
        $response = [
            'data' => $data,
            'message' => $message
        ];

        return response()->json($response, Response::HTTP_OK);
    }

    
    
        public function campoPreenchido(\Illuminate\Http\Request $request, $nome)           
                
        {
            if ( is_null(   $request->input($nome) ) )
                  return false;
            
            
            if ( $request->input($nome)  == "" )
                  return false;
            
            
            if ( $request->input($nome)  == "null" )
                  return false;
            
            return true;
        }
    public function sendResponse($result, $message = "", $code = Response::HTTP_OK)
    {
        
        return response()->json($result, $code, array(), JSON_NUMERIC_CHECK);
        
        //return Response::json(ResponseUtil::makeResponse($message, $result), $code, [], JSON_UNESCAPED_SLASHES);
    }
    
    
    public function sendError($error, $code = 400, $suppress = 0)
    {
        if($code < 199 || $code > 500) {
            $code = 500;
        }
        if($suppress == 1){
            $code = 200;
        }

        $response = array(  
            'message' => $error
        );


        return response()->json($response, $code);
    }
    
    
        public function sendError2($error, $code = 400, $suppress = 0)
    {
        if($code < 199 || $code > 500) {
            $code = 500;
        }
        if($suppress == 1){
            $code = 200;
        }

        $response = array( 'data' => array(  
            'message' => $error )
        );


        return response()->json($response, $code);
    }
    
    
    function get_limit_sql($inicio, $pagesize){
         return " limit ". $inicio.", ". $pagesize;
    }
    	
    function SetaRsetPaginacao($selQtdeRegistro, $selPagina,$totalRegistro,
					  &$inicio, &$fim)
	 {
						
						
						if ( ! is_numeric($selQtdeRegistro))
						  $selQtdeRegistro = 0;
						
						
						if ( ! is_numeric($totalRegistro))
						  $totalRegistro = 0;
						
						
						$pageCount =  @($totalRegistro / $selQtdeRegistro);
						
						if ($pageCount < 1)
							$pageCount = 1; 
						
						if ($pageCount > round($pageCount))
							{    $pageCount++;}
						else 
							{  $pageCount = round($pageCount); }
						
						$pageCount = (int)$pageCount;
						
						
						//echo  $selPagina . "-- ".$pageCount;
						
						if ( $selPagina > (int)$pageCount)
							$selPagina = (int)$pageCount;
						
						//die ( $selPagina );
						
						 $inicio = $selQtdeRegistro * ($selPagina -1);
						 $fim = $inicio + $selQtdeRegistro;
						// die ( $inicio . " -- AAAAAAAAA  ". $selPagina );
						 
						 if ($fim > $totalRegistro)
							 @($fim = $totalRegistro);

							 //die($inicio."----".$selQtdeRegistro."-".$selPagina."-".$fim."-".$totalRegistro);

							 return $inicio."_".$fim;
	}

    
        
            function trataNomeImagem($img, $com_ext = false){

                    $final = str_replace(" ","", $img);
                    $final = strtolower($final);

                    $frags = explode(".", $final) ;
                    $semext = explode(".", $final) ;
                    $semext = $semext[0];
                    $saida = $semext;
                    
                    
                    $saida = str_replace("(","", $saida);
                    $saida = str_replace(")","", $saida);

                    $saida  = \App\Http\Service\UtilService::removeAcentos( $saida );
                    $saida  = \App\Http\Service\UtilService::fileNameClean( $saida );


                    if ( strlen($saida) > 100 ){
                         $saida = substr($saida, 0, 100);
                    }

                    if ( $com_ext &&  count($frags) > 1 ){
                        $saida .= "." .$frags[1];
                    }
                    return $saida;
                } 
        
        	
	function executeScalar( $sql ){
		$ar = DB::select($sql);
		return $ar[0]->res;
	}
	
}
