<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Pedido;
use App\Models\EmpresaAvaliacao;
use App\Models\UsuarioLog;
use App\Models\Produto;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function getDados(Request $request)
    {
        $usuario  = Auth::user();
        $hoje     = Carbon::today();
        $primeiroDiaMes = Carbon::now()->startOfMonth();

        $empresaId = $request->empresa_id;
        if (!$empresaId) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhuma empresa encontrada para este usuário.'
            ], 404);
        }

        // ─── KPIs ─────────────────────────────────────────────────
        $pedidosHoje = Pedido::where('empresa_id', $empresaId)
            ->whereDate('created_at', $hoje)
            ->count();

        $faturamentoMes = Pedido::where('empresa_id', $empresaId)
            ->whereBetween('created_at', [$primeiroDiaMes, Carbon::now()])
            ->whereIn('status_pedido_id', [2, 3, 4, 5]) // confirmado, em preparo, em entrega, entregue
            ->sum('total');

        $pedidosPendentes = Pedido::where('empresa_id', $empresaId)
            ->where('status_pedido_id', 1)
            ->count();

        $avaliacaoMedia = EmpresaAvaliacao::where('empresa_id', $empresaId)->avg('nota');

        // ─── Vendas últimos 7 dias ────────────────────────────────
        $vendas7Dias = [];
        for ($i = 6; $i >= 0; $i--) {
            $dia = Carbon::today()->subDays($i);

            $totalDia = Pedido::where('empresa_id', $empresaId)
                ->whereDate('created_at', $dia)
                ->sum('total');

            $vendas7Dias[] = [
                'label' => $dia->format('d/m'),
                'total' => round((float) $totalDia, 2),
            ];
        }

        // ─── Últimos 5 pedidos ────────────────────────────────────
        $ultimosPedidos = Pedido::with(['usuario:id,nome,telefone', 'statusPedido:id,nome,slug'])
            ->where('empresa_id', $empresaId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($p) => [
                'id'           => $p->id,
                'codigo'       => $p->codigo ?? 'PED-' . str_pad($p->id, 6, '0', STR_PAD_LEFT),
                'usuario_nome' => $p->usuario?->nome ?? 'Cliente',
                'valor_total'  => (float) $p->total,
                'status_id'    => $p->status_pedido_id,
                'status_nome'  => $p->statusPedido?->nome ?? 'Desconhecido',
                'created_at'   => $p->created_at,
            ]);

        // ─── Avaliações recentes ───────────────────────────────────
        $avaliacoesRecentes = EmpresaAvaliacao::where('empresa_id', $empresaId)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(fn($a) => [
                'id'         => $a->id,
                'nota'       => (float) $a->nota,
                'comentario' => $a->descricao ?? null,
                'created_at' => $a->created_at,
            ]);

        // ─── Produtos populares (logs de adicionar ao carrinho) ────
        $produtosPopulares = UsuarioLog::select('produto_id', DB::raw('COUNT(*) as adicoes'))
            ->where('empresa_id', $empresaId)
            ->where('acao', 'adicionar_carrinho')
            ->whereNotNull('produto_id')
            ->groupBy('produto_id')
            ->orderByDesc('adicoes')
            ->limit(5)
            ->get()
            ->map(function ($log) {
                $produto = Produto::find($log->produto_id);
                return [
                    'id'      => $log->produto_id,
                    'nome'    => $produto?->nome ?? 'Produto #' . $log->produto_id,
                    'adicoes' => (int) $log->adicoes,
                ];
            });

        // ─── Horários de pico ──────────────────────────────────────
        $horariosPico = UsuarioLog::select(
                DB::raw('HOUR(created_at) as hora'),
                DB::raw('COUNT(*) as acessos')
            )
            ->where('empresa_id', $empresaId)
            ->groupBy('hora')
            ->orderByDesc('acessos')
            ->limit(8)
            ->get()
            ->map(fn($h) => [
                'hora'    => str_pad($h->hora, 2, '0', STR_PAD_LEFT),
                'acessos' => (int) $h->acessos,
            ]);

        // ─── Resposta única ────────────────────────────────────────
        return response()->json([
            'success' => true,
            'kpis' => [
                'pedidos_hoje'      => $pedidosHoje,
                'faturamento_mes'   => round((float) $faturamentoMes, 2),
                'pedidos_pendentes' => $pedidosPendentes,
                'avaliacao_media'   => $avaliacaoMedia ? round((float) $avaliacaoMedia, 1) : null,
            ],
            'vendas_7_dias'       => $vendas7Dias,
            'ultimos_pedidos'     => $ultimosPedidos,
            'avaliacoes_recentes' => $avaliacoesRecentes,
            'produtos_populares'  => $produtosPopulares,
            'horarios_pico'       => $horariosPico,
        ]);
    }
}
