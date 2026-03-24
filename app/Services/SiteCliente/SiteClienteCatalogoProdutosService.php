<?php

namespace App\Services\SiteCliente;

use App\Models\Categorias;
use App\Models\Produto;
use App\Services\CalculosService;
use Illuminate\Http\Request;

class SiteClienteCatalogoProdutosService
{
    public function listar(Request $request): array
    {
        $query = Produto::where('ativo', true)
            ->whereHas('empresa', function ($q) {
                $q->where('ativo', true)
                    ->where('cadastro_completo', true);
            })
            ->with(['empresa', 'categoria', 'unidadeMedida']);

        if ($request->has('bairro') && ! empty(trim($request->bairro))) {
            $query->whereHas('empresa.bairrosEntregas', function ($q) use ($request) {
                $q->whereHas('bairro', function ($qb) use ($request) {
                    $qb->where('nome', $request->bairro)
                        ->where('ativo', true);
                })
                    ->where('ativo', true);
            });
        }

        if ($request->has('q') && ! empty(trim($request->q))) {
            $termoBusca = trim($request->q);
            $query->where(function ($q) use ($termoBusca) {
                $q->where('nome', 'like', '%' . $termoBusca . '%')
                    ->orWhere('descricao', 'like', '%' . $termoBusca . '%');
            });
        }

        if ($request->has('categoria_id') && ! empty($request->categoria_id)) {
            $query->where('categoria_id', $request->categoria_id);
        }

        if ($request->has('promocao_only') && $request->promocao_only == 'true') {
            $query->where('preco_promocional', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('promocao_ate')
                        ->orWhere('promocao_ate', '>=', now());
                });
        }

        if ($request->has('ordenacao') && ! empty($request->ordenacao)) {
            switch ($request->ordenacao) {
                case 'preco_asc':
                    $query->orderByRaw('COALESCE(preco_promocional, preco) ASC');
                    break;
                case 'preco_desc':
                    $query->orderByRaw('COALESCE(preco_promocional, preco) DESC');
                    break;
                case 'nome_asc':
                    $query->orderBy('nome', 'asc');
                    break;
                case 'nome_desc':
                    $query->orderBy('nome', 'desc');
                    break;
                case 'promocoes':
                    $query->orderByRaw('(CASE WHEN preco_promocional > 0 AND (promocao_ate IS NULL OR promocao_ate >= NOW()) THEN 0 ELSE 1 END)')
                        ->orderBy('nome', 'asc');
                    break;
                default:
                    $query->orderBy('nome', 'asc');
            }
        } else {
            $query->orderByRaw('(CASE WHEN preco_promocional > 0 AND (promocao_ate IS NULL OR promocao_ate >= NOW()) THEN 0 ELSE 1 END)')
                ->orderBy('nome', 'asc');
        }

        $produtos = $query->paginate(20);
        $categorias = Categorias::where('ativo', true)->get(['id', 'nome']);

        $produtosFormatados = $produtos->map(function ($produto) {
            $estaEmPromocao = $produto->preco_promocional > 0 &&
                (is_null($produto->promocao_ate) || $produto->promocao_ate >= now());

            return [
                'id'                   => $produto->id,
                'nome'                 => $produto->nome,
                'descricao'            => $produto->descricao,
                'preco'                => $produto->preco,
                'preco_promocional'    => $produto->preco_promocional,
                'preco_atual'          => CalculosService::getPrecoEfetivo($produto),
                'esta_em_promocao'     => $estaEmPromocao,
                'url_imagem'           => $produto->url_imagem,
                'quantidade_estoque'   => $produto->quantidade_estoque,
                'vende_granel'         => $produto->vende_granel,
                'categoria'            => $produto->categoria ? [
                    'id'   => $produto->categoria->id,
                    'nome' => $produto->categoria->nome,
                ] : null,
                'unidade_medida'       => $produto->unidadeMedida ? [
                    'id'    => $produto->unidadeMedida->id,
                    'nome'  => $produto->unidadeMedida->nome,
                    'sigla' => $produto->unidadeMedida->sigla,
                ] : null,
                'empresa'              => [
                    'id'              => $produto->empresa->id,
                    'nome_fantasia'   => $produto->empresa->nome_fantasia,
                    'slug'            => $produto->empresa->slug,
                    'empresa_aberta'  => $produto->empresa->isAberta(),
                    'path_logo'       => $produto->empresa->path_logo,
                ],
            ];
        });

        return [
            'success'    => true,
            'produtos'   => $produtosFormatados,
            'categorias' => $categorias,
            'paginacao'  => [
                'total'            => $produtos->total(),
                'per_page'         => $produtos->perPage(),
                'current_page'     => $produtos->currentPage(),
                'last_page'        => $produtos->lastPage(),
                'has_more_pages'   => $produtos->hasMorePages(),
            ],
        ];
    }
}
