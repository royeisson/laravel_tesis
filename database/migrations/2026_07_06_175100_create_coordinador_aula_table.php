<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coordinador_aula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coordinador_id')->constrained('coordinadores')->onDelete('cascade');
            $table->foreignId('aula_id')->constrained('aulas')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['coordinador_id', 'aula_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coordinador_aula');
    }
};
