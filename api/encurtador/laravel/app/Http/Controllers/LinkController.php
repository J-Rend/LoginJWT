<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

use \App\Link as Link;
use Illuminate\Http\Request;
use stdClass;

class LinkController extends Controller{

    public function GetLinkByID($id)
    {
		$items= Link::find($id);

        return $this->sendResponse(array("data"=>$items));
    }   
    public function GetRankedLinks(Request $request)
    {

        $filtro = "";
        $order = " order by p.qnt_acesso asc ";
        $sql = "Select p.* from link p where 1=1 $filtro $order ;";
        $items = DB::select($sql);

        return $this->sendResponse(array("data"=>$items));
    }

    public function CreateLink(Request $request)
    {
        $link = $request->input('link');
        $id_usuario = $request->input('id_usuario');
        $short_link = $this->generateShortLink($link);

        $reg = new Link();

        $reg->link = $link;
        $reg->id_usuario = $id_usuario;
        $reg->short_link = $short_link;
        $reg->nome_link = $request->input('nome_link');
        $reg->qnt_acessos = 0;

        if(!$this->ValidateIfLinkAlreadyExists($reg))
        {
            $reg->save();

            return $this->sendResponse(array("msg"=>"Link $reg->nome_link criado com sucesso!", "status"=>"SUCCESS","data"=>$reg));
        }else
        {
            return $this->sendResponse(array("msg"=>"Nome do link já está sendo usado por outra pessoa!", "status"=>"FAIL"));
        }


    }

    public function ValidateIfLinkAlreadyExists($obj)
    {

        $sql = "Select p.nome_link from link p where 
                p.nome_link = '$obj->nome_link' 
                ;";

        $items = DB::select($sql);

        if(count($items) > 0 )

        return true;

        else 

        return false;
        
    }

    public function DeleteLink($id)
	{
		$reg = Link::find($id);
		$ret = $reg->delete();

		$final =  array("msg" => "sucesso", "code" =>  1, "success" => $ret, "data" => $reg);

		return $this->sendResponse($final);
	}

    public function UpdateLink($id,Request $request)
    {
		$reg = Link::find($id);

		$this->loadRequests($request, $reg);

		$ret = $reg->save();

		$msg = "sucesso!";
		$code = 1;
		if (!$ret) {
			$code = 0;
			$msg = "erro";
		}


		$final = array("msg" => $msg, "code" =>  $code, "success" => $ret, "data" => $reg);
		return $this->sendResponse($final);

    }

	private function loadRequests(Request $request, Link &$reg)
	{
        if($request->input('nome_link') !="")
		$reg->nome_Link = $request->input('nome_Link');
	}

    private function generateShortLink($full_link)
    {
        $temp_arr1 = ["w",".","com","br","http://"];
        $temp_link = str_replace($temp_arr1,"",$full_link);
        $temp_link = md5($full_link);
        $temp_link = $temp_link[0] + $temp_link[1] + $temp_link[2] + $temp_link[3] + $temp_link[4];
        return $temp_link;

    }


}