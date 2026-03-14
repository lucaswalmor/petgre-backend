<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ficha_pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->string('nome', 100);
            $table->string('raca', 100);
            $table->enum('porte', ['pequeno', 'medio', 'grande']);
            $table->enum('tamanho_pelagem', ['curta', 'media', 'longa']);
            $table->integer('idade')->nullable();
            $table->enum('unidade_idade', ['meses', 'anos'])->default('anos');
            $table->text('comportamento')->nullable();
            $table->text('alergias')->nullable();
            $table->string('foto_url', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ficha_pets');
    }
};