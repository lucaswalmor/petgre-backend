<?php

namespace App\Console\Commands;

use App\Mail\AssinaturaInativaMail;
use App\Models\EmpresaFatura;
use App\Models\User;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AvisarVencimentoProximo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faturamento:avisar-vencimento-proximo {--dry-run : Executar sem enviar emails}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia lembretes para clientes com faturas que vencem em 3 dias';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 MODO DRY-RUN: Nenhum email será enviado');
        }

        $this->info('🔍 Buscando faturas que vencem em 3 dias...');

        // Buscar faturas que vencem exatamente em 3 dias
        $dataLimite = Carbon::now()->addDays(3)->endOfDay();
        $dataInicio = Carbon::now()->addDays(3)->startOfDay();

        $faturas = EmpresaFatura::where('status', 'pendente')
            ->whereBetween('vencimento', [$dataInicio, $dataLimite])
            ->where('aviso_previo_enviado', false)
            ->get();

        if ($faturas->isEmpty()) {
            $this->info('✅ Nenhuma fatura vence em 3 dias');
            return;
        }

        $this->info("📧 Encontradas {$faturas->count()} faturas para notificar");

        $emailsEnviados = 0;

        foreach ($faturas as $fatura) {
            $this->line("📧 Processando fatura ID {$fatura->id} (Usuário: {$fatura->usuario_id})");

            $usuario = User::find($fatura->usuario_id);
            if (!$usuario) {
                $this->warn("   ⚠️  Usuário {$fatura->usuario_id} não encontrado");
                continue;
            }

            if (!$dryRun) {
                try {
                    app(EmailService::class)->sendMailable(
                        $usuario->email,
                        new AssinaturaInativaMail(
                            $usuario,
                            (float) $fatura->valor,
                            $fatura->vencimento?->format('d/m/Y') ?? '',
                            $fatura->link_fatura,
                            $fatura->pix_copia_cola,
                            'aviso_previo'
                        )
                    );

                    // Registrar que o aviso foi enviado hoje
                    $fatura->update([
                        'aviso_previo_enviado_em' => now(),
                        'aviso_previo_enviado' => true
                    ]);

                    $emailsEnviados++;
                    $this->line("   ✅ Email enviado para {$usuario->email}");

                    Log::info('Email de aviso prévio de vencimento enviado', [
                        'usuario_id' => $usuario->id,
                        'fatura_id' => $fatura->id,
                        'email' => $usuario->email,
                        'vencimento' => $fatura->vencimento
                    ]);

                } catch (\Exception $e) {
                    $this->error("   ❌ Erro ao enviar email: {$e->getMessage()}");
                    Log::error('Erro ao enviar email de aviso prévio', [
                        'usuario_id' => $usuario->id,
                        'fatura_id' => $fatura->id,
                        'erro' => $e->getMessage()
                    ]);
                }
            } else {
                $this->line("   🔍 [DRY-RUN] Email seria enviado para {$usuario->email}");
            }
        }

        // Resumo final
        $this->newLine();
        $this->info("📊 RESUMO DA EXECUÇÃO:");

        if ($dryRun) {
            $this->info("🔍 Modo dry-run - nenhum email enviado");
        } else {
            $this->info("📧 Emails enviados: {$emailsEnviados}");
        }

        $this->info("📄 Faturas processadas: {$faturas->count()}");
    }
}
