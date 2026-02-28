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

        $empresaIds = DB::table('usuarios_empresas')->where('usuario_id', $user->id)->pluck('empresa_id');
        $mesAtual = Carbon::now()->format('Y-m');
        $inicioMes = Carbon::now()->startOfMonth();
        $fimMes = Carbon::now()->endOfMonth();

        $pedidosMesAtual = Pedido::whereIn('empresa_id', $empresaIds)
            ->whereBetween('created_at', [$inicioMes, $fimMes])
            ->count();

        $faturamento = EmpresaFaturamento::where('usuario_id', $user->id)->first();
        $planoStatus = 'gratuito';
        $proximaCobranca = null;
        if ($faturamento && $faturamento->assinatura_ativa) {
            $planoStatus = 'ativo';
            $proximaCobranca = Carbon::now()->addMonth()->endOfMonth()->format('d/m/Y');
        }

        $valorPlano = 39.90;
        $limiteGratuito = 30;

        $faturas = EmpresaFatura::where('usuario_id', $user->id)
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
            ]);

        return response()->json([
            'success' => true,
            'plano_status' => $planoStatus,
            'pedidos_mes_atual' => $pedidosMesAtual,
            'limite_gratuito' => $limiteGratuito,
            'proxima_cobranca' => $proximaCobranca,
            'valor_plano' => $valorPlano,
            'faturas' => $faturas,
        ]);
    }
}
