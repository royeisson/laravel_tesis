<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->dropForeign(['aula_id']);
            $table->unsignedBigInteger('aula_id')->nullable()->change();
            $table->foreign('aula_id')->references('id')->on('aulas')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('alumnos', function (Blueprint $table) {
            $table->dropForeign(['aula_id']);
            $table->unsignedBigInteger('aula_id')->nullable(false)->change();
            $table->foreign('aula_id')->references('id')->on('aulas')->onDelete('cascade');
        });
    }
};
