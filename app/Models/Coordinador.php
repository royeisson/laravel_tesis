<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coordinador extends Model
{
    protected $table = 'coordinadores';
    protected $fillable = ['nombre', 'usuario', 'password'];

    public function aulas()
    {
        return $this->belongsToMany(Aula::class, 'coordinador_aula');
    }
}
