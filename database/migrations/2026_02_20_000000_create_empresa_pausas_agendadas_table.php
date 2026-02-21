<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_pausas_agendadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->dateTime('data_inicio');
            $table->dateTime('data_fim');
            $table->string('motivo', 255)->nullable();
            $table->boolean('recorrente')->default(false)->comment('Se true, aplica todo dia no horário de data_inicio/data_fim');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_pausas_agendadas');
    }
};
