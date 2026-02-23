<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database';
    protected $description = 'Faz backup do banco e envia para o R2';

    public function handle()
    {
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host', '127.0.0.1');

        $date = now()->format('Y-m-d_H-i-s');
        $projectSlug = Str::slug(config('app.name', 'laravel'));
        $fileName = "backups/{$projectSlug}/{$database}_{$date}.sql";
        $localPath = storage_path("app/{$database}_{$date}.sql");

        // Arquivo temporário com senha (evita senha no comando / process list)
        $configFile = storage_path('app/.my_backup_' . uniqid() . '.cnf');
        $configContent = "[client]\nuser={$username}\npassword=" . addcslashes($password, '"\\') . "\nhost={$host}\n";
        file_put_contents($configFile, $configContent);
        @chmod($configFile, 0600);

        $mysqldump = $this->getMysqldumpPath();
        if (!$mysqldump) {
            $this->error('mysqldump não encontrado. No Laragon/Windows, verifique se MySQL está em C:\laragon\bin\mysql.');
            return Command::FAILURE;
        }

        try {
            // Gerar dump (--defaults-extra-file evita senha na linha de comando)
            $cmd = sprintf(
                '%s --defaults-extra-file=%s %s > %s',
                $mysqldump,
                escapeshellarg($configFile),
                escapeshellarg($database),
                escapeshellarg($localPath)
            );
            exec($cmd, $output, $returnCode);

            @unlink($configFile);

            if ($returnCode !== 0) {
                $this->error('mysqldump falhou. Verifique credenciais e se o MySQL do Laragon está ativo.');
                return Command::FAILURE;
            }

            if (!file_exists($localPath) || filesize($localPath) === 0) {
                $this->error('Arquivo de backup vazio ou não gerado.');
                return Command::FAILURE;
            }

            // Enviar para R2
            Storage::disk('r2')->put($fileName, fopen($localPath, 'r'));

            $this->info("Backup enviado com sucesso: {$fileName}");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Erro no backup: ' . $e->getMessage());
            return Command::FAILURE;
        } finally {
            if (file_exists($configFile)) {
                @unlink($configFile);
            }
            if (file_exists($localPath)) {
                @unlink($localPath);
            }
        }
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
}
