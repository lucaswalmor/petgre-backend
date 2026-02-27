<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_faturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
            $table->string('mes_referencia', 7); // YYYY-MM
            $table->decimal('valor', 10, 2);
            $table->enum('status', ['pendente', 'pago', 'vencido']);
            $table->date('vencimento');
            $table->date('pago_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_faturas');
    }
};
