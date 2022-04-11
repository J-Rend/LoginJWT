<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use \App\User as Usuario;
use Illuminate\Http\Request;
use stdClass;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{

    public function GetUserByID($id)
    {

        $items = Usuario::find($id);

        return $this->sendResponse(array("data" => $items));
    }
    public function GetAllUsers(Request $request)
    {
        $filtro = "";
        $sql = "Select p.name, p.email, p.created_at, p.updated_at from users p where 1=1 $filtro ;";
        $items = DB::select($sql);

        return $this->sendResponse(array("data" => $items));
    }

    public function CreateUser(Request $request)
    {
        $reg = new Usuario();
        $reg->name = $request->input('name');
        $reg->email = $request->input('email');
        $reg->password = Hash::make($request->input('password'));

        if (!$this->ValidateIfUserAlreadyExists($reg)) {

            $ret = $reg->save();

            return $this->sendResponse(array("msg" => "SUCESSO!", "status" => "SUCESS", "data" => $reg->email));
        } 
        else
            return $this->sendResponse(array("msg" => "Email já utilizado!", "status" => "FAIL"));
    }

    public function ValidateIfUserAlreadyExists($obj)
    {

        $sql = "Select p.email from users p where 
                p.email = '$obj->email' 
                ;";

        $items = DB::select($sql);

        if (count($items) > 0)

            return true;

        else

            return false;
    }
    public function GetUserByEmail($email)
    {

        $sql = "Select p.email,p.id from users p where 
                p.email = '$email' 
                ;";

        $items = DB::select($sql);

        return $items;
    }

    public function DeleteUser($id)
    {
        $reg = Usuario::find($id);
        $ret = $reg->delete();

        $final =  array("msg" => "sucesso", "code" =>  1, "success" => $ret, "data" => $reg);

        return $this->sendResponse($final);
    }

    public function UpdateUser($id, Request $request)
    {
        $reg = Usuario::find($id);

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

    public function insertTokenInUser($token,$email)
    {
        $user = $this->GetUserByEmail($email);
        $reg = Usuario::find($user[0]->id);
        $reg->api_token = $token;
        $ret = $reg->save();
        $msg = "sucesso!";
        
        $final = array("data" => $reg);
        return $this->sendResponse($final);
    }

    private function loadRequests(Request $request, Usuario &$reg)
    {

        if ($request->input('email') != "")
            $reg->email = $request->input('email');

        if ($request->input('password') != "")
            $reg->password = md5($request->input('password'));
    }
}
