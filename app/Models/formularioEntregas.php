<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\SubArea;

class FormularioEntregas extends Model
{
    use HasFactory;

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

    protected $casts = [
        'elementos' => 'array',
    ];

    public function operacion()
    {
        return $this->belongsTo(SubArea::class, 'operacion_id');
    }
}
