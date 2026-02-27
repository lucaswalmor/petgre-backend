<?php

namespace App\Services;

use App\Mail\FaturamentoAtivadoMail;
use App\Models\Empresa;
use App\Models\EmpresaFaturamento;
use App\Models\Plano;
use App\Models\User;
use App\Models\UsuarioFaturamentoPedidos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FaturamentoService
{
    public function __construct(
        private AsaasService $asaasService,
        private EmailService $emailService
    ) {
    }

    public function calcularValorPlano(int $usuarioId): float
    {
        $plano = Plano::withoutTrashed()->where('ativo', true)->first();
        $valorBase = $plano ? (float) $plano->valor : 39.90;

        $empresaIds = DB::table('usuarios_empresas')->where('usuario_id', $usuarioId)->pluck('empresa_id');
        $quantidadeFiliais = Empresa::whereIn('id', $empresaIds)->where('is_matriz', false)->count();

        $valor = $valorBase * (1 + ($quantidadeFiliais * 0.5));
        return round($valor, 2);
    }

    public function contabilizarPedido(int $empresaId): void
    {
        $master = User::where('is_master', true)
            ->whereHas('usuarioEmpresas', fn ($q) => $q->where('empresa_id', $empresaId))
            ->first();
        if (!$master) {
            return;
        }

        $empresa = Empresa::find($empresaId);
        if (!$empresa || !$empresa->ativo) {
            return;
        }

        $mesRef = now()->format('Y-m');
        $registro = UsuarioFaturamentoPedidos::firstOrCreate(
            ['usuario_id' => $master->id, 'mes_referencia' => $mesRef],
            ['total_pedidos' => 0, 'assinatura_disparada' => false]
        );
        $registro->increment('total_pedidos');

        if ($registro->total_pedidos >= 30 && !$registro->assinatura_disparada) {
            $this->dispararAssinatura($master->id);
            $registro->update(['assinatura_disparada' => true]);
        }
    }

    public function dispararAssinatura(int $usuarioMasterId): void
    {
        // Verificar se Asaas está configurado
        if (!$this->asaasService->isConfigured()) {
            Log::warning('FaturamentoService::dispararAssinatura - Asaas não configurado, pulando assinatura');
            return;
        }

        $faturamento = EmpresaFaturamento::where('usuario_id', $usuarioMasterId)->first();
        if (!$faturamento || empty($faturamento->nome_titular) || empty($faturamento->cpf_cnpj)) {
            Log::warning('FaturamentoService::dispararAssinatura - dados incompletos', ['usuario_id' => $usuarioMasterId]);
            return;
        }

        if (empty($faturamento->asaas_customer_id)) {
            $resposta = $this->asaasService->criarCliente([
                'name' => $faturamento->nome_titular,
                'cpfCnpj' => preg_replace('/\D/', '', $faturamento->cpf_cnpj),
                'email' => $faturamento->email,
                'phone' => preg_replace('/\D/', '', $faturamento->telefone ?? ''),
            ]);
            if (empty($resposta['id'])) {
                Log::error('Asaas criarCliente falhou', ['response' => $resposta]);
                return;
            }
            $faturamento->update(['asaas_customer_id' => $resposta['id']]);
            $faturamento->refresh();
        }

        $valor = $this->calcularValorPlano($usuarioMasterId);
        $nextDueDate = now()->addDays(3)->format('Y-m-d');

        $respostaSub = $this->asaasService->criarAssinatura(
            $faturamento->asaas_customer_id,
            $valor,
            $nextDueDate
        );
        if (empty($respostaSub['id'])) {
            Log::error('Asaas criarAssinatura falhou', ['response' => $respostaSub]);
            return;
        }

        $faturamento->update([
            'asaas_subscription_id' => $respostaSub['id'],
            'assinatura_ativa' => true,
            'valor_atual' => $valor,
            'data_ativacao' => now(),
        ]);

        $usuario = User::find($usuarioMasterId);
        $this->emailService->sendMailable($usuario->email, new FaturamentoAtivadoMail($usuario, $valor, $nextDueDate));
        if ($faturamento->email && $faturamento->email !== $usuario->email) {
            $this->emailService->sendMailable($faturamento->email, new FaturamentoAtivadoMail($usuario, $valor, $nextDueDate));
        }
    }

    public function recalcularValorAssinatura(int $usuarioMasterId): void
    {
        // Verificar se Asaas está configurado
        if (!$this->asaasService->isConfigured()) {
            return;
        }

        $faturamento = EmpresaFaturamento::where('usuario_id', $usuarioMasterId)->first();
        if (!$faturamento || !$faturamento->assinatura_ativa || empty($faturamento->asaas_subscription_id)) {
            return;
        }

        $novoValor = $this->calcularValorPlano($usuarioMasterId);
        if ((float) $faturamento->valor_atual === (float) $novoValor) {
            return;
        }

        $this->asaasService->atualizarAssinatura($faturamento->asaas_subscription_id, $novoValor);
        $faturamento->update(['valor_atual' => $novoValor]);
    }
}
