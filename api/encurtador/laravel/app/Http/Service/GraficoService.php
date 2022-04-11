<?php
namespace App\Http\Service;

use App\ArquivoCliente;
use Illuminate\Support\Facades\DB;
use App\Http\Service\UtilService;

class GraficoService{

	    public static function setBreakLine($text, $wordcount){

	    	 $text = str_replace("  "," ", $text);
	    	 $saida = "";

	    	 $ar = explode(" ", $text  );
	    	 $conta = 0;


	    	 for ( $i = 0 ; $i < count($ar); $i++  ){

                if ( $conta == $wordcount || (strlen($ar[$i]) > 9 && $i >0)   ){
	    	 		 $saida .="\n ";
	    	 		 $conta = 0;
	    	 	}

	    	 	
	    	 	if ( $saida != ""){
	    	 	          $saida .=" ";

	    	 	}

                $saida .=$ar[$i];

                // ||  ( $i < count($ar-1)  && strlen($ar[$i+1]) > 9 )

               

	    	 	$conta++;
	    	 }

	    	 return 
                $saida ;


	    }
    
    	

        public static function getArrayPaginator($ls_unidades, $qtde_registro_max = 10 ){

             $totalRegistro = count($ls_unidades);
             //$qtde_registro_max = count($ls_unidades) +1; //permitindo mais gráficos na mesma página.

             $pageCount = @($totalRegistro / $qtde_registro_max);
          // echo  $pageCount . "-- ".$pageCount; die(" ");


            if ($pageCount < 1)
               $pageCount = 1; 

            if ($pageCount > round($pageCount))
            {   
                $pageCount++;            
            }
            else 
            { 
                 $pageCount = round($pageCount);        
            }

            $pageCount = (int)$pageCount;

            $saida = array();

            for ( $pag = 1; $pag <= $pageCount; $pag++ ){

                $inicio = 0; $fim = 0;
                UtilService::SetaRsetPaginacao($qtde_registro_max, $pag, $totalRegistro, $inicio, $fim);

                $item_pag = array("inicio"=>$inicio,"fim"=>$fim);
                $item_pag["unidades"] = array();
                
                for ( $zz = $inicio; $zz < $fim; $zz++ ){
                    array_push($item_pag["unidades"], $ls_unidades[ $zz ] );
                }
                
                $saida[count($saida)]  =$item_pag;

            }

            return $saida;

        }
        
        public static function getGraficoArray($id_cliente, $ids_unidade,
                $periodo_inicio, $periodo_final, &$ls_unidades , $id_modelo = "", $id_setor = ""){
            
              $ls_unidade = array();
              
	      $lista_inspecao = self::getArrayAuditoria($id_cliente, $ids_unidade,
                                       $periodo_inicio, $periodo_final, $ls_unidades , $id_modelo, $id_setor );
              
              
              
              $ls_paginacao = self::getArrayPaginator($ls_unidade, 8);
              
              return $ls_paginacao;
       
        }

