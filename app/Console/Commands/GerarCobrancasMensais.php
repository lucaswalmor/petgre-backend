<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Services\FaturamentoService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GerarCobrancasMensais extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faturamento:gerar-cobrancas-mensais {--mes= : Mês de referência (YYYY-MM, padrão: mês anterior)} {--dry-run : Executar sem criar cobranças}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gera cobranças mensais condicionais para empresas matriz (roda dia 1º às 08:00)';

    /**
     * Execute the console command.
     */
    public function handle(FaturamentoService $faturamentoService)
    {
        $dryRun = $this->option('dry-run');
        $mesReferencia = $this->option('mes');

        // Se não informou mês, usar mês anterior
        if (!$mesReferencia) {
            $mesReferencia = now()->subMonth()->format('Y-m');
        }

        if ($dryRun) {
            $this->info('🔍 MODO DRY-RUN: Nenhuma cobrança será criada');
        }

        $this->info("📅 Processando cobranças para o mês: {$mesReferencia}");
        $this->newLine();

        // Buscar todas as empresas matriz ativas
        $matrizes = Empresa::where('is_matriz', true)
            ->where('ativo', true)
            ->get();

        if ($matrizes->isEmpty()) {
            $this->warn('⚠️ Nenhuma empresa matriz ativa encontrada');
            return;
        }

        $this->info("🏢 Encontradas {$matrizes->count()} empresas matriz para processar");
        $this->newLine();

        $cobrancasGeradas = 0;
        $mesesGratuitos = 0;
        $erros = 0;

        foreach ($matrizes as $matriz) {
            $this->line("🔍 Processando: {$matriz->nome_fantasia} (ID: {$matriz->id})");

            // Contar pedidos do mês
            $contagem = $faturamentoService->contarPedidosMes($matriz->id, $mesReferencia);
            $totalPedidos = $contagem['total_pedidos'];

            $this->line("   📊 Pedidos no mês: {$totalPedidos}");

            if ($totalPedidos <= 15) {
                $this->line("   ✅ Mês gratuito (<=15 pedidos)");
                $mesesGratuitos++;
                continue;
            }

            // Verificar se já existe cobrança
            if ($faturamentoService->existeCobrancaParaMes($matriz->id, $mesReferencia)) {
                $this->line("   ⏭️ Cobrança já existe para este mês");
                continue;
            }

            // Calcular valor
            $calculo = $faturamentoService->calcularValorCobranca($matriz->id);
            $this->line("   💰 Valor a cobrar: R$ " . number_format($calculo['valor_total'], 2, ',', '.'));
            $this->line("   🏪 Filiais ativas: {$calculo['quantidade_filiais']}");

            if (!$dryRun) {
                try {
                    $resultado = $faturamentoService->gerarCobrancaMensal($matriz->id, $mesReferencia);

                    if ($resultado) {
                        $this->line("   ✅ Cobrança gerada: {$resultado['asaas_payment_id']}");
                        $cobrancasGeradas++;
                    } else {
                        $this->line("   ⚠️ Cobrança não gerada (verifique logs)");
                        $erros++;
                    }
                } catch (\Exception $e) {
                    $this->error("   ❌ Erro: {$e->getMessage()}");
                    Log::error('Erro ao gerar cobrança mensal', [
                        'empresa_id' => $matriz->id,
                        'mes_referencia' => $mesReferencia,
                        'erro' => $e->getMessage(),
                    ]);
                    $erros++;
                }
            } else {
                $this->line("   🔍 [DRY-RUN] Cobrança seria gerada");
                $cobrancasGeradas++;
            }
        }

        $this->newLine();
        $this->info('📊 RESUMO DA EXECUÇÃO:');
        $this->info("📅 Mês de referência: {$mesReferencia}");
        $this->info("🏢 Empresas processadas: {$matrizes->count()}");
        $this->info("💰 Cobranças geradas: {$cobrancasGeradas}");
        $this->info("🆓 Meses gratuitos: {$mesesGratuitos}");
        $this->info("❌ Erros: {$erros}");

        if ($dryRun) {
            $this->info('🔍 Modo dry-run - nenhuma alteração foi feita');
        }

        Log::info('GerarCobrancasMensais executado', [
            'mes_referencia' => $mesReferencia,
            'empresas_processadas' => $matrizes->count(),
            'cobrancas_geradas' => $cobrancasGeradas,
            'meses_gratuitos' => $mesesGratuitos,
            'erros' => $erros,
            'dry_run' => $dryRun,
        ]);
    }
}
