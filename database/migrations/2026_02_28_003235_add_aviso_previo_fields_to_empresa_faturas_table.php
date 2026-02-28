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
            $table->boolean('aviso_previo_enviado')->default(false)->after('link_fatura');
            $table->timestamp('aviso_previo_enviado_em')->nullable()->after('aviso_previo_enviado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresa_faturas', function (Blueprint $table) {
            $table->dropColumn(['aviso_previo_enviado', 'aviso_previo_enviado_em']);
        });
    }
};
