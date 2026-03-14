<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            // Campos para variação de preço por porte (serviços)
            $table->enum('tipo_porte', ['unico', 'pequeno', 'medio', 'grande', 'todos'])->default('unico')->after('tipo');
            $table->decimal('preco_pequeno', 10, 2)->nullable()->after('preco');
            $table->decimal('preco_medio', 10, 2)->nullable()->after('preco_pequeno');
            $table->decimal('preco_grande', 10, 2)->nullable()->after('preco_medio');
            $table->string('porte_descricao_pequeno', 50)->nullable()->default('até 10kg')->after('preco_grande');
            $table->string('porte_descricao_medio', 50)->nullable()->default('10-25kg')->after('porte_descricao_pequeno');
            $table->string('porte_descricao_grande', 50)->nullable()->default('25kg+')->after('porte_descricao_medio');
            
            // Duração estimada para serviços (minutos)
            $table->integer('duracao_estimada')->nullable()->after('porte_descricao_grande');
            
            // O que inclui o serviço (JSON)
            $table->json('inclui_servico')->nullable()->after('duracao_estimada');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_porte',
                'preco_pequeno',
                'preco_medio',
                'preco_grande',
                'porte_descricao_pequeno',
                'porte_descricao_medio',
                'porte_descricao_grande',
                'duracao_estimada',
                'inclui_servico'
            ]);
        });
    }
};