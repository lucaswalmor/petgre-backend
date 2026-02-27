<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_faturamento_pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
            $table->string('mes_referencia', 7);
            $table->unsignedInteger('total_pedidos')->default(0);
            $table->boolean('assinatura_disparada')->default(false);
            $table->timestamps();
            $table->unique(['usuario_id', 'mes_referencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_faturamento_pedidos');
    }
};