        public static function getArrayAuditoria( $id_cliente, $ids_unidade,
                $periodo_inicio, $periodo_final, &$ls_unidades , $id_modelo = "", $id_setor = "" , $ordenar_grafico = "" ){
            
            
                        $order_by_str = " nome ";
                        
                        if ( $ordenar_grafico == "unidade_asc"){
                            $order_by_str = " nome asc ";
                        }
                        if ( $ordenar_grafico == "unidade_desc"){
                            $order_by_str = " nome desc ";
                        }
                        
                        $ls_unidades = (array) DB::select( "select id, nome, '' as cor
                                         from estabelecimento where id in ( " . $ids_unidade.")"
                               . "         order by ". $order_by_str);

                        $sql = "";
                        $ids = explode(",",$ids_unidade);
                        $array = array();
                        
                        $titulo = \App\Http\Dao\ConfigDao::executeScalar("select nome  as res from modelo_checklist where id = ". $id_modelo);

                        $nome_unidades = UtilService::arrayToString($ls_unidades,"nome",",",false,0,false,true);

                         $ids = explode(",",  UtilService::arrayToString( $ls_unidades,"id",
                                                                       ",",false,0,false,true));
                         $array_titulo = explode(",", "Período,".$nome_unidades);
                         $array[count($array)] = $array_titulo;
                         
                         $complemento = "";
                         if ( $id_modelo != ""){
                             $complemento .= " and subb.id_modelo = ".$id_modelo;
                         }
                         $sql_inspecao = self::getSqlListaInspecao($id_cliente, $ids_unidade, $periodo_inicio, $periodo_final, $complemento);


                         $sql = " select avg(sub.nota) as nota, sub.descricao, sub.unidade_id, sub.mes, sub.ano from 
                                          ( select ip.nota_final as nota, un.id as unidade_id, un.nome as descricao,
                                                   month(ip.data) as mes, year(ip.data) as ano
                                                   from inspecao ip		                     
                                           inner join estabelecimento un on un.id = ip.id_estabelecimento
                                           where ip.id in (  
                                                  ".$sql_inspecao."
                                            )   
                                           and ip.id_estabelecimento in ( " . $ids_unidade. ") ) sub 
                                         group by sub.descricao, sub.unidade_id, sub.mes, sub.ano ";

                               if ( $id_setor != ""){
                                   
                                   
                                      $titulo = \App\Http\Dao\ConfigDao::executeScalar("select nome as res from categoria where id = ". $id_setor);

                                           $sql = " select avg(sub.nota) as nota, sub.descricao,
                                               sub.unidade_id, sub.mes, sub.ano from 
                                          ( select ipit.nota, un.id as unidade_id, un.nome as descricao,
                                                   month(ip.data) as mes, year(ip.data) as ano
                                                   from inspecao_item ipit 	
                                                   inner join  inspecao ip on ip.id = ipit.id_inspecao
                                           inner join estabelecimento un on un.id = ip.id_estabelecimento
                                           where ip.id in (  
                                                  ".$sql_inspecao."
                                            )   and ipit.id_categoria = ". $id_setor . " and ipit.id_item is null 
                                           and ip.id_estabelecimento in ( " . $ids_unidade. ") ) sub 
                                         group by sub.descricao, sub.unidade_id, sub.mes, sub.ano ";


                               }


                               $stsql = " select distinct sub3.ano, sub3.mes from ( ". $sql . ") sub3"
                                       . "   order by sub3.ano desc, sub3.mes desc ";
                               
                               $ar = DB::select(  $stsql);

                                   $arr_grafico = array();

                                    for ( $i = 0; $i < count($ar); $i++ ){
                                         // UtilService::mes_nome( $item["mes"])
                                        $item = (array)$ar[$i];
                                        $item_grafico = array("periodo" => utf8_encode(  
                                               str_pad( $item["mes"], 2, "0", STR_PAD_LEFT)   ."/".$item["ano"] ));


                                           for ( $zz = 0; $zz < count($ls_unidades); $zz++ ){
                                                      $item_unidade = $ls_unidades[$zz];

                                                      self::getValorUnidade($item_grafico, $sql,
                                                                $item["ano"], $item["mes"], $item_unidade ); //Setando o valor..
                                           }


                                        $arr_grafico[ count($arr_grafico)] = $item_grafico;
                                    }
                                    
                                    
                                    
                                    for ( $i = 0; $i < count($ls_unidades); $i++ ){
                                          $item= &$ls_unidades[$i];
                                          $item->cor = self::getHtmlColorRandom($i);
                                        
                                    }


                                    
             $ls_saida = array();
             for ( $zz = count($ls_unidades)-1; $zz >= 0; $zz-- ){
                     $ls_saida[count($ls_saida)] =  $ls_unidades[$zz];                        
                                               
             }


               return array("data"=>$arr_grafico,"titulo"=>$array_titulo,"unidades"=>$ls_unidades,
                   "unidades_inv"=>$ls_saida,
                   "titulo"=>$titulo);
           }
           
           


