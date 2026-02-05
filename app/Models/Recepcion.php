<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recepcion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'recepciones';

    protected $fillable = [
        'rol_recepcion',
        'recepcion_user',
        'recepcion_email',
        'tipo_recepcion',
        'tipo_documento',
        'numero_documento',
        'nombres',
        'apellidos',
        'usuarios_id',
        'operacion_id',
        'entregas_id',
        'entregado',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'entregado' => 'boolean',
    ];

    // Relaciones
    public function elementos()
    {
        return $this->hasMany(ElementoXRecepcion::class, 'recepcion_id');
    }

    public function operacion()
    {
        return $this->belongsTo(SubArea::class, 'operacion_id');
    }

    public function usuario()
    {
        // Tabla externa usuarios_entregas (modelo Usuarios esperado)
        return $this->belongsTo(Usuarios::class, 'usuarios_id');
    }

    public function entregas()
    {
        return $this->hasMany(Entrega::class, 'recepciones_id');
    }
}
