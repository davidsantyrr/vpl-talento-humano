<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\SubArea;

class Entrega extends Model
{
    use HasFactory;

    /*
     * Campos que se pueden asignar masivamente
     */
    protected $fillable = [
        'nombre',
        'apellidos',
        'tipo',
        'operacion_id',
        'tipo_documento',
        'numberDocumento',
        'elementos',
        'firma',
        'comprobante_path',
    ];

    /**
     * Casts automáticos
     */
    protected $casts = [
        'elementos' => 'array', // JSON → array automáticamente
    ];

    /**
     * Relación: una entrega pertenece a una operación
     */
    public function operacion()
    {
        return $this->belongsTo(SubArea::class, 'operacion_id');
    }

}
