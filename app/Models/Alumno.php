<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alumno extends Model
{
    protected $fillable = ['dni', 'nombre', 'carrera', 'aula_id', 'estado', 'foto_path', 'vector_rostro'];

    public function aula()
    {
        return $this->belongsTo(Aula::class);
    }

    public function registros()
    {
        return $this->hasMany(RegistroAcceso::class, 'dni', 'dni');
    }
}
