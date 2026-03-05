<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Dashboard\DashboardService;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    ) {
    }

    /**
     * GET /api/dashboard
     * Query: tab (resumo|financeiro|produtos|pedidos), periodo (7d|15d|30d|mes_atual|mes_anterior), data_inicio, data_fim
     * empresa_id vem do header x-empresa-id (middleware empresa.context).
     */
    public function getDados(Request $request)
    {
        $empresaId = $request->empresa_id;
        if (!$empresaId) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhuma empresa encontrada para este usuário.',
            ], 404);
        }

        $tab = $request->input('tab', 'resumo');
        $params = [
            'periodo' => $request->input('periodo', 'mes_atual'),
            'data_inicio' => $request->input('data_inicio'),
            'data_fim' => $request->input('data_fim'),
            'dias' => $request->input('dias', 7),
        ];

        $validTabs = ['resumo', 'financeiro', 'produtos', 'pedidos', 'analise_vendas'];
        if (!in_array($tab, $validTabs)) {
            $tab = 'resumo';
        }

        try {
            $dados = match ($tab) {
                'resumo' => $this->dashboardService->getResumo((int) $empresaId, $params),
                'financeiro' => $this->dashboardService->getFinanceiro((int) $empresaId, $params),
                'produtos' => $this->dashboardService->getProdutos((int) $empresaId, $params),
                'pedidos' => $this->dashboardService->getPedidos((int) $empresaId, $params),
                'analise_vendas' => $this->dashboardService->getAnaliseVendas((int) $empresaId, $params),
                default => $this->dashboardService->getResumo((int) $empresaId, $params),
            };
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar dados do dashboard.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'tab' => $tab,
            ...$dados,
        ]);
    }
}
