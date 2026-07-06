<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registro_accesos', function (Blueprint $table) {
            $table->id();
            $table->string('dni');
            $table->timestamp('fecha')->useCurrent();
            $table->string('resultado');
            $table->float('distancia')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registro_accesos');
    }
};
