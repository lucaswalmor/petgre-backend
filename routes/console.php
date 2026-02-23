<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup diário do banco (envia para R2). Em produção, configure o cron: * * * * * php /caminho/artisan schedule:run
Schedule::command('backup:database')->daily()->at('02:00');
