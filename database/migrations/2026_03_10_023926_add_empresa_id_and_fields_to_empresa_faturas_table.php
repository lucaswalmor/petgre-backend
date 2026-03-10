<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('empresa_faturas', function (Blueprint $table) {
            // Adicionar empresa_id (matriz) para vincular a cobrança
            $table->foreignId('empresa_id')->nullable()->after('usuario_id')->constrained('empresas')->onDelete('cascade');

            // Campos para controle da cobrança condicional mensal
            $table->unsignedInteger('quantidade_pedidos')->default(0)->after('valor')->comment('Total de pedidos do mês (matriz + filiais)');
            $table->unsignedInteger('quantidade_filiais')->default(0)->after('quantidade_pedidos')->comment('Quantidade de filiais ativas consideradas no cálculo');

            // Indice único para evitar duplicidade de cobrança por mês/empresa
            $table->unique(['empresa_id', 'mes_referencia'], 'unique_fatura_mes_empresa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresa_faturas', function (Blueprint $table) {
            $table->dropForeign(['empresa_id']);
            $table->dropColumn(['empresa_id', 'quantidade_pedidos', 'quantidade_filiais']);
            $table->dropUnique('unique_fatura_mes_empresa');
        });
    }
};