        public static function mostraTabela(array $ls_unidade, array $arr , $style = "", $tit = "Período"){

            
               $saida = array();
               
               $titulo = array($tit);
               for ( $y = 0; $y < count( $ls_unidade); $y++){
                    $item = $ls_unidade[$y ];
                    $titulo[count($titulo)] = $item->nome;                   
               }
               
               
               //$saida[0] = $titulo;
               
               for ( $y = 0; $y < count( $arr); $y++){
                   
                    $item = $arr[$y ];
                   
                    $item_reg = array();
                    $item_reg[count($item_reg)] = $item["periodo"];
                     for ( $z = 0; $z < count( $ls_unidade); $z++){
                         $value = "value_". $ls_unidade[$z]->id;
                         
                         $txt  = UtilService::numeroTela(  $item[$value ], 0 );
                         if ( $txt == ""){
                               $txt = " - ";
                         }
                         $item_reg[count($item_reg)] =  $txt;
                         
                     }
                     
                     
                     $saida[count($saida)] = $item_reg;
               }
            
               return array("body"=>$saida,"head"=>$titulo);
            
                $str = "";

                $str = "<table " . $style. ">";

                $estilo1 = " bgcolor='#EEEEEE' ";
                $estilo2 = " bgcolor='#F7F4F2' ";

                $y = -1;
                foreach ($arr as $key => $item) {
                    
                    $y++;
                //} ( $y = 0; $y < count( $arr); $y++){

                       // $item = $arr[ $key ];
                        $str .= "<tr";

                        if ( $y > 0){
                        if ( $y % 2 ){
                                 $str .= " ".$estilo1; 
                                }else{

                                 $str .= " ".$estilo2;
                                        }
                        }else{

                                 $str .= " bgcolor='#FFB951'  "; 


                        }

                        $str.=">";
                        $yy = -1;
                 foreach ($item as $key2 => $value2 ) {
                        //for ( $yy = 0; $yy < count( $item); $yy++){

                                $classe = "td";

                                if ( $y > 0 )
                                   $classe = "td";

                                if ( $yy > 0 )
                                   $classe .= " align='center' ";

                                $str .= "<".$classe.">";

                                if ( is_numeric($value2 ) ){

                                        $str .=  UtilService::NVL(UtilService::numeroTela( $value2, 1 ),"0");
                                        }else{

                                    $str .= UtilService::NVL( $value2,"-");
                                }
                                $str .= "</".$classe.">";
                         }
                        $str .= "</tr>";


                }


                $str .= "</table>";

                return $str;
        }

        public static function getSqlListaInspecao($id_cliente, $ids_unidade, $periodo_inicio, $periodo_final, $complemento = ""){
            
                       // die($periodo_final . " ". $periodo_inicio);
                        $ar_fim = explode("/",$periodo_final);
                        $ar_inicio = explode("/",$periodo_inicio);

                        $ultimoDiaMes = UtilService::UltimoDiaDoMes( $ar_fim[0], $ar_fim[1] );

                        $legenda = array();
                        $bars = array(); $array = array();

                        $nome_grafico = "";


                        $filtro_inicio = $ar_inicio[1]."-".$ar_inicio[0]. "-01";
                        $filtro_fim = $ar_fim[1]."-".$ar_fim[0]. "-".$ultimoDiaMes;
                        
                        $sql_inspecao = "  select subb.id from inspecao subb
                                                where
                                                  subb.data >= '".$filtro_inicio." 00:00:00'
                                                  and  subb.data <= '".$filtro_fim." 23:59:59'
                                          and subb.id_cliente = ".$id_cliente." ";
         
                        
                        if ( $ids_unidade != ""){
                              $sql_inspecao .= " and subb.id_estabelecimento in ( ". $ids_unidade ." ) ";
                        }
                        $sql_inspecao.= $complemento;
                        
                        //die( $sql_inspecao );
                        
                        return $sql_inspecao;
                        
                        //$lista = DB::select($sql_inspecao);
                        
                        //return $lista;
        }
        
        
        
