<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PullProductionDatabase extends Command
{
    protected $signature = 'db:pull-production
                            {--no-clean : Não apaga o arquivo de dump após importar}
                            {--dump-only : Apenas gera o dump, sem importar}';

    protected $description = 'Baixa o banco de produção e importa no ambiente local';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Este comando não pode ser executado em produção!');
            return Command::FAILURE;
        }

        $this->info('🚀 Iniciando pull do banco de produção...');

        $prodHost     = env('PROD_DB_HOST');
        $prodPort     = env('PROD_DB_PORT', 3306);
        $prodUser     = env('PROD_DB_USERNAME');
        $prodPassword = env('PROD_DB_PASSWORD');
        $prodDatabase = env('PROD_DB_DATABASE');

        if (!$prodHost || !$prodUser || !$prodDatabase) {
            $this->error('Variáveis de produção não configuradas no .env (PROD_DB_HOST, PROD_DB_USERNAME, PROD_DB_DATABASE)');
            return Command::FAILURE;
        }

        $dumpFile = storage_path('app/production_dump_' . date('Y_m_d_His') . '.sql');

        $mysqldump = $this->getMysqldumpPath();
        if (!$mysqldump) {
            $this->error('mysqldump não encontrado. No Windows/Laragon, verifique se MySQL está em C:\laragon\bin\mysql.');
            return Command::FAILURE;
        }

        // ── 1. DUMP ──────────────────────────────────────────────────────────
        $this->info('📦 Gerando dump do banco de produção...');

        $passwordFlag = $prodPassword ? "-p" . escapeshellarg($prodPassword) : '';

        $stderrRedirect = PHP_OS_FAMILY === 'Windows' ? '2>nul' : '2>/dev/null';
        $dumpCommand = sprintf(
            '%s -h %s -P %s -u %s %s --single-transaction --no-tablespaces %s > %s %s',
            $mysqldump,
            escapeshellarg($prodHost),
            escapeshellarg($prodPort),
            escapeshellarg($prodUser),
            $passwordFlag,
            escapeshellarg($prodDatabase),
            escapeshellarg($dumpFile),
            $stderrRedirect
        );

        exec($dumpCommand, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($dumpFile) || filesize($dumpFile) === 0) {
            $this->error('Falha ao gerar o dump! Saída: ' . implode("\n", $output));
            return Command::FAILURE;
        }

        $sizeMb = round(filesize($dumpFile) / 1024 / 1024, 2);
        $this->info("✅ Dump gerado com sucesso! ({$sizeMb} MB) → {$dumpFile}");

        if ($this->option('dump-only')) {
            $this->info('Opção --dump-only ativa. Importação pulada.');
            return Command::SUCCESS;
        }

        // ── 2. CONFIRMAÇÃO ───────────────────────────────────────────────────
        // Importamos sempre para um banco com o MESMO NOME da produção no localhost (ex: lksoft04_pet).
        // Assim o banco de desenvolvimento (DB_DATABASE, ex: petgre) permanece intacto.
        $importTargetDb = $prodDatabase;
        $localDevDb     = env('DB_DATABASE');
        if (!$this->confirm("⚠️  Será criado/sobrescrito o banco '{$importTargetDb}' no localhost com os dados de produção. O banco '{$localDevDb}' (seu desenvolvimento) NÃO será alterado. Continuar?")) {
            $this->warn('Operação cancelada.');
            @unlink($dumpFile);
            return Command::SUCCESS;
        }

        // ── 3. CRIAR BANCO (nome de produção) NO LOCALHOST SE NÃO EXISTIR ─────
        $mysql         = $this->getMysqlPath();
        $localUser     = env('DB_USERNAME');
        $localPassword = env('DB_PASSWORD');
        $localHost     = env('DB_HOST', '127.0.0.1');
        $localPort     = env('DB_PORT', 3306);
        $localPassFlag = $localPassword ? "-p" . escapeshellarg($localPassword) : '';

        $createDbSql = 'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $importTargetDb) . '`';
        $createDbCommand = sprintf(
            '%s -h %s -P %s -u %s %s -e %s 2>&1',
            $mysql,
            escapeshellarg($localHost),
            escapeshellarg($localPort),
            escapeshellarg($localUser),
            $localPassFlag,
            escapeshellarg($createDbSql)
        );
        exec($createDbCommand, $createDbOut, $createDbCode);
        if ($createDbCode !== 0) {
            $this->error('Falha ao criar banco no localhost. Saída: ' . implode("\n", $createDbOut));
            return Command::FAILURE;
        }
        $this->line('Banco "' . $importTargetDb . '" no localhost garantido (criado se não existia).');

        // ── 4. IMPORTAR NO BANCO (MESMO NOME DA PRODUÇÃO) NO LOCALHOST ─────────
        $this->info('💾 Importando no banco local "' . $importTargetDb . '"...');

        $importCommand = sprintf(
            '%s -h %s -P %s -u %s %s %s < %s 2>&1',
            $mysql,
            escapeshellarg($localHost),
            escapeshellarg($localPort),
            escapeshellarg($localUser),
            $localPassFlag,
            escapeshellarg($importTargetDb),
            escapeshellarg($dumpFile)
        );

        exec($importCommand, $outputImport, $returnCodeImport);

        if ($returnCodeImport !== 0) {
            $this->error('Falha ao importar! Saída: ' . implode("\n", $outputImport));
            return Command::FAILURE;
        }

        // ── 5. LIMPEZA ───────────────────────────────────────────────────────
        if (!$this->option('no-clean')) {
            @unlink($dumpFile);
            $this->line('🗑  Arquivo de dump removido.');
        }

        $this->info('✅ Dados de produção importados no banco "' . $importTargetDb . '" no localhost. O banco "' . $localDevDb . '" (desenvolvimento) permanece intacto.');

        return Command::SUCCESS;
    }

    /** Retorna o executável mysqldump (PATH ou Laragon no Windows). */
    private function getMysqldumpPath(): ?string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return 'mysqldump';
        }
        $laragonBase = 'C:\\laragon\\bin\\mysql';
        if (!is_dir($laragonBase)) {
            return 'mysqldump';
        }
        $dirs = @scandir($laragonBase) ?: [];
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            $exe = $laragonBase . '\\' . $dir . '\\bin\\mysqldump.exe';
            if (is_file($exe)) {
                return '"' . $exe . '"';
            }
        }
        return 'mysqldump';
    }

    /** Retorna o executável mysql (PATH ou Laragon no Windows). */
    private function getMysqlPath(): string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return 'mysql';
        }
        $laragonBase = 'C:\\laragon\\bin\\mysql';
        if (!is_dir($laragonBase)) {
            return 'mysql';
        }
        $dirs = @scandir($laragonBase) ?: [];
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            $exe = $laragonBase . '\\' . $dir . '\\bin\\mysql.exe';
            if (is_file($exe)) {
                return '"' . $exe . '"';
            }
        }
        return 'mysql';
    }
}
