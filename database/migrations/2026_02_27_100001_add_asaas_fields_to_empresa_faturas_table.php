<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_faturas', function (Blueprint $table) {
            $table->string('asaas_payment_id')->nullable()->unique()->after('usuario_id');
            $table->text('pix_qrcode_base64')->nullable()->after('pago_em');
            $table->text('pix_copia_cola')->nullable()->after('pix_qrcode_base64');
            $table->string('link_fatura')->nullable()->after('pix_copia_cola');
        });

        \DB::statement("ALTER TABLE empresa_faturas MODIFY COLUMN status ENUM('pendente', 'pago', 'vencido', 'cancelado') DEFAULT 'pendente'");
    }

    public function down(): void
    {
        Schema::table('empresa_faturas', function (Blueprint $table) {
            $table->dropColumn(['asaas_payment_id', 'pix_qrcode_base64', 'pix_copia_cola', 'link_fatura']);
        });
        \DB::statement("ALTER TABLE empresa_faturas MODIFY COLUMN status ENUM('pendente', 'pago', 'vencido') DEFAULT 'pendente'");
    }
};
