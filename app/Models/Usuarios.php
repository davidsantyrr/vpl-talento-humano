<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\SubArea;
class Usuarios extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'usuarios_entregas';

    protected $fillable = [
        'nombres',
        'apellidos',
        'tipo_documento',
        'numero_documento',
        'email',
        'cargo_id',
        'fecha_ingreso',
        'operacion_id',
        'area_id',
    ];
    protected $dates = ['fecha_ingreso'];



    public function operacion()
    {
        return $this->belongsTo(SubArea::class, 'operacion_id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }
    public function entregas()
    {
        return $this->hasMany(Entrega::class, 'usuarios_id');
    }
    public function cargo()
    {
        return $this->belongsTo(Cargo::class, 'cargo_id');
    }
    public function elementos()
    {
        return $this->hasMany(ElementosXUsuarios::class, 'usuarios_entregas_id');
    }

}
