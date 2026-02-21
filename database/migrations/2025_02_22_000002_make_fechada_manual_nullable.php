<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
