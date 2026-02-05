<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GestionNotificacionInventario extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'gestion_notificaciones_inventario';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'elemento',
        'stock',
        'notified',
        'last_notified_at',
    ];

    protected $casts = [
        'notified' => 'boolean',
        'last_notified_at' => 'datetime',
    ];
}
