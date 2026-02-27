<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_faturamento', function (Blueprint $table) {
            $table->string('asaas_customer_id')->nullable()->after('tipo_chave_pix');
            $table->string('asaas_subscription_id')->nullable()->after('asaas_customer_id');
            $table->decimal('valor_atual', 8, 2)->nullable()->after('assinatura_ativa');
            $table->timestamp('data_ativacao')->nullable()->after('valor_atual');
        });
    }

    public function down(): void
    {
        Schema::table('empresa_faturamento', function (Blueprint $table) {
            $table->dropColumn(['asaas_customer_id', 'asaas_subscription_id', 'valor_atual', 'data_ativacao']);
        });
    }
};
