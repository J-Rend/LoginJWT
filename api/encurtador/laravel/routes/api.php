<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
    Route::post('login', 'AuthController@login');
    Route::post('register_user', 'UsuarioController@CreateUser');

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['middleware' => ['auth:api']], function () {

    Route::get('/get_user_id/{id}', 'UsuarioController@GetUserByID');
    Route::get('/get_user_all', 'UsuarioController@GetAllUsers');
    Route::post('/auth_user', 'UsuarioController@AutenticateUser');
    Route::post('/delete_user/{id}', 'UsuarioController@DeleteUser');
    Route::post('/update_user/{id}', 'UsuarioController@UpdateUser');
});
