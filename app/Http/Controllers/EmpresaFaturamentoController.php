<?php

namespace App\Http\Controllers;

use App\Http\Requests\Faturamento\EmpresaFaturamentoRequest;
use App\Http\Resources\EmpresaFaturamentoResource;
use App\Models\EmpresaFatura;
use App\Models\EmpresaFaturamento;
use App\Models\Pedido;
use App\Services\AsaasService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmpresaFaturamentoController extends Controller
{
    public function show(): JsonResponse
    {
        $user = Auth::user();
        if (!$user->isMaster()) {
            return response()->json(['success' => false, 'message' => 'Acesso negado.'], 403);
        }

        $faturamento = EmpresaFaturamento::where('usuario_id', $user->id)->first();
        return response()->json([
            'success' => true,
            'faturamento' => $faturamento ? new EmpresaFaturamentoResource($faturamento) : null,
        ]);
    }

    public function store(EmpresaFaturamentoRequest $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user->isMaster()) {
            return response()->json(['success' => false, 'message' => 'Acesso negado.'], 403);
        }

        if (EmpresaFaturamento::where('usuario_id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Dados de faturamento já cadastrados para este usuário.'], 422);
        }

        $faturamento = new EmpresaFaturamento();
        $faturamento->usuario_id = $user->id;
        $faturamento->nome_titular = $request->input('nome_titular');
        $faturamento->tipo_documento_titular = $request->input('tipo_documento_titular', 'cpf');
        $faturamento->cpf_cnpj = $request->input('cpf_cnpj');
        $faturamento->fill($request->only(['email', 'telefone', 'chave_pix', 'tipo_chave_pix']));
        $faturamento->save();

        return response()->json([
            'success' => true,
            'message' => 'Dados de faturamento cadastrados com sucesso.',
            'faturamento' => new EmpresaFaturamentoResource($faturamento),
        ], 201);
    }

    public function update(EmpresaFaturamentoRequest $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user->isMaster()) {
            return response()->json(['success' => false, 'message' => 'Acesso negado.'], 403);
        }

        $faturamento = EmpresaFaturamento::where('usuario_id', $user->id)->first();
        if (!$faturamento) {
            return response()->json(['success' => false, 'message' => 'Dados de faturamento não encontrados.'], 404);
        }

        $faturamento->update($request->only(['email', 'telefone', 'chave_pix', 'tipo_chave_pix']));

        if ($faturamento->asaas_customer_id) {
            app(AsaasService::class)->atualizarCliente($faturamento->asaas_customer_id, [
                'email' => $faturamento->email,
                'phone' => $faturamento->telefone,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Dados de faturamento atualizados com sucesso.',
            'faturamento' => new EmpresaFaturamentoResource($faturamento->fresh()),
        ]);
    }

    public function resumo(): JsonResponse
    {
        $user = Auth::user();
        if (!$user->isMaster()) {
            return response()->json(['success' => false, 'message' => 'Acesso negado.'], 403);
        }

        // Buscar empresa matriz do usuário
        $matriz = \App\Models\Empresa::whereHas('usuarioEmpresas', fn ($q) => $q->where('usuario_id', $user->id))
            ->where('is_matriz', true)
            ->first();

        if (!$matriz) {
            return response()->json(['success' => false, 'message' => 'Empresa matriz não encontrada.'], 404);
        }

        $mesAtual = Carbon::now()->format('Y-m');
        $inicioMes = Carbon::now()->startOfMonth();
        $fimMes = Carbon::now()->endOfMonth();

        // Contar pedidos do mês atual (matriz + filiais ativas)
        $idsEmpresas = \App\Models\Empresa::where('id', $matriz->id)
            ->orWhere(function ($query) use ($matriz) {
                $query->where('empresa_matriz_id', $matriz->id)
                    ->where('ativo', true);
            })
            ->pluck('id');

        $pedidosMesAtual = Pedido::whereIn('empresa_id', $idsEmpresas)
            ->whereBetween('created_at', [$inicioMes, $fimMes])
            ->count();

        // Contar filiais ativas
        $quantidadeFiliais = \App\Models\Empresa::where('empresa_matriz_id', $matriz->id)
            ->where('ativo', true)
            ->where('is_matriz', false)
            ->count();

        // Calcular valor estimado da próxima cobrança
        $faturamentoService = app(\App\Services\FaturamentoService::class);
        $calculo = $faturamentoService->calcularValorCobranca($matriz->id);
        $valorEstimado = $calculo['valor_total'];
        $valorBase = $calculo['valor_base'];

        // Status do plano
        $faturamento = EmpresaFaturamento::where('usuario_id', $user->id)->first();
        $assinaturaAtiva = $faturamento && $faturamento->assinatura_ativa;

        // Verificar se há fatura em aberto (pendente ou vencida)
        $faturaEmAberto = EmpresaFatura::where('empresa_id', $matriz->id)
            ->whereIn('status', ['pendente', 'vencido'])
            ->first();

        // Próxima avaliação: dia 01 do próximo mês
        $proximaAvaliacao = Carbon::now()->addMonth()->startOfMonth()->format('d/m/Y');

        // Histórico de faturas
        $faturas = EmpresaFatura::where('usuario_id', $user->id)
            ->orWhere('empresa_id', $matriz->id)
            ->orderBy('mes_referencia', 'desc')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'mes_referencia' => $f->mes_referencia,
                'valor' => (float) $f->valor,
                'status' => $f->status,
                'vencimento' => $f->vencimento?->format('Y-m-d'),
                'pago_em' => $f->pago_em?->format('Y-m-d'),
                'link_fatura' => $f->link_fatura,
                'quantidade_pedidos' => $f->quantidade_pedidos,
                'quantidade_filiais' => $f->quantidade_filiais,
            ]);

        return response()->json([
            'success' => true,
            'modelo_cobranca' => 'condicional_mensal',
            'matriz_id' => $matriz->id,
            'mes_referencia_atual' => $mesAtual,
            'pedidos_mes_atual' => $pedidosMesAtual,
            'quantidade_filiais' => $quantidadeFiliais,
            'limite_gratuito' => 15,
            'pedidos_para_cobranca' => max(0, 16 - $pedidosMesAtual),
            'vai_ser_cobrado' => $pedidosMesAtual >= 16,
            'valor_base' => $valorBase,
            'valor_estimado_proxima_cobranca' => $pedidosMesAtual >= 16 ? $valorEstimado : 0,
            'proxima_avaliacao' => $proximaAvaliacao,
            'assinatura_ativa' => $assinaturaAtiva,
            'fatura_em_aberto' => $faturaEmAberto ? [
                'id' => $faturaEmAberto->id,
                'valor' => (float) $faturaEmAberto->valor,
                'status' => $faturaEmAberto->status,
                'vencimento' => $faturaEmAberto->vencimento?->format('Y-m-d'),
                'link_fatura' => $faturaEmAberto->link_fatura,
            ] : null,
            'faturas' => $faturas,
        ]);
    }
}
