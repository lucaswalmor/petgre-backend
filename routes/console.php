<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup diário do banco (envia para R2). Em produção, configure o cron: * * * * * php /caminho/artisan schedule:run
Schedule::command('backup:database')->daily()->at('02:00');

// Gerar cobranças mensais condicionais no dia 1 de cada mês às 08:00
// Verifica pedidos do mês anterior e gera cobrança se >= 16 pedidos
Schedule::command('faturamento:gerar-cobrancas-mensais')->monthlyOn(1, '08:00');

// Verificar empresas inadimplentes diariamente às 09:00
// Desativa matriz + filiais se fatura vencida há 5+ dias
Schedule::command('faturamento:desativar-empresas-inadimplentes')->daily()->at('09:00');

// Enviar avisos de vencimento próximo diariamente às 08:00
Schedule::command('faturamento:avisar-vencimento-proximo')->daily()->at('08:00');