    public static function getValorUnidade(&$item, $sql, $ano, $mes, $item_unidade){
     
        
          $id_unidade = $item_unidade->id;
          $nome_unidade = $item_unidade->nome;

            $stsql = " select sum(sub3.nota) as nota from ( ". $sql . ") sub3 
                    where sub3.ano = " . $ano.
                    " and sub3.mes=" . $mes." "
                    . "and sub3.unidade_id = " . $id_unidade;

            $sql_conta  = " select * from ( ". $sql . ") sub3 
              where sub3.ano = " . $ano. " and sub3.mes=" . $mes." and sub3.unidade_id = " . 
                 $id_unidade;

            $ar20 = DB::select( $stsql);
            $ar22= DB::select( $sql_conta);

            $item["value_". $id_unidade] = 0;
            $item["category_". $id_unidade] = utf8_encode( $nome_unidade );

            if ( count($ar22) > 0  ){

                     $item["value_". $id_unidade] = round( (float)  $ar20[0]->nota, 2 );
            }

    }
    
    
    public static function getValorUnidadeCategoria(&$item, $sql, $id_categoria, $mes, $item_unidade ){
        
            $id_unidade = $item_unidade->id;
            $nome_unidade = $item_unidade->nome;

            //
                   // " and sub3.mes=" . $mes." "
            $stsql = " select sum(sub3.nota) as nota from ( ". $sql . ") sub3 
                    where sub3.id_categoria = " . $id_categoria.
                     " and sub3.id_unidade = " . $id_unidade;

            $sql_conta  = " select * from ( ". $sql . ") sub3 
              where sub3.id_categoria = " . $id_categoria. "  and sub3.id_unidade = " . 
                 $id_unidade;

            $ar20 = DB::select( $stsql);
            $ar22= DB::select( $sql_conta);

            $item["value_". $id_unidade] = 0;
            $item["category_". $id_unidade] = utf8_encode( $nome_unidade );

            if ( count($ar22) > 0  ){

                     $item["value_". $id_unidade] = round( (float)  $ar20[0]->nota, 2 );
            }
        
    }
      
        
    public static function getValorCategoria(&$item, $sql, $ano, $mes, $item_categoria ){
     
        
          $id_unidade = $item_categoria->id;
          $nome_unidade = $item_categoria->nome;

            $stsql = " select sum(sub3.nota) as nota from ( ". $sql . ") sub3 
                    where sub3.ano = " . $ano.
                    " and sub3.mes=" . $mes." "
                    . "and sub3.id_categoria = " . $id_unidade;

            $sql_conta  = " select * from ( ". $sql . ") sub3 
              where sub3.ano = " . $ano. " and sub3.mes=" . $mes." and sub3.id_categoria = " . 
                 $id_unidade;

            $ar20 = DB::select( $stsql);
            $ar22= DB::select( $sql_conta);

            $item["value_". $id_unidade] = 0;
            $item["category_". $id_unidade] = utf8_encode( $nome_unidade );

            if ( count($ar22) > 0  ){

                     $item["value_". $id_unidade] = round( (float)  $ar20[0]->nota, 2 );
            }

    }
    
    
    
        public static function getArrayCategoria( $id_cliente, $ids_unidade,
                $periodo_inicio, $periodo_final, &$ls_unidades , $id_modelo = "", $id_setor = "", $ordenar_grafico = "" ){
              
            
                  
                         $complemento = "";
                         if ( $id_modelo != ""){
                             $complemento .= " and subb.id_modelo = ".$id_modelo;
                         }
                         $sql_inspecao = self::getSqlListaInspecao($id_cliente, $ids_unidade, $periodo_inicio, $periodo_final, $complemento);
                         
                         
                         $sql_cat = " select distinct it.id_categoria as id, modi.ordem, cat.nome from inspecao_item it 
                                           left join  categoria cat on cat.id = it.id_categoria 
                                                left join inspecao insp on insp.id = it.id_inspecao
                                                 left join modelo_checklist modc on modc.id = insp.id_modelo
                                                 left join modelo_checklist_itens modi on (modi.id_modelo_check = modc.id and modi.id_categoria = it.id_categoria and modi.id_item is null)
				
					  where it.id_inspecao in (  
								        ".$sql_inspecao." and subb.id_estabelecimento in ( " . $ids_unidade . " )
								   ) and ifNull(it.nota,0) > -1 order by modi.ordem desc, cat.nome ";
                        $ls_unidades = DB::select( $sql_cat );

                        $sql = "";
                        $ids = explode(",",$ids_unidade);
                        $array = array();
                        
                        $titulo = \App\Http\Dao\ConfigDao::executeScalar("select nome  as res from modelo_checklist where id = ". $id_modelo);

                        $nome_unidades = UtilService::arrayToString($ls_unidades,"nome",",",false,0,false,true);

                         $ids = explode(",",  UtilService::arrayToString( $ls_unidades,"id",
                                                                       ",",false,0,false,true));
                         $array_titulo = explode(",", "Período,".$nome_unidades);
                         $array[count($array)] = $array_titulo;
                   

                         $sql = " select avg(sub.nota) as nota, sub.descricao, sub.id_categoria, sub.mes, sub.ano from 
		                   ( select it.nota , un.id as id_categoria, un.nome as descricao,
						               month(ip.data) as mes, year(ip.data) as ano
		                            from inspecao ip		 
								  inner join inspecao_item it on it.id_inspecao = ip.id                   
								  inner join categoria un on un.id = it.id_categoria
                                                              
								  where ip.id in (  
								        ".$sql_inspecao."
								   )   
								  and ip.id_estabelecimento in ( " . $ids_unidade. ") and it.id_item is null  ) sub 
								group by sub.descricao, sub.id_categoria, sub.mes, sub.ano ";

                               $stsql = " select distinct sub3.ano, sub3.mes from ( ". $sql . ") sub3"
                                       . "   order by sub3.ano, sub3.mes ";
                               
                               $ar = DB::select(  $stsql);

                                   $arr_grafico = array();

                                    for ( $i = 0; $i < count($ar); $i++ ){
                                         // UtilService::mes_nome( $item["mes"])
                                        $item = (array)$ar[$i];
                                        $item_grafico = array("periodo" => utf8_encode(  
                                               str_pad( $item["mes"], 2, "0", STR_PAD_LEFT)   ."/".$item["ano"] ));


                                           for ( $zz = 0; $zz < count($ls_unidades); $zz++ ){
                                                      $item_unidade = $ls_unidades[$zz];
                                                      
                                                      self::getValorCategoria($item_grafico, $sql,
                                                                $item["ano"], $item["mes"], $item_unidade ); //Setando o valor..
                                           }


                                        $arr_grafico[ count($arr_grafico)] = $item_grafico;
                                    }
                                    
                                    
                                    
                                    for ( $i = 0; $i < count($ls_unidades); $i++ ){
                                          $item= &$ls_unidades[$i];
                                          $item->cor = self::getHtmlColorRandom($i);
                                        
                                    }

             $ls_saida = array();
             for ( $zz = count($ls_unidades)-1; $zz >= 0; $zz-- ){
                     $ls_saida[count($ls_saida)] =  $ls_unidades[$zz];                        
                                               
             }

               return array("data"=>$arr_grafico,"titulo"=>$array_titulo,"unidades"=>$ls_unidades,
                   
                   "unidades_inv"=>$ls_saida,
                   "titulo"=>$titulo);
           }
           
         
    
