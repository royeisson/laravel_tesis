<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aula extends Model
{
    protected $fillable = ['nombre'];

    public function alumnos()
    {
        return $this->hasMany(Alumno::class);
    }

    public function coordinadores()
    {
        return $this->belongsToMany(Coordinador::class, 'coordinador_aula');
    }
}
