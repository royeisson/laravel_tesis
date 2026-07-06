<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumnos', function (Blueprint $table) {
            $table->id();
            $table->string('dni')->unique()->index();
            $table->string('nombre');
            $table->string('carrera');
            $table->foreignId('aula_id')->constrained('aulas')->onDelete('cascade');
            $table->string('foto_path')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE alumnos ADD COLUMN vector_rostro vector(512)');
    }

    public function down(): void
    {
        Schema::dropIfExists('alumnos');
    }
};
