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
            $this->error('❌ Variáveis de produção não configuradas no .env');
            $this->line('   Verifique se existem:');
            $this->line('   - PROD_DB_HOST');
            $this->line('   - PROD_DB_USERNAME');
            $this->line('   - PROD_DB_DATABASE');
            return Command::FAILURE;
        }

        // Verificar se porta está acessível
        $this->info('🔍 Verificando conectividade na porta ' . $prodPort . '...');
        $connection = @fsockopen($prodHost, $prodPort, $errno, $errstr, 5);
        if (!$connection) {
            $this->error('❌ Não foi possível conectar em ' . $prodHost . ':' . $prodPort);
            $this->error('   Erro: ' . $errstr . ' (código ' . $errno . ')');
            $this->line('');
            $this->warn('💡 Possíveis causas:');
            $this->warn('   1. Firewall bloqueando conexão externa na porta ' . $prodPort);
            $this->warn('   2. MySQL não está rodando ou não aceita conexões externas');
            $this->warn('   3. Host ou porta incorretos no .env');
            $this->warn('   4. Hostinger pode exigir whitelist de IP ou usar SSH tunnel');
            return Command::FAILURE;
        }
        fclose($connection);
        $this->info('✅ Porta ' . $prodPort . ' está acessível!');

        $dumpFile = storage_path('app/production_dump_' . date('Y_m_d_His') . '.sql');

        $mysqldump = $this->getMysqldumpPath();
        if (!$mysqldump) {
            $this->error('mysqldump não encontrado. No Windows/Laragon, verifique se MySQL está em C:\laragon\bin\mysql.');
            return Command::FAILURE;
        }

        // ── 1. DUMP ──────────────────────────────────────────────────────────
        $this->info('📦 Gerando dump do banco de produção...');

        $passwordFlag = $prodPassword ? "-p" . escapeshellarg($prodPassword) : '';

        // Mostrar comando (sem senha) para debug
        $debugCommand = sprintf(
            '%s -h %s -P %s -u %s -p*** --skip-lock-tables --no-tablespaces %s > %s',
            $mysqldump,
            $prodHost,
            $prodPort,
            $prodUser,
            $prodDatabase,
            $dumpFile
        );
        $this->line('🔧 Comando: ' . $debugCommand);

        // Testar conexão primeiro com mysql
        $this->info('🔍 Testando conexão com o banco de produção...');
        $mysql = $this->getMysqlPath();
        $testCmd = sprintf(
            '%s -h %s -P %s -u %s %s -e "SELECT 1" 2>&1',
            $mysql,
            escapeshellarg($prodHost),
            escapeshellarg($prodPort),
            escapeshellarg($prodUser),
            $passwordFlag
        );
        exec($testCmd, $testOutput, $testCode);

        if ($testCode !== 0) {
            $this->error('❌ Falha na conexão com o banco de produção!');
            $this->error('   Host: ' . $prodHost);
            $this->error('   Porta: ' . $prodPort);
            $this->error('   Usuário: ' . $prodUser);
            $this->error('   Erro: ' . implode("\n", $testOutput));
            $this->line('');
            $this->warn('💡 Verifique se:');
            $this->warn('   1. As credenciais PROD_DB_* no .env estão corretas');
            $this->warn('   2. O IP da sua máquina está liberado no firewall do banco');
            $this->warn('   3. O banco está acessível externamente (bind-address)');
            return Command::FAILURE;
        }
        $this->info('✅ Conexão OK!');

        // Verificar se o banco existe e usuário tem permissão
        $this->info('🔍 Verificando acesso ao banco "' . $prodDatabase . '"...');
        $checkDbCmd = sprintf(
            '%s -h %s -P %s -u %s %s -e "SHOW DATABASES LIKE \'%s\'" 2>&1',
            $mysql,
            escapeshellarg($prodHost),
            escapeshellarg($prodPort),
            escapeshellarg($prodUser),
            $passwordFlag,
            $prodDatabase
        );
        exec($checkDbCmd, $dbOutput, $dbCode);

        if ($dbCode !== 0 || empty($dbOutput) || count($dbOutput) < 2) {
            $this->error('❌ Banco de dados "' . $prodDatabase . '" não encontrado ou sem permissão!');
            $this->error('   Erro: ' . implode("\n", $dbOutput));
            return Command::FAILURE;
        }
        $this->info('✅ Banco encontrado!');

        // Criar arquivo de erro temporário para capturar stderr
        $errorFile = storage_path('app/mysqldump_error_' . date('Y_m_d_His') . '.txt');

        // Usar --skip-lock-tables em vez de --single-transaction para evitar necessidade de RELOAD privilege
        $dumpCommand = sprintf(
            '%s -h %s -P %s -u %s %s --skip-lock-tables --no-tablespaces %s > %s 2> %s',
            $mysqldump,
            escapeshellarg($prodHost),
            escapeshellarg($prodPort),
            escapeshellarg($prodUser),
            $passwordFlag,
            escapeshellarg($prodDatabase),
            escapeshellarg($dumpFile),
            escapeshellarg($errorFile)
        );

        $this->line('🔧 Executando mysqldump...');
        exec($dumpCommand, $output, $returnCode);

        // Ler mensagens de erro
        $errorOutput = '';
        if (file_exists($errorFile)) {
            $errorOutput = file_get_contents($errorFile);
            @unlink($errorFile);
        }

        if ($returnCode !== 0 || !file_exists($dumpFile) || filesize($dumpFile) === 0) {
            $this->error('❌ Falha ao gerar o dump!');
            $this->error('   Código de retorno: ' . $returnCode);

            if (!empty($errorOutput)) {
                $this->error('   Erro do mysqldump:');
                $this->line('   ' . str_replace("\n", "\n   ", $errorOutput));
            } elseif (!empty($output)) {
                $this->error('   Saída: ' . implode("\n", $output));
            }

            // Verificar permissões do usuário
            $this->line('');
            $this->warn('💡 Diagnóstico:');
            $this->warn('   O usuário pode não ter permissões suficientes para exportar o banco.');
            $this->warn('   Verifique se o usuário tem permissão SELECT em todas as tabelas.');

            if (file_exists($dumpFile)) {
                @unlink($dumpFile);
            }
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
