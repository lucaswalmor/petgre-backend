<?php

namespace App\Services\Produto;

use App\Http\Resources\Produto\ProdutoResource;
use App\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProdutoListagemService
{
    /** Colunas permitidas em order_by (evita injeção / colunas inexistentes). */
    private const ORDER_BY_PERMITIDOS = [
        'created_at',
        'updated_at',
        'nome',
        'preco',
        'estoque',
        'ordem',
        'id',
    ];

    public function listarPaginado(Request $request, int $empresaId): array
    {
        Log::info('[ProdutoListagemService@listarPaginado] Parâmetros recebidos', [
            'empresa_id'       => $empresaId,
            'q'                => $request->get('q'),
            'categoria_id'     => $request->get('categoria_id'),
            'destaque'         => $request->get('destaque'),
            'somente_promocao' => $request->get('somente_promocao'),
            'todos_params'     => $request->all(),
        ]);

        $query = Produto::where('empresa_id', $empresaId)
            ->whereNull('deleted_at')
            ->with(['categoria', 'unidadeMedida', 'empresa']);

        if ($request->filled('q')) {
            $busca = '%' . $request->get('q') . '%';
            Log::info('[ProdutoListagemService@listarPaginado] Aplicando filtro q', ['busca' => $busca]);
            $query->where(function ($subQuery) use ($busca) {
                $subQuery
                    ->where('nome', 'like', $busca)
                    ->orWhere('descricao', 'like', $busca)
                    ->orWhere('sku', 'like', $busca)
                    ->orWhere('marca', 'like', $busca);
            });
        }

        if ($request->filled('categoria_id') && is_numeric($request->categoria_id)) {
            Log::info('[ProdutoListagemService@listarPaginado] Aplicando filtro categoria_id', ['categoria_id' => (int) $request->categoria_id]);
            $query->where('categoria_id', (int) $request->categoria_id);
        }

        if ($request->has('tipo') && $request->tipo) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('ativo') && $request->ativo !== null) {
            $query->where('ativo', $request->boolean('ativo'));
        }

        if ($request->filled('tipo_porte') && in_array($request->tipo_porte, ['unico', 'todos'], true)) {
            $query->where('tipo_porte', $request->tipo_porte);
        }

        if ($request->filled('destaque') && $request->boolean('destaque') === true) {
            $query->where('destaque', true);
        }

        if ($request->has('somente_promocao') && $request->boolean('somente_promocao')) {
            $query->where('tem_promocao', true);
        }

        if ($request->has('vende_granel') && $request->vende_granel !== null) {
            $query->where('vende_granel', $request->boolean('vende_granel'));
        }

        if ($request->has('estoque_status') && $request->estoque_status) {
            $status = $request->estoque_status;
            $query->where(function ($q) use ($status) {
                if ($status === 'baixo') {
                    $q->whereColumn('estoque', '<=', 'estoque_minimo');
                }
                if ($status === 'zerado') {
                    $q->where('estoque', '<=', 0);
                }
            });
        }

        $orderBy = $request->get('order_by', 'created_at');
        if (! in_array($orderBy, self::ORDER_BY_PERMITIDOS, true)) {
            $orderBy = 'created_at';
        }
        $orderDirection = strtolower((string) $request->get('order_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($orderBy, $orderDirection);

        $perPage = (int) $request->get('per_page', 15);
        if ($perPage < 1) {
            $perPage = 15;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        Log::info('[ProdutoListagemService@listarPaginado] SQL gerado', [
            'sql'      => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        $produtos = $query->paginate($perPage);

        Log::info('[ProdutoListagemService@listarPaginado] Total de resultados', ['total' => $produtos->total()]);

        return [
            'produtos'  => ProdutoResource::collection($produtos),
            'paginacao' => [
                'total'            => $produtos->total(),
                'per_page'         => $produtos->perPage(),
                'current_page'     => $produtos->currentPage(),
                'last_page'        => $produtos->lastPage(),
                'from'             => $produtos->firstItem(),
                'to'               => $produtos->lastItem(),
                'has_more_pages'   => $produtos->hasMorePages(),
            ],
        ];
    }

    public function buscar(Request $request, int $empresaId): array
    {
        $query = $request->get('q', '');
        $categoriaId = $request->get('categoria_id');
        $tipo = $request->get('tipo');

        $produtos = Produto::where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->when($query, function ($q) use ($query) {
                $q->where('nome', 'like', "%{$query}%")
                    ->orWhere('descricao', 'like', "%{$query}%");
            })
            ->when($categoriaId, function ($q) use ($categoriaId) {
                $q->where('categoria_id', $categoriaId);
            })
            ->when($tipo, function ($q) use ($tipo) {
                $q->where('tipo', $tipo);
            })
            ->with(['categoria', 'unidadeMedida', 'empresa'])
            ->orderBy('nome')
            ->get();

        return ['produtos' => ProdutoResource::collection($produtos)];
    }
}
