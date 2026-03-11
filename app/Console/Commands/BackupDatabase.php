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

    /** Faz dump usando container MySQL via Docker ou conexão direta */
    private function dumpUsingDocker(string $database, string $username, string $password, string $localPath): int
    {
        // Verificar se docker está disponível (pode não estar dentro do container)
        exec('which docker 2>/dev/null', $dockerCheck, $dockerCheckCode);

        if ($dockerCheckCode !== 0) {
            // Docker não disponível - estamos provavelmente dentro do container
            // Tentar conexão direta ao MySQL usando as credenciais do Laravel
            $this->info('Docker não disponível, usando conexão direta ao MySQL...');
            return $this->dumpUsingDirectConnection($database, $username, $password, $localPath);
        }

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

    /** Faz dump usando conexão direta PDO (quando docker não está disponível) */
    private function dumpUsingDirectConnection(string $database, string $username, string $password, string $localPath): int
    {
        $host = config('database.connections.mysql.host', 'mysql');
        $port = config('database.connections.mysql.port', 3306);

        try {
            $this->info("Conectando ao MySQL em {$host}:{$port}...");

            // Criar conexão PDO
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
            ]);

            // Abrir arquivo para escrita
            $fp = fopen($localPath, 'w');
            if (!$fp) {
                $this->error('Não foi possível criar arquivo de backup.');
                return 1;
            }

            // Escrever header
            fwrite($fp, "-- Backup gerado em " . date('Y-m-d H:i:s') . "\n");
            fwrite($fp, "-- Database: {$database}\n\n");
            fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            // Pegar todas as tabelas
            $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $this->line("Exportando tabela: {$table}");

                // Estrutura da tabela
                $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
                fwrite($fp, "\n-- Estrutura da tabela `{$table}`\n");
                fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($fp, $createTable['Create Table'] . ";\n\n");

                // Dados da tabela
                $rows = $pdo->query("SELECT * FROM `{$table}`", \PDO::FETCH_ASSOC);
                $rowCount = 0;

                foreach ($rows as $row) {
                    if ($rowCount === 0) {
                        fwrite($fp, "-- Dados da tabela `{$table}`\n");
                        fwrite($fp, "INSERT INTO `{$table}` VALUES\n");
                    } else {
                        fwrite($fp, ",\n");
                    }

                    $values = array_map(function ($value) {
                        if ($value === null) {
                            return 'NULL';
                        }
                        return "'" . addslashes($value) . "'";
                    }, array_values($row));

                    fwrite($fp, "(" . implode(", ", $values) . ")");
                    $rowCount++;

                    // A cada 1000 registros, fecha o INSERT e abre novo
                    if ($rowCount % 1000 === 0) {
                        fwrite($fp, ";\n");
                        fwrite($fp, "INSERT INTO `{$table}` VALUES\n");
                        $rowCount = 0;
                    }
                }

                if ($rowCount > 0) {
                    fwrite($fp, ";\n");
                }
            }

            // Footer
            fwrite($fp, "\nSET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fp);

            $this->info("Backup concluído via PHP PDO.");
            return 0;

        } catch (\PDOException $e) {
            $this->error('Erro de conexão MySQL: ' . $e->getMessage());
            $this->error('Verifique se o host ' . $host . ' está acessível do container.');
            return 1;
        } catch (\Exception $e) {
            $this->error('Erro: ' . $e->getMessage());
            return 1;
        }
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
