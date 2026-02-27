<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_faturamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
            $table->string('nome_titular');
            $table->string('cpf_cnpj');
            $table->string('email');
            $table->string('telefone');
            $table->string('chave_pix')->nullable();
            $table->enum('tipo_chave_pix', ['cpf', 'cnpj', 'email', 'telefone', 'aleatoria'])->nullable();
            $table->boolean('assinatura_ativa')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_faturamento');
    }
};