        public static function getArrayCategoriaPorInspecao( $id ){
              
            
            $sql_inspecao = \App\Http\Dao\InspecaoDao::SqlInspecao()." where i.id = ". $id;
            
            $ls_inspecao = DB::select($sql_inspecao);
            
            $registro = $ls_inspecao[0];
                  
                         $complemento = "";
                         $sql_cat = " select distinct it.id_categoria as id, modi.ordem, cat.nome from inspecao_item it 
                                           left join  categoria cat on cat.id = it.id_categoria 
                                                left join inspecao insp on insp.id = it.id_inspecao
                                                 left join modelo_checklist modc on modc.id = insp.id_modelo
                                                 left join modelo_checklist_itens modi on (modi.id_modelo_check = modc.id and modi.id_categoria = it.id_categoria and modi.id_item is null)
				
					  where it.id_inspecao in( ".$id.") and ifNull(it.nota,0) > -1 order by modi.ordem desc, cat.nome ";
                        $ls_unidades = DB::select( $sql_cat );

                        $sql = "";
                        
                        $array = array();
                        
                        $titulo = \App\Http\Dao\ConfigDao::executeScalar("select nome  as res from modelo_checklist where id = ". $registro->id_modelo);

                        $nome_unidades = UtilService::arrayToString($ls_unidades,"nome",",",false,0,false,true);

                         $ids = explode(",",  UtilService::arrayToString( $ls_unidades,"id",
                                                                       ",",false,0,false,true));
                         $array_titulo = explode(",", "Período,".$nome_unidades);
                         $array[count($array)] = $array_titulo;
                   

                         $sql = " select avg(sub.nota) as nota, sub.descricao, sub.id_categoria, sub.mes, sub.ano from 
		                   ( select it.nota , un.id as id_categoria, un.nome as descricao,
						               month(ip.data) as mes, year(ip.data) as ano
		                            from inspecao ip		 
								  inner join inspecao_item it on it.id_inspecao = ip.id                   
								  inner join categoria un on un.id = it.id_categoria
                                                              
								  where ip.id in (  ".$id.")  and it.id_item is null  ) sub 
								group by sub.descricao, sub.id_categoria, sub.mes, sub.ano ";

                               $stsql = " select distinct sub3.ano, sub3.mes from ( ". $sql . ") sub3"
                                       . "   order by sub3.ano, sub3.mes ";
                               
                               $ar = DB::select(  $stsql);

                                   $arr_grafico = array();

                                    for ( $i = 0; $i < count($ar); $i++ ){
                                         // UtilService::mes_nome( $item["mes"])
                                        $item = (array)$ar[$i];
                                        $item_grafico = array("periodo" => utf8_encode(  
                                               str_pad( $item["mes"], 2, "0", STR_PAD_LEFT)   ."/".$item["ano"] ));


                                           for ( $zz = 0; $zz < count($ls_unidades); $zz++ ){
                                                      $item_unidade = $ls_unidades[$zz];
                                                      
                                                      self::getValorCategoria($item_grafico, $sql,
                                                                $item["ano"], $item["mes"], $item_unidade ); //Setando o valor..
                                           }


                                        $arr_grafico[ count($arr_grafico)] = $item_grafico;
                                    }
                                    
                                    
                                    
                                    for ( $i = 0; $i < count($ls_unidades); $i++ ){
                                          $item= &$ls_unidades[$i];
                                          $item->cor = self::getHtmlColorRandom($i);
                                        
                                    }

             $ls_saida = array();
             for ( $zz = count($ls_unidades)-1; $zz >= 0; $zz-- ){
                     $ls_saida[count($ls_saida)] =  $ls_unidades[$zz];                        
                                               
             }

               return array("data"=>$arr_grafico,"registro"=> $registro, 
                   "unidades_inv"=>$ls_saida,
                   "titulo"=>$array_titulo,"unidades"=>$ls_unidades, "titulo"=>$titulo);
           }
             
           
        public static function getArrayCategoriasUnidades( $id_cliente, $ids_unidade,
                $periodo_inicio, $periodo_final, &$ls_unidades , $id_modelo = "", $id_setor = "" , $ordenar_grafico= ""){
              
            
                  
                         $complemento = "";
                         if ( $id_modelo != ""){
                             $complemento .= " and subb.id_modelo = ".$id_modelo;
                         }
                         $sql_inspecao = self::getSqlListaInspecao($id_cliente, $ids_unidade, $periodo_inicio, $periodo_final, $complemento);
                         
                         //die($sql_inspecao);
                         $sql_cat = " select distinct it.id_categoria as id, cat.nome, modi.ordem from inspecao_item it 
                                           left join  categoria cat on cat.id = it.id_categoria 
                                           left join inspecao insp on insp.id = it.id_inspecao
                                           left join modelo_checklist modc on modc.id = insp.id_modelo
                                           left join modelo_checklist_itens modi on (modi.id_modelo_check = modc.id and modi.id_categoria = it.id_categoria and modi.id_item is null)
					  where it.id_inspecao in (  
								        ".$sql_inspecao." 
								   )  and it.id_item is null and ifNull(it.nota,0) > -1 order by modi.ordem desc, cat.nome ";
                         //and subb.id_estabelecimento in ( " . $ids_unidade . " )
                        $ls_categorias = DB::select( $sql_cat );
                        
                        
                        
                        $order_by_str = " cat.nome ";
                        
                        if ( $ordenar_grafico == "unidade_asc"){
                            $order_by_str = " cat.nome asc ";
                        }
                        if ( $ordenar_grafico == "unidade_desc"){
                            $order_by_str = " cat.nome desc ";
                        }
                        
                        $sql_cat = " select distinct it.id_estabelecimento as id, cat.nome from inspecao it 
                                           left join  estabelecimento cat on cat.id = it.id_estabelecimento 
					  where it.id in (  
								        ".$sql_inspecao." 
								   ) order by ". $order_by_str;
                         //and subb.id_estabelecimento in ( " . $ids_unidade . " )
                        $ls_unidades = DB::select( $sql_cat );

                        $sql = "";
                        $ids = explode(",",$ids_unidade);
                        $array = array();
                        
                        $titulo = \App\Http\Dao\ConfigDao::executeScalar("select nome  as res from modelo_checklist where id = ". $id_modelo);

                        $nome_unidades = UtilService::arrayToString($ls_unidades,"nome",",",false,0,false,true);

                         $ids = explode(",",  UtilService::arrayToString( $ls_unidades,"id",
                                                                       ",",false,0,false,true));
                         $array_titulo = explode(",", "Categoria,".$nome_unidades);
                         $array[count($array)] = $array_titulo;
                   

                         $sql = " select avg(sub.nota) as nota, sub.descricao, sub.id_categoria, sub.mes, sub.ano, sub.nome_unidade, sub.id_unidade from 
		                   ( select it.nota , cat.id as id_categoria, cat.nome as descricao, esta.nome as nome_unidade,
                                                              esta.id as id_unidade,
						               month(ip.data) as mes, year(ip.data) as ano
		                                                 from inspecao ip		 
								  inner join inspecao_item it on it.id_inspecao = ip.id                   
								  inner join categoria cat on cat.id = it.id_categoria                
								  inner join estabelecimento esta on esta.id = ip.id_estabelecimento
								  where ip.id in (  
								        ".$sql_inspecao."
								   )   
								  and ip.id_estabelecimento in ( " . $ids_unidade. ") and it.id_item is null  ) sub 
								group by sub.descricao, sub.id_categoria, sub.mes, 
                                                                sub.ano, sub.nome_unidade, sub.id_unidade ";

                              // $stsql = " select distinct sub3.descricao, sub3.id_categoria, sub3.ano, sub3.mes from ( ". $sql . ") sub3"
                              //         . "   order by sub3.descricao, sub3.id_categoria, sub3.ano, sub3.mes ";
                               
                              // $ar = DB::select(  $stsql);

                                   $arr_grafico = array();
                                      //print_r( $ls_categorias ); die(" ");
                                    for ( $i = 0; $i < count($ls_categorias); $i++ ){
                                        
                                        //print_r( $item ); die(" ");
                                         // UtilService::mes_nome( $item["mes"])
                                        $item = $ls_categorias[$i];
                                        $item_grafico = array("periodo" => self::setBreakLine(  $item->nome , 2), "id_categoria" => $item->id );
                                              // str_pad( $item["mes"], 2, "0", STR_PAD_LEFT)   ."/".$item["ano"] ));


                                           for ( $zz = 0; $zz < count($ls_unidades); $zz++ ){
                                                      $item_unidade = $ls_unidades[$zz];
                                                      
                                                      self::getValorUnidadeCategoria($item_grafico, $sql,
                                                                $item->id, "", $item_unidade ); //Setando o valor..
                                           }


                                        $arr_grafico[ count($arr_grafico)] = $item_grafico;
                                    }
                                    
                                    
                                    
                                    for ( $i = 0; $i < count($ls_unidades); $i++ ){
                                          $item= &$ls_unidades[$i];
                                          $item->cor = self::getHtmlColorRandom($i);
                                        
                                    }


             $ls_saida = array();
             for ( $zz = count($ls_unidades)-1; $zz >= 0; $zz-- ){
                     $ls_saida[count($ls_saida)] =  $ls_unidades[$zz];                        
                                               
             }


               return array("data"=>$arr_grafico,"titulo"=>$array_titulo,"unidades"=>$ls_unidades,
                   "unidades_inv"=>$ls_saida,
                   "titulo"=>$titulo);
           }
    
