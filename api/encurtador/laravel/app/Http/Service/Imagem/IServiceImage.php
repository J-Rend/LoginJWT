<?php
namespace App\Http\Service\Imagem;


interface IServiceImage{


       function destroyImagem($name);

       function sendImagem($file, $name);

       function getUrl($name);

}