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

        try {
            // Verificar se está rodando dentro de Docker (sem mysqldump local)
            if ($this->isRunningInDocker() && !$this->hasLocalMysqldump()) {
                $this->info('Detectado ambiente Docker, usando container MySQL...');
                $returnCode = $this->dumpUsingDocker($database, $username, $password, $localPath);
            } else {
                // Ambiente local (Windows/Laragon) ou com mysqldump disponível
                $returnCode = $this->dumpUsingLocal($database, $username, $password, $host, $localPath);
            }

            if ($returnCode !== 0) {
                $this->error('mysqldump falhou.');
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
            if (file_exists($localPath)) {
                @unlink($localPath);
            }
        }
    }

    /** Verifica se está rodando dentro de um container Docker */
    private function isRunningInDocker(): bool
    {
        return file_exists('/.dockerenv') || (
            is_file('/proc/1/cgroup') &&
            strpos(file_get_contents('/proc/1/cgroup'), 'docker') !== false
        );
    }

    /** Verifica se mysqldump está disponível localmente */
    private function hasLocalMysqldump(): bool
    {
        exec('which mysqldump 2>/dev/null', $output, $returnCode);
        return $returnCode === 0;
    }

    /** Faz dump usando mysqldump local */
    private function dumpUsingLocal(string $database, string $username, string $password, string $host, string $localPath): int
    {
        $mysqldump = $this->getMysqldumpPath();

        // Arquivo temporário com senha (evita senha no comando / process list)
        $configFile = storage_path('app/.my_backup_' . uniqid() . '.cnf');
        $configContent = "[client]\nuser={$username}\npassword=" . addcslashes($password, '"\\') . "\nhost={$host}\n";
        file_put_contents($configFile, $configContent);
        @chmod($configFile, 0600);

        $cmd = sprintf(
            '%s --defaults-extra-file=%s --skip-lock-tables %s > %s 2>&1',
            $mysqldump,
            escapeshellarg($configFile),
            escapeshellarg($database),
            escapeshellarg($localPath)
        );

        exec($cmd, $output, $returnCode);

        @unlink($configFile);

        return $returnCode;
    }

    /** Faz dump usando container MySQL via Docker */
    private function dumpUsingDocker(string $database, string $username, string $password, string $localPath): int
    {
        // Encontrar o container MySQL
        $mysqlContainer = $this->findMysqlContainer();

        if (!$mysqlContainer) {
            $this->error('Container MySQL não encontrado.');
            return 1;
        }

        $this->info("Usando container MySQL: {$mysqlContainer}");

        // Usar docker exec para rodar mysqldump dentro do container MySQL
        $cmd = sprintf(
            'docker exec %s mysqldump -u %s -p%s --skip-lock-tables %s > %s 2>&1',
            escapeshellarg($mysqlContainer),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($localPath)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 && !empty($output)) {
            $this->error('Erro do mysqldump: ' . implode("\n", $output));
        }

        return $returnCode;
    }

    /** Encontra o nome do container MySQL */
    private function findMysqlContainer(): ?string
    {
        // Tentar encontrar container com nome típico de MySQL
        $possibleNames = [
            'petgre_petgre-mysql',
            'petgre-mysql',
            'mysql',
            'db'
        ];

        foreach ($possibleNames as $name) {
            exec('docker ps --format "{{.Names}}" | grep ' . escapeshellarg($name) . ' 2>/dev/null', $output, $returnCode);
            if ($returnCode === 0 && !empty($output)) {
                return trim($output[0]);
            }
        }

        // Tentar listar todos os containers e encontrar um com "mysql" no nome
        exec('docker ps --format "{{.Names}}" 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0) {
            foreach ($output as $container) {
                if (stripos($container, 'mysql') !== false) {
                    return trim($container);
                }
            }
        }

        return null;
    }

    /** Retorna o executável mysqldump (PATH ou Laragon no Windows). */
    private function getMysqldumpPath(): string
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
