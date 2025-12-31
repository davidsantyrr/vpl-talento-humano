<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ElementoXEntrega extends Model
{
    use HasFactory;

    protected $table = 'elemento_x_entrega';

    protected $fillable = [
        'sku',
        'cantidad',
    ];

    public function entrega()
    {
        return $this->belongsTo(Entrega::class, 'entrega_id');
    }
}
