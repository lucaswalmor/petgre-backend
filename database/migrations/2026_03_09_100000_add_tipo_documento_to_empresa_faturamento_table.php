<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_faturamento', function (Blueprint $table) {
            $table->enum('tipo_documento_titular', ['cpf', 'cnpj'])->default('cpf')->after('nome_titular');
        });
    }

    public function down(): void
    {
        Schema::table('empresa_faturamento', function (Blueprint $table) {
            $table->dropColumn('tipo_documento_titular');
        });
    }
};
