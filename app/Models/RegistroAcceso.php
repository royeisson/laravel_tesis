<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistroAcceso extends Model
{
    protected $fillable = ['dni', 'fecha', 'resultado', 'distancia'];

    protected $casts = [
        'fecha' => 'datetime',
    ];
}
