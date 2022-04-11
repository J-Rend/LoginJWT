<?php
namespace App\Http\Service\Imagem;

class FactoryServiceImagem{

   static function create($tip ){

           if ( strtolower( $tip ) == "s3"){

           	      $obj = new ImageS3Service();
           	      return $obj;

           }

           $obj = new ImagePathService();
           return $obj;

   }

}