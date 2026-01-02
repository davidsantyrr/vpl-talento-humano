<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\SubArea;
use App\Models\Usuarios;
use App\Models\ElementoXEntrega;
use Illuminate\Database\Eloquent\SoftDeletes;

class Entrega extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Tabla asociada
     */
    protected $table = 'entregas';

    /*
     * Campos que se pueden asignar masivamente (según migración)
     */
    protected $fillable = [
        'rol_entrega',
        'entrega_user',
        'tipo_entrega',
        'usuarios_id',
        'operacion_id',
        'recepciones_id',
    ];

    /**
     * Relaciones
     */
    public function usuario()
    {
        return $this->belongsTo(Usuarios::class, 'usuarios_id');
    }

    public function operacion()
    {
        return $this->belongsTo(SubArea::class, 'operacion_id');
    }

    public function recepcion()
    {
        return $this->belongsTo(\App\Models\Recepcion::class, 'recepciones_id');
    }

    public function elementos()
    {
        return $this->hasMany(ElementoXEntrega::class, 'entrega_id');
    }

}
