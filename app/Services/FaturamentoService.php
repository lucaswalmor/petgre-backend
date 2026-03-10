<?php

namespace App\Services;

use App\Mail\FaturamentoAtivadoMail;
use App\Models\Empresa;
use App\Models\EmpresaFatura;
use App\Models\EmpresaFaturamento;
use App\Models\Pedido;
use App\Models\Plano;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FaturamentoService
{
    public function __construct(
        private AsaasService $asaasService,
        private EmailService $emailService
    ) {
    }

    /**
     * Calcular valor da cobrança para uma empresa matriz
     * Fórmula: valor_base + (filiais_ativas × valor_base × 0.5)
     *
     * @param int $empresaMatrizId ID da empresa matriz
     * @return array ['valor_total' => float, 'valor_base' => float, 'quantidade_filiais' => int]
     */
    public function calcularValorCobranca(int $empresaMatrizId): array
    {
        $plano = Plano::withoutTrashed()->where('ativo', true)->first();
        $valorBase = $plano ? (float) $plano->valor : 39.90;

        // Contar filiais ativas da matriz
        $quantidadeFiliais = Empresa::where('empresa_matriz_id', $empresaMatrizId)
            ->where('ativo', true)
            ->where('is_matriz', false)
            ->count();

        $valorTotal = $valorBase * (1 + ($quantidadeFiliais * 0.5));

        return [
            'valor_total' => round($valorTotal, 2),
            'valor_base' => $valorBase,
            'quantidade_filiais' => $quantidadeFiliais,
        ];
    }

    /**
     * Contar pedidos do mês para uma matriz e suas filiais
     *
     * @param int $empresaMatrizId ID da empresa matriz
     * @param string $mesReferencia Formato YYYY-MM
     * @return array ['total_pedidos' => int, 'empresas_consideradas' => array]
     */
    public function contarPedidosMes(int $empresaMatrizId, string $mesReferencia): array
    {
        // Buscar IDs da matriz e todas as filiais ativas
        $idsEmpresas = Empresa::where('id', $empresaMatrizId)
            ->orWhere(function ($query) use ($empresaMatrizId) {
                $query->where('empresa_matriz_id', $empresaMatrizId)
                    ->where('ativo', true);
            })
            ->pluck('id')
            ->toArray();

        // Calcular período do mês de referência
        $dataInicio = Carbon::createFromFormat('Y-m', $mesReferencia)->startOfMonth();
        $dataFim = Carbon::createFromFormat('Y-m', $mesReferencia)->endOfMonth();

        $totalPedidos = Pedido::whereIn('empresa_id', $idsEmpresas)
            ->whereBetween('created_at', [$dataInicio, $dataFim])
            ->count();

        return [
            'total_pedidos' => $totalPedidos,
            'empresas_consideradas' => $idsEmpresas,
        ];
    }

    /**
     * Verificar se já existe cobrança para o mês/empresa
     *
     * @param int $empresaMatrizId
     * @param string $mesReferencia Formato YYYY-MM
     * @return bool
     */
    public function existeCobrancaParaMes(int $empresaMatrizId, string $mesReferencia): bool
    {
        return EmpresaFatura::where('empresa_id', $empresaMatrizId)
            ->where('mes_referencia', $mesReferencia)
            ->exists();
    }

    /**
     * Gerar cobrança mensal condicional para uma empresa matriz
     * Executado pelo cron job no dia 1º de cada mês
     *
     * @param int $empresaMatrizId
     * @param string $mesReferencia Formato YYYY-MM (mês a ser cobrado - normalmente o anterior)
     * @return array|null Dados da cobrança gerada ou null se não gerar
     */
    public function gerarCobrancaMensal(int $empresaMatrizId, string $mesReferencia): ?array
    {
        // Verificar se Asaas está configurado
        if (!$this->asaasService->isConfigured()) {
            Log::warning('FaturamentoService::gerarCobrancaMensal - Asaas não configurado');
            return null;
        }

        // Verificar se matriz existe e está ativa
        $matriz = Empresa::where('id', $empresaMatrizId)
            ->where('is_matriz', true)
            ->first();

        if (!$matriz) {
            Log::warning('FaturamentoService::gerarCobrancaMensal - Matriz não encontrada', [
                'empresa_id' => $empresaMatrizId,
            ]);
            return null;
        }

        // Verificar se já existe cobrança para este mês
        if ($this->existeCobrancaParaMes($empresaMatrizId, $mesReferencia)) {
            Log::info('FaturamentoService::gerarCobrancaMensal - Cobrança já existe', [
                'empresa_id' => $empresaMatrizId,
                'mes_referencia' => $mesReferencia,
            ]);
            return null;
        }

        // Contar pedidos do mês (matriz + filiais)
        $contagem = $this->contarPedidosMes($empresaMatrizId, $mesReferencia);
        $totalPedidos = $contagem['total_pedidos'];

        // Se tiver 15 ou menos pedidos → mês gratuito, sem cobrança
        if ($totalPedidos <= 15) {
            Log::info('FaturamentoService::gerarCobrancaMensal - Mês gratuito (<=15 pedidos)', [
                'empresa_id' => $empresaMatrizId,
                'mes_referencia' => $mesReferencia,
                'total_pedidos' => $totalPedidos,
            ]);
            return null;
        }

        // Calcular valor da cobrança (16+ pedidos)
        $calculo = $this->calcularValorCobranca($empresaMatrizId);
        $valorTotal = $calculo['valor_total'];
        $quantidadeFiliais = $calculo['quantidade_filiais'];

        // Buscar dados de faturamento do master
        $usuarioMaster = User::where('is_master', true)
            ->whereHas('usuarioEmpresas', fn ($q) => $q->where('empresa_id', $empresaMatrizId))
            ->first();

        if (!$usuarioMaster) {
            Log::error('FaturamentoService::gerarCobrancaMensal - Master não encontrado', [
                'empresa_id' => $empresaMatrizId,
            ]);
            return null;
        }

        $faturamento = EmpresaFaturamento::where('usuario_id', $usuarioMaster->id)->first();

        if (!$faturamento || empty($faturamento->nome_titular) || empty($faturamento->cpf_cnpj)) {
            Log::warning('FaturamentoService::gerarCobrancaMensal - Dados de faturamento incompletos', [
                'empresa_id' => $empresaMatrizId,
                'usuario_id' => $usuarioMaster->id,
            ]);
            return null;
        }

        // Criar cliente no Asaas se não existir
        if (empty($faturamento->asaas_customer_id)) {
            $resposta = $this->asaasService->criarCliente([
                'name' => $faturamento->nome_titular,
                'cpfCnpj' => preg_replace('/\D/', '', $faturamento->cpf_cnpj),
                'email' => $faturamento->email,
                'phone' => preg_replace('/\D/', '', $faturamento->telefone ?? ''),
            ]);

            if (empty($resposta['id'])) {
                Log::error('Asaas criarCliente falhou', ['response' => $resposta]);
                return null;
            }

            $faturamento->update(['asaas_customer_id' => $resposta['id']]);
            $faturamento->refresh();
        }

        // Criar cobrança única no Asaas (não assinatura)
        $dueDate = now()->addDays(5)->format('Y-m-d'); // Vencimento em 5 dias
        $descricao = "PetGre - Cobrança mensal {$mesReferencia} ({$totalPedidos} pedidos)";

        $respostaCobranca = $this->asaasService->criarCobrancaUnica(
            $faturamento->asaas_customer_id,
            $valorTotal,
            $dueDate,
            $descricao
        );

        if (empty($respostaCobranca['id'])) {
            Log::error('Asaas criarCobrancaUnica falhou', ['response' => $respostaCobranca]);
            return null;
        }

        // Salvar fatura no banco
        $fatura = EmpresaFatura::create([
            'usuario_id' => $usuarioMaster->id,
            'empresa_id' => $empresaMatrizId,
            'asaas_payment_id' => $respostaCobranca['id'],
            'mes_referencia' => $mesReferencia,
            'valor' => $valorTotal,
            'status' => 'pendente',
            'vencimento' => $dueDate,
            'quantidade_pedidos' => $totalPedidos,
            'quantidade_filiais' => $quantidadeFiliais,
            'pix_qrcode_base64' => $respostaCobranca['pixTransaction']['encodedImage'] ?? null,
            'pix_copia_cola' => $respostaCobranca['pixTransaction']['payload'] ?? null,
            'link_fatura' => $respostaCobranca['invoiceUrl'] ?? null,
        ]);

        // Enviar email de notificação
        try {
            $this->emailService->sendMailable(
                $usuarioMaster->email,
                new FaturamentoAtivadoMail($usuarioMaster, $valorTotal, $dueDate, $mesReferencia, $totalPedidos, $quantidadeFiliais)
            );

            if ($faturamento->email && $faturamento->email !== $usuarioMaster->email) {
                $this->emailService->sendMailable(
                    $faturamento->email,
                    new FaturamentoAtivadoMail($usuarioMaster, $valorTotal, $dueDate, $mesReferencia, $totalPedidos, $quantidadeFiliais)
                );
            }
        } catch (\Exception $e) {
            Log::error('Erro ao enviar email de cobrança', [
                'empresa_id' => $empresaMatrizId,
                'erro' => $e->getMessage(),
            ]);
        }

        Log::info('FaturamentoService::gerarCobrancaMensal - Cobrança gerada com sucesso', [
            'empresa_id' => $empresaMatrizId,
            'mes_referencia' => $mesReferencia,
            'fatura_id' => $fatura->id,
            'asaas_payment_id' => $respostaCobranca['id'],
            'valor' => $valorTotal,
            'total_pedidos' => $totalPedidos,
        ]);

        return [
            'fatura_id' => $fatura->id,
            'asaas_payment_id' => $respostaCobranca['id'],
            'valor' => $valorTotal,
            'total_pedidos' => $totalPedidos,
            'quantidade_filiais' => $quantidadeFiliais,
        ];
    }

    /**
     * @deprecated Não usar - método legado do modelo de assinatura recorrente
     * Removido no novo modelo de cobrança condicional mensal
     */
    public function contabilizarPedido(int $empresaId): void
    {
        // Método descontinuado - não faz nada no novo modelo
        // A contagem de pedidos agora é feita pelo cron job mensal
        Log::info('FaturamentoService::contabilizarPedido - Método descontinuado, ignorando', [
            'empresa_id' => $empresaId,
        ]);
    }

    /**
     * @deprecated Usar gerarCobrancaMensal() no novo modelo
     */
    public function calcularValorPlano(int $usuarioId): float
    {
        // Buscar empresa matriz do usuário
        $empresaMatriz = Empresa::whereHas('usuarioEmpresas', fn ($q) => $q->where('usuario_id', $usuarioId))
            ->where('is_matriz', true)
            ->first();

        if (!$empresaMatriz) {
            return 39.90;
        }

        $calculo = $this->calcularValorCobranca($empresaMatriz->id);
        return $calculo['valor_total'];
    }

    /**
     * @deprecated Usar gerarCobrancaMensal() no novo modelo
     */
    public function dispararAssinatura(int $usuarioMasterId): void
    {
        Log::warning('FaturamentoService::dispararAssinatura - Método descontinuado', [
            'usuario_id' => $usuarioMasterId,
        ]);
    }

    /**
     * @deprecated Não usado no novo modelo de cobrança condicional
     */
    public function recalcularValorAssinatura(int $usuarioMasterId): void
    {
        Log::warning('FaturamentoService::recalcularValorAssinatura - Método descontinuado', [
            'usuario_id' => $usuarioMasterId,
        ]);
    }
}
