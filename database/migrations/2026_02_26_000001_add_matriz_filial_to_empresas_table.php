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
        Schema::table('empresas', function (Blueprint $table) {
            $table->foreignId('empresa_matriz_id')->nullable()->after('id')->constrained('empresas')->onDelete('restrict');
            $table->boolean('is_matriz')->default(true)->after('empresa_matriz_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropForeign(['empresa_matriz_id']);
            $table->dropColumn('is_matriz');
        });
    }
};
