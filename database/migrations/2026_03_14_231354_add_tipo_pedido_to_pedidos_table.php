<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            // Tipo do pedido: produto, servico, misto
            $table->enum('tipo_pedido', ['produto', 'servico', 'misto'])->default('produto')->after('status_pedido_id');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('tipo_pedido');
        });
    }
};
