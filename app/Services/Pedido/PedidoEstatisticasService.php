<?php

namespace App\Services\Pedido;

use App\Models\EmpresaAvaliacao;
use App\Models\Pedido;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PedidoEstatisticasService
{
    public function obterParaEmpresa(Request $request): array
    {
        $empresaId = $request->empresa_id;
        $hoje = Carbon::today();
        $primeiroDiaMes = Carbon::now()->startOfMonth();

        $pedidosHoje = Pedido::where('empresa_id', $empresaId)
            ->whereDate('created_at', $hoje)
            ->count();

        $faturamentoMes = Pedido::where('empresa_id', $empresaId)
            ->whereBetween('created_at', [$primeiroDiaMes, Carbon::now()])
            ->whereIn('status_pedido_id', [2, 3, 4, 5])
            ->sum('total');

        $pedidosPendentes = Pedido::where('empresa_id', $empresaId)
            ->where('status_pedido_id', 1)
            ->count();

        $avaliacaoMedia = EmpresaAvaliacao::where('empresa_id', $empresaId)->avg('nota');

        return [
            'success'      => true,
            'estatisticas' => [
                'pedidos_hoje'      => $pedidosHoje,
                'faturamento_mes'   => round((float) $faturamentoMes, 2),
                'pedidos_pendentes' => $pedidosPendentes,
                'avaliacao_media'   => $avaliacaoMedia ? round((float) $avaliacaoMedia, 1) : null,
            ],
        ];
    }
}