        public static function getHtmlColorRandom($id){

            $cores = array(
                     "3366CC","DC3912",
                    "FF9900","109618",
                    "990099","0099C6",
                    "DD4477","66AA00","B82E2E",

                   "73171C", "826F8C", "4D6E70", "AD6F72","DFE5EB","EBA9AC", "483C8C",

                    "D4D29F", "F0D1D4", "D1F0EA", "F0E0D1", "F0F0D1", "0000CD", "689396", "FAE19D",
                            "68ADF2", "29234D","E8AC07","5691CC",  "F29268",
                            "C0A7CC",
                            "502A63", "282E33", "327480", "63851B","9E9B99", "B5A084", "164852","A6B584",
                            "A384B5","B5AF84","40A8A7","BAA218","BA4118",
                
                
                     "3366CC","DC3912",
                    "FF9900","109618",
                    "990099","0099C6",
                    "DD4477","66AA00","B82E2E",

                   "73171C", "E5D1F0", "4D6E70", "AD6F72","DFE5EB","EBA9AC", "483C8C",

                    "D4D29F", "F0D1D4", "D1F0EA", "F0E0D1", "F0F0D1", "0000CD", "689396", "FAE19D",
                            "68ADF2", "29234D","E8AC07","5691CC",  "F29268",
                            "C0A7CC",
                            "502A63", "282E33", "327480", "63851B","9E9B99", "B5A084", "164852","A6B584",
                            "A384B5","B5AF84","40A8A7","BAA218","BA4118"
                

                            );

            if ( $id > count($cores) -1 ){
                
                $id = (int) rand(0, count($cores));

                  //  $id = 0;	
            }

            return "#".$cores[ $id ];

    }
    

    
}