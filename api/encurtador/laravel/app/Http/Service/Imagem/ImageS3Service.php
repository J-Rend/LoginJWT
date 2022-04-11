<?php
namespace App\Http\Service\Imagem;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\AdapterInterface;


class ImageS3Service implements IServiceImage{

       var $client;
       var $adapter;
       var $filesystem;
       var $folder = "banner";

       function __construct(){

   
            $client = new S3Client([
                'credentials' => [
                    'key'    => env("S3_KEY"),
                    'secret' => env("S3_SECRET")
                ],
                'region' => env("S3_REGION"),
                'version' => 'latest',
            ]);
            
            $this->client = $client;

            $this->adapter = new AwsS3Adapter($this->client, env("S3_BUCKET"));
            $this->filesystem = new Filesystem($this->adapter);

       }

       function destroyImagem($name){

                  $this->filesystem->delete($name);
       }


       function sendImagem($file, $name){
           
                   $ar = explode("/", $name);
                   $path = "";
                   
                   if ( count( $ar ) > 1){
                       
                       for ( $i = 0; $i < count($ar)-1; $i++){
                             //Cria as pastas..
                           if ( $path  != "")
                               $path .="/";
                           
                           $path .= $ar[$i];
                           
                              $this->filesystem->createDir($path);
                       }
                       
                   }
                   
                      if ( $this->filesystem->has( $name ) ){
                         $this->filesystem->delete( $name );
                   }
                   
           

                    $this->filesystem->write( $name, file_get_contents( $file ) ,
                        ['visibility' => 'public']);
       }


        function getUrl($name){
          $str =  "https://". env("S3_BUCKET") . ".s3.amazonaws.com/". $name;
          return $str;
       }
}