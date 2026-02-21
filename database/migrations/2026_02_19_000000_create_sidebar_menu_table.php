<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sidebar_menu', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('chave', 80)->unique()->comment('Identificador único para o seeder não duplicar');
            $table->string('label', 100);
            $table->string('path', 255)->nullable()->comment('Null = item apenas expandível (pai)');
            $table->string('icon', 60)->default('pi pi-circle');
            $table->string('permission_slug', 80)->nullable()->comment('Null = visível para todos autenticados');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('sidebar_menu')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sidebar_menu');
    }
};
