<?php

namespace App\Console\Commands;

use App\Mail\AssinaturaInativaMail;
use App\Models\Empresa;
use App\Models\EmpresaFatura;
use App\Models\User;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DesativarEmpresasInadimplentes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faturamento:desativar-empresas-inadimplentes {--dry-run : Executar sem fazer alterações}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Desativa empresas com faturas vencidas há mais de 5 dias e envia notificações';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 MODO DRY-RUN: Nenhuma alteração será feita');
        }

        $this->info('🔍 Buscando faturas vencidas há mais de 5 dias...');

        // Buscar faturas vencidas com status 'vencido' há mais de 5 dias
        $faturasVencidas = EmpresaFatura::where('status', 'vencido')
            ->whereNotNull('vencimento')
            ->whereRaw('DATEDIFF(CURDATE(), vencimento) >= 5')
            ->get();

        if ($faturasVencidas->isEmpty()) {
            $this->info('✅ Nenhuma fatura vencida há mais de 5 dias encontrada');
            return;
        }

        $this->info("📊 Encontradas {$faturasVencidas->count()} faturas vencidas");

        $empresasDesativadas = 0;
        $emailsEnviados = 0;

        foreach ($faturasVencidas as $fatura) {
            $this->line("🔍 Processando fatura ID {$fatura->id} (Usuário: {$fatura->usuario_id})");

            // Verificar se já foi processada (empresas já desativadas)
            $empresaIds = DB::table('usuarios_empresas')
                ->where('usuario_id', $fatura->usuario_id)
                ->pluck('empresa_id');

            $empresasAtivas = Empresa::whereIn('id', $empresaIds)
                ->where('ativo', true)
                ->count();

            if ($empresasAtivas === 0) {
                $this->line("   ⏭️  Empresas já estão desativadas, pulando...");
                continue;
            }

            $this->line("   ⚠️  {$empresasAtivas} empresa(s) ativa(s) encontrada(s)");

            if (!$dryRun) {
                // Desativar empresas
                Empresa::whereIn('id', $empresaIds)->update(['ativo' => false]);
                $empresasDesativadas += $empresasAtivas;

                // Enviar email de notificação
                $usuario = User::find($fatura->usuario_id);
                if ($usuario) {
                    try {
                        app(EmailService::class)->sendMailable(
                            $usuario->email,
                            new AssinaturaInativaMail(
                                $usuario,
                                (float) $fatura->valor,
                                $fatura->vencimento?->format('d/m/Y') ?? '',
                                $fatura->link_fatura,
                                $fatura->pix_copia_cola
                            )
                        );
                        $emailsEnviados++;
                        $this->line("   📧 Email enviado para {$usuario->email}");
                    } catch (\Exception $e) {
                        $this->error("   ❌ Erro ao enviar email: {$e->getMessage()}");
                        Log::error('Erro ao enviar email de empresa desativada', [
                            'usuario_id' => $usuario->id,
                            'fatura_id' => $fatura->id,
                            'erro' => $e->getMessage()
                        ]);
                    }
                }

                // Log da ação
                Log::info('Empresas desativadas por inadimplência', [
                    'usuario_id' => $fatura->usuario_id,
                    'fatura_id' => $fatura->id,
                    'empresas_desativadas' => $empresaIds->toArray(),
                    'valor' => $fatura->valor,
                    'dias_atraso' => Carbon::parse($fatura->vencimento)->diffInDays(now())
                ]);

                $this->line("   ✅ Empresas desativadas: " . implode(', ', $empresaIds->toArray()));
            } else {
                $this->line("   🔍 [DRY-RUN] Empresas seriam desativadas: " . implode(', ', $empresaIds->toArray()));
            }
        }

        // Resumo final
        $this->newLine();
        $this->info("📊 RESUMO DA EXECUÇÃO:");

        if ($dryRun) {
            $this->info("🔍 Modo dry-run - nenhuma alteração feita");
        } else {
            $this->info("🏢 Empresas desativadas: {$empresasDesativadas}");
            $this->info("📧 Emails enviados: {$emailsEnviados}");
        }

        $this->info("📄 Faturas processadas: {$faturasVencidas->count()}");
    }
}
