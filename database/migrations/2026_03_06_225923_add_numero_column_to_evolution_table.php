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
        Schema::table('empresa_evolution_whatsapp', function (Blueprint $table) {
            if (!Schema::hasColumn('empresa_evolution_whatsapp', 'numero')) {
                $table->string('numero', 20)->nullable()->after('instance_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresa_evolution_whatsapp', function (Blueprint $table) {
            if (Schema::hasColumn('empresa_evolution_whatsapp', 'numero')) {
                $table->dropColumn('numero');
            }
        });
    }
};
