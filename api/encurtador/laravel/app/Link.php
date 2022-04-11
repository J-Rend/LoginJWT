<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Cliente
 *
 * @package App
*/
class Link extends Model
{
    protected $table = 'link';
    public $timestamps = false;
    
     protected $casts = [
        'id_usuario' => 'int',
        'nome_link' => 'string'
    ];
    protected $dates = [
        'created_at',
        'updated_at',
    ];
    
    protected $fillable = ['id_usuario','link','nome_link','qnt_acessos','short_link'];
    
}
