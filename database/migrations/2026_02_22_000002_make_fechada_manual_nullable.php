<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('empresas', 'fechada_manual')) {
            return;
        }
        if (\DB::connection()->getDriverName() === 'mysql') {
            $row = \DB::selectOne("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresas' AND COLUMN_NAME = 'fechada_manual'");
            if ($row && $row->IS_NULLABLE === 'YES') {
                return;
            }
        }
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('fechada_manual')->nullable()->change();
        });
        \DB::table('empresas')->where('fechada_manual', false)->update(['fechada_manual' => null]);
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('fechada_manual')->default(false)->nullable(false)->change();
        });
    }
};
