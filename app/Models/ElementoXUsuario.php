<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ElementoXUsuario extends Model
{
    use HasFactory;

    protected $table = 'elemento_x_usuario';

    protected $fillable = [
        'sku',
        'name_produc',
        'usuarios_entregas_id',
    ];

    public function usuarioEntrega()
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_entregas_id');
    }
}
