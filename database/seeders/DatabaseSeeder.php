<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Aula;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $aulas = ['Aula 1', 'Aula 2', 'Aula 3', 'Aula 4', 'Aula 5'];
        foreach ($aulas as $nombre) {
            Aula::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
