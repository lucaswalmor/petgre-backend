<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avaliacoes_moderacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('avaliacao_id')
                  ->constrained('empresa_avaliacoes')
                  ->onDelete('cascade');
            $table->foreignId('empresa_id')
                  ->constrained('empresas')
                  ->onDelete('cascade');
            $table->text('motivo');
            $table->enum('status', ['pendente', 'em_analise', 'aprovado', 'rejeitado'])
                  ->default('pendente');
            $table->text('observacao_moderador')->nullable();
            $table->timestamps();

            // Cada avaliação só pode ter uma solicitação de moderação
            $table->unique('avaliacao_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacoes_moderacao');
    }
};
