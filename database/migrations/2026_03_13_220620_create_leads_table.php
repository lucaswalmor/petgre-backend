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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email');
            $table->string('whatsapp');
            $table->enum('tipo_empresa', ['petshop', 'clinica_veterinaria', 'banho_tosa', 'outros']);
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('referrer')->nullable();
            $table->timestamp('contato_em')->nullable();
            $table->enum('status', ['novo', 'contatado', 'qualificado', 'descartado'])->default('novo');
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('whatsapp');
            $table->index('status');
            $table->index('utm_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
