<?php

namespace App\Services\Dashboard;

use App\Models\Pedido;
use App\Models\PedidoItems;
use App\Models\EmpresaAvaliacao;
use App\Models\UsuarioLog;
use App\Models\Produto;
use App\Models\EmpresaFavorito;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /** Status considerados como "faturados" (receita realizada) */
    private const STATUS_FATURADOS = [2, 3, 4, 5];

    /**
     * Retorna dados da tab Visão Geral (resumo).
     */
    public function getResumo(int $empresaId, array $params = []): array
    {
        $hoje = Carbon::today();
        $primeiroDiaMes = Carbon::now()->startOfMonth();

        $pedidosHoje = Pedido::where('empresa_id', $empresaId)
            ->whereDate('created_at', $hoje)
            ->count();

        $faturamentoMes = Pedido::where('empresa_id', $empresaId)
            ->whereBetween('created_at', [$primeiroDiaMes, Carbon::now()])
            ->whereIn('status_pedido_id', self::STATUS_FATURADOS)
            ->sum('total');

        $pedidosPendentes = Pedido::where('empresa_id', $empresaId)
            ->where('status_pedido_id', 1)
            ->count();

        $avaliacaoMedia = EmpresaAvaliacao::where('empresa_id', $empresaId)->avg('nota');

        $totalFavoritos = EmpresaFavorito::where('empresa_id', $empresaId)->count();

        $dias = (int) ($params['dias'] ?? 7);
        $vendasDias = $this->vendasPorDias($empresaId, $dias);

        $ultimosPedidos = Pedido::with(['usuario:id,nome,telefone', 'statusPedido:id,nome,slug'])
            ->where('empresa_id', $empresaId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($p) => $this->formatoPedidoResumo($p));

        $avaliacoesRecentes = EmpresaAvaliacao::where('empresa_id', $empresaId)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'nota' => (float) $a->nota,
                'comentario' => $a->descricao ?? null,
                'created_at' => $a->created_at,
            ]);

        $produtosPopulares = $this->produtosPopularesCarrinho($empresaId, 5);

        $horariosPico = UsuarioLog::select(
            DB::raw('HOUR(created_at) as hora'),
            DB::raw('COUNT(*) as acessos')
        )
            ->where('empresa_id', $empresaId)
            ->groupBy('hora')
            ->orderByDesc('acessos')
            ->limit(8)
            ->get()
            ->map(fn ($h) => [
                'hora' => str_pad((string) $h->hora, 2, '0', STR_PAD_LEFT),
                'acessos' => (int) $h->acessos,
            ]);

        $vendasSemanaTotal = array_sum(array_column($vendasDias, 'total'));

        return [
            'kpis' => [
                'pedidos_hoje' => $pedidosHoje,
                'faturamento_mes' => round((float) $faturamentoMes, 2),
                'pedidos_pendentes' => $pedidosPendentes,
                'avaliacao_media' => $avaliacaoMedia ? round((float) $avaliacaoMedia, 1) : null,
                'vendas_semana_total' => round((float) $vendasSemanaTotal, 2),
                'total_favoritos' => $totalFavoritos,
            ],
            'vendas_7_dias' => $vendasDias,
            'ultimos_pedidos' => $ultimosPedidos,
            'avaliacoes_recentes' => $avaliacoesRecentes,
            'produtos_populares' => $produtosPopulares,
            'horarios_pico' => $horariosPico,
        ];
    }

    /**
     * Retorna dados da tab Financeiro.
     */
    public function getFinanceiro(int $empresaId, array $params = []): array
    {
        $periodo = $this->periodoFromParams($params);
        $inicio = $periodo['inicio'];
        $fim = $periodo['fim'];

        $queryMes = Pedido::where('empresa_id', $empresaId)
            ->whereBetween('created_at', [$inicio, $fim])
            ->whereIn('status_pedido_id', self::STATUS_FATURADOS);

        $faturamento = (float) (clone $queryMes)->sum('total');
        $totalDescontos = (float) (clone $queryMes)->sum('cupom_valor');
        $quantidadePedidos = (clone $queryMes)->count();
        $ticketMedio = $quantidadePedidos > 0 ? round($faturamento / $quantidadePedidos, 2) : 0;

        $mesAnteriorInicio = (clone $inicio)->subMonth()->startOfMonth();
        $mesAnteriorFim = (clone $fim)->subMonth()->endOfMonth();
        $faturamentoMesAnterior = (float) Pedido::where('empresa_id', $empresaId)
            ->whereBetween('created_at', [$mesAnteriorInicio, $mesAnteriorFim])
            ->whereIn('status_pedido_id', self::STATUS_FATURADOS)
            ->sum('total');
        $variacaoPercentual = $faturamentoMesAnterior > 0
            ? round((($faturamento - $faturamentoMesAnterior) / $faturamentoMesAnterior) * 100, 1)
            : ($faturamento > 0 ? 100 : 0);

        $porFormaPagamento = Pedido::where('empresa_id', $empresaId)
            ->whereBetween('created_at', [$inicio, $fim])
            ->whereIn('status_pedido_id', self::STATUS_FATURADOS)
            ->select('pagamento_id', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as quantidade'))
            ->groupBy('pagamento_id')
            ->get()
            ->map(function ($row) {
                $forma = \App\Models\FormasPagamentos::find($row->pagamento_id);
                return [
                    'forma_nome' => $forma?->nome ?? 'N/D',
                    'total' => round((float) $row->total, 2),
                    'quantidade' => (int) $row->quantidade,
                ];
            });

        $evolucaoSemanal = $this->evolucaoFinanceiraSemanal($empresaId, $inicio, $fim);
        $receitaDiariaComparada = $this->receitaDiariaComparadaMeses($empresaId);

        return [
            'kpis' => [
                'faturamento' => round($faturamento, 2),
                'faturamento_mes_anterior' => round($faturamentoMesAnterior, 2),
                'variacao_percentual' => $variacaoPercentual,
                'ticket_medio' => $ticketMedio,
                'total_descontos' => round($totalDescontos, 2),
                'quantidade_pedidos' => $quantidadePedidos,
            ],
            'por_forma_pagamento' => $porFormaPagamento,
            'evolucao_semanal' => $evolucaoSemanal,
            'receita_diaria_comparada' => $receitaDiariaComparada,
            'periodo' => [
                'inicio' => $inicio->format('Y-m-d'),
                'fim' => $fim->format('Y-m-d'),
                'label' => $inicio->format('d/m/Y') . ' - ' . $fim->format('d/m/Y'),
            ],
        ];
    }

    /**
     * Receita dia a dia (1 a 31) do mês atual vs mês anterior para gráfico comparativo.
     */
    private function receitaDiariaComparadaMeses(int $empresaId): array
    {
        $mesAtualInicio = Carbon::now()->startOfMonth();
        $mesAtualFim = Carbon::now();
        $mesAnteriorInicio = Carbon::now()->subMonth()->startOfMonth();
        $mesAnteriorFim = Carbon::now()->subMonth()->endOfMonth();

        $result = [];
        $diasNoMesAtual = (int) $mesAtualFim->format('d');
        $diasNoMesAnterior = (int) $mesAnteriorFim->format('d');
        $maxDias = max(31, $diasNoMesAtual, $diasNoMesAnterior);

        for ($dia = 1; $dia <= 31; $dia++) {
            $totalMesAtual = 0.0;
            $totalMesAnterior = 0.0;
            if ($dia <= $diasNoMesAtual) {
                $dataAtual = $mesAtualInicio->copy()->addDays($dia - 1);
                $totalMesAtual = (float) Pedido::where('empresa_id', $empresaId)
                    ->whereDate('created_at', $dataAtual)
                    ->whereIn('status_pedido_id', self::STATUS_FATURADOS)
                    ->sum('total');
            }
            if ($dia <= $diasNoMesAnterior) {
                $dataAnterior = $mesAnteriorInicio->copy()->addDays($dia - 1);
                $totalMesAnterior = (float) Pedido::where('empresa_id', $empresaId)
                    ->whereDate('created_at', $dataAnterior)
                    ->whereIn('status_pedido_id', self::STATUS_FATURADOS)
                    ->sum('total');
            }
            $result[] = [
                'dia' => str_pad((string) $dia, 2, '0', STR_PAD_LEFT),
                'mes_atual' => round($totalMesAtual, 2),
                'mes_anterior' => round($totalMesAnterior, 2),
            ];
        }
        return $result;
    }

    /**
     * Retorna dados da tab Produtos.
     */
    public function getProdutos(int $empresaId, array $params = []): array
    {
        $periodo = $this->periodoFromParams($params);
        $inicio = $periodo['inicio'];
        $fim = $periodo['fim'];

        $totalAtivos = Produto::where('empresa_id', $empresaId)->where('ativo', true)->count();
        $totalPromocao = Produto::where('empresa_id', $empresaId)->where('tem_promocao', true)->count();
        $totalEstoqueBaixo = Produto::where('empresa_id', $empresaId)
            ->where('ativar_estoque_minimo', true)
            ->whereRaw('estoque <= estoque_minimo')
            ->count();
        $novosNoMes = Produto::where('empresa_id', $empresaId)
            ->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()])
            ->count();

        $maisVendidos = PedidoItems::whereHas('pedido', function ($q) use ($empresaId, $inicio, $fim) {
            $q->where('empresa_id', $empresaId)
                ->whereIn('status_pedido_id', self::STATUS_FATURADOS)
                ->whereBetween('created_at', [$inicio, $fim]);
        })
            ->whereNotNull('produto_id')
            ->select('produto_id', DB::raw('SUM(quantidade) as quantidade'), DB::raw('SUM(preco_total) as valor'))
            ->groupBy('produto_id')
            ->orderByDesc('quantidade')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $produto = Produto::find($row->produto_id);
                return [
                    'id' => $row->produto_id,
                    'nome' => $produto?->nome ?? 'Produto #' . $row->produto_id,
                    'quantidade' => (float) $row->quantidade,
                    'valor' => round((float) $row->valor, 2),
                ];
            });

        $produtosPopulares = $this->produtosPopularesCarrinho($empresaId, 5);

        $porCategoria = Produto::where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->select('categoria_id', DB::raw('COUNT(*) as total'))
            ->groupBy('categoria_id')
            ->get()
            ->map(function ($row) {
                $cat = \App\Models\Categorias::find($row->categoria_id);
                return [
                    'categoria_nome' => $cat?->nome ?? 'Sem categoria',
                    'total' => (int) $row->total,
                ];
            });

        $estoqueBaixo = Produto::where('empresa_id', $empresaId)
            ->where('ativar_estoque_minimo', true)
            ->whereRaw('estoque <= estoque_minimo')
            ->orderBy('estoque')
            ->get(['id', 'nome', 'estoque', 'estoque_minimo'])
            ->values()
            ->map(fn ($p) => [
                'id' => $p->id,
                'nome' => $p->nome,
                'estoque' => (float) $p->estoque,
                'estoque_minimo' => (float) $p->estoque_minimo,
            ]);

        $produtosVendidosTotalGeral = PedidoItems::whereHas('pedido', function ($q) use ($empresaId) {
            $q->where('empresa_id', $empresaId)->whereIn('status_pedido_id', self::STATUS_FATURADOS);
        })
            ->whereNotNull('produto_id')
            ->select('produto_id', DB::raw('SUM(quantidade) as quantidade'))
            ->groupBy('produto_id')
            ->orderByDesc('quantidade')
            ->get()
            ->map(function ($row) {
                $produto = Produto::find($row->produto_id);
                return [
                    'id' => $row->produto_id,
                    'nome' => $produto?->nome ?? 'Produto #' . $row->produto_id,
                    'quantidade' => (float) $row->quantidade,
                ];
            });

        return [
            'kpis' => [
                'total_ativos' => $totalAtivos,
                'total_promocao' => $totalPromocao,
                'total_estoque_baixo' => $totalEstoqueBaixo,
                'novos_no_mes' => $novosNoMes,
            ],
            'mais_vendidos' => $maisVendidos,
            'produtos_vendidos_total_geral' => $produtosVendidosTotalGeral,
            'produtos_populares' => $produtosPopulares,
            'por_categoria' => $porCategoria,
            'estoque_baixo' => $estoqueBaixo,
            'periodo' => [
                'inicio' => $inicio->format('Y-m-d'),
                'fim' => $fim->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Retorna dados da tab Pedidos.
     */
    public function getPedidos(int $empresaId, array $params = []): array
    {
        $periodo = $this->periodoFromParams($params);
        $inicio = $periodo['inicio'];
        $fim = $periodo['fim'];

        $hoje = Carbon::today();
        $pedidosHoje = Pedido::where('empresa_id', $empresaId)->whereDate('created_at', $hoje)->count();
        $pendentes = Pedido::where('empresa_id', $empresaId)->where('status_pedido_id', 1)->count();

        $queryPeriodo = Pedido::where('empresa_id', $empresaId)
            ->whereBetween('created_at', [$inicio, $fim]);

        $confirmadosMes = (clone $queryPeriodo)->whereIn('status_pedido_id', self::STATUS_FATURADOS)->count();
        $canceladosMes = (clone $queryPeriodo)->where('status_pedido_id', 6)->count();
        $totalPeriodo = (clone $queryPeriodo)->count();
        $taxaCancelamento = $totalPeriodo > 0 ? round(($canceladosMes / $totalPeriodo) * 100, 1) : 0;

        $porStatus = Pedido::where('empresa_id', $empresaId)
            ->whereBetween('created_at', [$inicio, $fim])
            ->select('status_pedido_id', DB::raw('COUNT(*) as quantidade'), DB::raw('SUM(total) as valor'))
            ->groupBy('status_pedido_id')
            ->get()
            ->map(function ($row) {
                $status = \App\Models\StatusPedidos::find($row->status_pedido_id);
                return [
                    'status_nome' => $status?->nome ?? 'N/D',
                    'quantidade' => (int) $row->quantidade,
                    'valor' => round((float) $row->valor, 2),
                ];
            });

        $ultimosPedidos = Pedido::with(['usuario:id,nome,telefone', 'statusPedido:id,nome,slug'])
            ->where('empresa_id', $empresaId)
            ->whereBetween('created_at', [$inicio, $fim])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($p) => $this->formatoPedidoResumo($p));

        $pedidosPorDia = $this->pedidosPorDia($empresaId, $inicio, $fim);

        $horariosPicoPedidos = Pedido::where('empresa_id', $empresaId)
            ->whereBetween('created_at', [$inicio, $fim])
            ->select(DB::raw('HOUR(created_at) as hora'), DB::raw('COUNT(*) as quantidade'))
            ->groupBy('hora')
            ->orderByDesc('quantidade')
            ->limit(8)
            ->get()
            ->map(fn ($h) => [
                'hora' => str_pad((string) $h->hora, 2, '0', STR_PAD_LEFT),
                'quantidade' => (int) $h->quantidade,
            ]);

        return [
            'kpis' => [
                'pedidos_hoje' => $pedidosHoje,
                'pendentes' => $pendentes,
                'confirmados_mes' => $confirmadosMes,
                'cancelados_mes' => $canceladosMes,
                'taxa_cancelamento' => $taxaCancelamento,
            ],
            'por_status' => $porStatus,
            'ultimos_pedidos' => $ultimosPedidos,
            'pedidos_por_dia' => $pedidosPorDia,
            'horarios_pico' => $horariosPicoPedidos,
            'periodo' => [
                'inicio' => $inicio->format('Y-m-d'),
                'fim' => $fim->format('Y-m-d'),
            ],
        ];
    }

    private function periodoFromParams(array $params): array
    {
        $inicio = Carbon::today();
        $fim = Carbon::today();

        if (!empty($params['data_inicio']) && !empty($params['data_fim'])) {
            $inicio = Carbon::parse($params['data_inicio'])->startOfDay();
            $fim = Carbon::parse($params['data_fim'])->endOfDay();
            return ['inicio' => $inicio, 'fim' => $fim];
        }

        $periodo = $params['periodo'] ?? 'mes_atual';
        if ($periodo === '7d') {
            $inicio = Carbon::today()->subDays(6);
            $fim = Carbon::today();
        } elseif ($periodo === '15d') {
            $inicio = Carbon::today()->subDays(14);
            $fim = Carbon::today();
        } elseif ($periodo === '30d') {
            $inicio = Carbon::today()->subDays(29);
            $fim = Carbon::today();
        } elseif ($periodo === 'mes_atual') {
            $inicio = Carbon::now()->startOfMonth();
            $fim = Carbon::now();
        } elseif ($periodo === 'mes_anterior') {
            $inicio = Carbon::now()->subMonth()->startOfMonth();
            $fim = Carbon::now()->subMonth()->endOfMonth();
        }

        return ['inicio' => $inicio, 'fim' => $fim];
    }

    private function vendasPorDias(int $empresaId, int $dias): array
    {
        $result = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $dia = Carbon::today()->subDays($i);
            $totalDia = Pedido::where('empresa_id', $empresaId)
                ->whereDate('created_at', $dia)
                ->whereIn('status_pedido_id', self::STATUS_FATURADOS)
                ->sum('total');
            $result[] = [
                'label' => $dia->format('d/m'),
                'total' => round((float) $totalDia, 2),
            ];
        }
        return $result;
    }

    private function evolucaoFinanceiraSemanal(int $empresaId, Carbon $inicio, Carbon $fim): array
    {
        $result = [];
        $atual = $inicio->copy();
        while ($atual <= $fim) {
            $fimSemana = $atual->copy()->endOfWeek();
            if ($fimSemana > $fim) {
                $fimSemana = $fim->copy();
            }
            $total = Pedido::where('empresa_id', $empresaId)
                ->whereBetween('created_at', [$atual, $fimSemana])
                ->whereIn('status_pedido_id', self::STATUS_FATURADOS)
                ->sum('total');
            $result[] = [
                'label' => 'Sem ' . $atual->format('d/m'),
                'total' => round((float) $total, 2),
            ];
            $atual->addWeek()->startOfWeek();
        }
        if (empty($result)) {
            $total = Pedido::where('empresa_id', $empresaId)
                ->whereBetween('created_at', [$inicio, $fim])
                ->whereIn('status_pedido_id', self::STATUS_FATURADOS)
                ->sum('total');
            $result[] = ['label' => $inicio->format('d/m') . ' - ' . $fim->format('d/m'), 'total' => round((float) $total, 2)];
        }
        return $result;
    }

    private function pedidosPorDia(int $empresaId, Carbon $inicio, Carbon $fim): array
    {
        $result = [];
        $atual = $inicio->copy();
        while ($atual <= $fim) {
            $total = Pedido::where('empresa_id', $empresaId)
                ->whereDate('created_at', $atual)
                ->count();
            $result[] = [
                'label' => $atual->format('d/m'),
                'quantidade' => (int) $total,
            ];
            $atual->addDay();
        }
        return $result;
    }

    private function produtosPopularesCarrinho(int $empresaId, int $limit): array
    {
        return UsuarioLog::select('produto_id', DB::raw('COUNT(*) as adicoes'))
            ->where('empresa_id', $empresaId)
            ->where('acao', 'adicionar_carrinho')
            ->whereNotNull('produto_id')
            ->groupBy('produto_id')
            ->orderByDesc('adicoes')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                $produto = Produto::find($log->produto_id);
                return [
                    'id' => $log->produto_id,
                    'nome' => $produto?->nome ?? 'Produto #' . $log->produto_id,
                    'adicoes' => (int) $log->adicoes,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Retorna dados para o card Análise de vendas (dia da semana com maior volume, forma de pagamento mais usada, pedidos por status).
     */
    public function getAnaliseVendas(int $empresaId, array $params = []): array
    {
        $periodo = $this->periodoFromParams($params);
        $inicio = $periodo['inicio'];
        $fim = $periodo['fim'];

        $query = Pedido::where('empresa_id', $empresaId)
            ->whereBetween('created_at', [$inicio, $fim]);

        $diaSemanaMaiorVolume = (clone $query)
            ->select(DB::raw('DAYOFWEEK(created_at) as dia_semana'), DB::raw('COUNT(*) as total'))
            ->groupBy('dia_semana')
            ->orderByDesc('total')
            ->first();
        $nomesDia = [1 => 'Domingo', 2 => 'Segunda-feira', 3 => 'Terça-feira', 4 => 'Quarta-feira', 5 => 'Quinta-feira', 6 => 'Sexta-feira', 7 => 'Sábado'];
        $diaSemanaLabel = $diaSemanaMaiorVolume ? ($nomesDia[$diaSemanaMaiorVolume->dia_semana] ?? 'N/D') : null;

        $formaPagamentoMaisUsada = (clone $query)
            ->select('pagamento_id', DB::raw('COUNT(*) as quantidade'))
            ->groupBy('pagamento_id')
            ->orderByDesc('quantidade')
            ->first();
        $formaNome = null;
        $formaQuantidade = 0;
        if ($formaPagamentoMaisUsada) {
            $forma = \App\Models\FormasPagamentos::find($formaPagamentoMaisUsada->pagamento_id);
            $formaNome = $forma?->nome ?? 'N/D';
            $formaQuantidade = (int) $formaPagamentoMaisUsada->quantidade;
        }

        $pedidosPorStatus = (clone $query)
            ->select('status_pedido_id', DB::raw('COUNT(*) as quantidade'))
            ->groupBy('status_pedido_id')
            ->get()
            ->map(function ($row) {
                $status = \App\Models\StatusPedidos::find($row->status_pedido_id);
                return [
                    'status_nome' => $status?->nome ?? 'N/D',
                    'quantidade' => (int) $row->quantidade,
                ];
            });

        return [
            'dia_semana_maior_volume' => $diaSemanaLabel,
            'forma_pagamento_mais_utilizada' => $formaNome ? $formaNome . ' — ' . $formaQuantidade . ' pedidos' : null,
            'pedidos_por_status' => $pedidosPorStatus,
            'periodo' => [
                'inicio' => $inicio->format('Y-m-d'),
                'fim' => $fim->format('Y-m-d'),
            ],
        ];
    }

    private function formatoPedidoResumo($p): array
    {
        return [
            'id' => $p->id,
            'codigo' => $p->codigo ?? 'PED-' . str_pad((string) $p->id, 6, '0', STR_PAD_LEFT),
            'usuario_nome' => $p->usuario?->nome ?? 'Cliente',
            'valor_total' => (float) $p->total,
            'status_id' => $p->status_pedido_id,
            'status_nome' => $p->statusPedido?->nome ?? 'Desconhecido',
            'created_at' => $p->created_at,
        ];
    }
}
