<?php
namespace App\Http\Service;

use ZipArchive;

 class  ZipService
 {
     public static function extract($zip_file_path, $destination ){
         
         $zip = new ZipArchive;
         if ($zip->open($zip_file_path) === TRUE) {
             
               $zip->extractTo($destination);
               $zip->close();
             
               $files = explode("," ,  join( array_diff(scandir($destination), array('.', '..')),","));
               $saida = array();
               
               for($i = 0; $i < count($files); $i++ ){
                   $saida[count($saida)] = $destination . DIRECTORY_SEPARATOR . $files[$i];
               }
              // print_r( $saida );die(" ");
               return $saida; 
         }
         
         return array();
         
    }
     
     
     
 }

