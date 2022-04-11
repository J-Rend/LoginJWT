<?php
namespace App\Http\Service;


use App\Log;
use Illuminate\Support\Facades\DB;
use DateTime;
use Illuminate\Support\Facades\Auth;

class LogService{
    
    
    
    static function salvar($request, $tabela, $tipo, $registro_id, $excluir = false ){
        
              $user = Auth::user();
              $usuario = \App\Http\Dao\UsuarioDao::getUsuarioByUser($user);
              
              
        if ( $excluir ){
            $dt = new DateTime(date("Y-m-d"));
            $dt->modify("-60 days");
            DB::statement("delete from log where data_inicio <= '". $dt->format("Y-m-d")." 00:00:00' and tipo='".$tipo."' and tabela = '". $tabela."' ");
        }
           

            $input = $request->all();
            $oLog = new \App\Log();

           $oLog->data_inicio = date("Y-m-d");
           $oLog->data_fim = date("Y-m-d");
           $oLog->texto = json_encode(array("input"=>$input) );
           $oLog->registros_auxiliares = json_encode(array("header"=>$request->headers->all() ));
           $oLog->registro_id = $registro_id;
           $oLog->tabela = $tabela;
           $oLog->tipo = $tipo;
           $oLog->operador_id = $usuario->id;
           $oLog->nome_operador = $usuario->nome;
           $oLog->save();
        
    }
    
    
       
    static function salvar2($titulo, $data, $tabela, $tipo, $registro_id, $tit = "", $excluir = true ){
        
              $user = Auth::user();
              $usuario = \App\Http\Dao\UsuarioDao::getUsuarioByUser($user);
              
              
        if ( $excluir ){
            $dt = new DateTime(date("Y-m-d"));
            $dt->modify("-60 days");
            DB::statement("delete from log where data_inicio <= '". $dt->format("Y-m-d")." 00:00:00' and tipo='".$tipo."' and tabela = '". $tabela."' ");
        }
           
            $oLog = new \App\Log();

           $oLog->data_inicio = date("Y-m-d");
           $oLog->data_fim = date("Y-m-d");
           $oLog->texto = json_encode( $data );
           $oLog->registros_auxiliares = $titulo;
           $oLog->registro_id = $registro_id;
           $oLog->tabela = $tabela;
           $oLog->tipo = $tipo;
           $oLog->titulo = $tit;
           $oLog->operador_id = $usuario->id;
           $oLog->nome_operador = $usuario->nome;
           $oLog->save();
        
    }
    
    
    
            
    
    
    
}