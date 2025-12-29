<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Usuarios extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'usuarios_entregas';

    protected $fillable = [
        'nombre',
        'apellidos',
        'tipo_documento',
        'numero_documento',
        'email',
        'fecha_ingreso',
        'operacion_id',
        'area_id',
    ];
    protected $dates = ['fecha_ingreso'];

    public function getUsuarioNameAttribute()
    {
        return $this->attributes['nombre'] ?? null;
    }
    public function setUsuarioNameAttribute($value)
    {
        $this->attributes['nombre'] = $value;
    }

    /**
     * Relación: un usuario pertenece a una operación
     */
    public function operacion()
    {
        return $this->belongsTo(Operation::class, 'operacion_id');
    }

}
