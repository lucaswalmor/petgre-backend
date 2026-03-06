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
        Schema::create('empresa_evolution_whatsapp', function (Blueprint $tabela) {
            $tabela->id();
            $tabela->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $tabela->string('instance_name')->unique();
            $tabela->string('numero', 20)->nullable();
            $tabela->string('status')->default('close');
            $tabela->timestamp('conectado_em')->nullable();
            $tabela->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresa_evolution_whatsapp');
    }
};
