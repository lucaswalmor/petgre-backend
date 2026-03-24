<?php

namespace App\Services\Produto;

use App\Http\Requests\Produto\ProdutoLoteRequest;
use App\Http\Resources\Produto\ProdutoResource;
use App\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProdutoLoteService
{
    /**
     * @return array{ok: true, produtos: array<int, ProdutoResource>}|array{ok: false, http: int, message: string, criados?: array, erros?: array}
     */
    public function cadastrarLote(ProdutoLoteRequest $request): array
    {
        $usuarioAutenticado = Auth::user();
        $empresasIds = $usuarioAutenticado->empresas->pluck('id')->toArray();

        $produtosPayload = $request->input('produtos', []);
        $criados = [];
        $erros = [];

        DB::beginTransaction();
        try {
            foreach ($produtosPayload as $index => $produtoDados) {
                $empresaId = $produtoDados['empresa_id'] ?? null;

                if (! $empresaId || ! in_array($empresaId, $empresasIds, true)) {
                    $erros[] = [
                        'index'   => $index,
                        'message' => 'Empresa não reconhecida para este usuário',
                    ];
                    continue;
                }

                try {
                    $dados = [
                        'empresa_id'                   => $empresaId,
                        'categoria_id'                 => $produtoDados['categoria_id'],
                        'unidade_medida_id'            => $produtoDados['unidade_medida_id'],
                        'tipo'                         => $produtoDados['tipo'] ?? 'produto',
                        'nome'                         => $produtoDados['nome'],
                        'slug'                         => Str::slug($produtoDados['nome']),
                        'descricao'                    => $produtoDados['descricao'] ?? null,
                        'preco'                        => $produtoDados['preco'],
                        'estoque'                      => ($produtoDados['tipo'] ?? 'produto') === 'servico' ? 0 : ($produtoDados['estoque'] ?? 0),
                        'destaque'                     => $produtoDados['destaque'] ?? false,
                        'ativo'                        => $produtoDados['ativo'] ?? true,
                        'marca'                        => $produtoDados['marca'] ?? null,
                        'sku'                          => $produtoDados['sku'] ?? null,
                        'preco_custo'                  => $produtoDados['preco_custo'] ?? null,
                        'estoque_minimo'               => $produtoDados['estoque_minimo'] ?? 0,
                        'ativar_estoque_minimo'        => $produtoDados['ativar_estoque_minimo'] ?? false,
                        'peso'                         => $produtoDados['peso'] ?? null,
                        'altura'                       => $produtoDados['altura'] ?? null,
                        'largura'                      => $produtoDados['largura'] ?? null,
                        'comprimento'                  => $produtoDados['comprimento'] ?? null,
                        'ordem'                        => $produtoDados['ordem'] ?? 0,
                        'preco_promocional'            => $produtoDados['preco_promocional'] ?? null,
                        'preco_promocional_percentual' => $produtoDados['preco_promocional_percentual'] ?? null,
                        'promocao_ate'                 => $produtoDados['promocao_ate'] ?? null,
                        'tem_promocao'                 => ($produtoDados['tem_promocao'] ?? false)
                            && (! empty($produtoDados['preco_promocional']) || ! empty($produtoDados['preco_promocional_percentual'])),
                        'vende_granel'                 => $produtoDados['vende_granel'] ?? false,
                    ];

                    $produtoCriado = Produto::create($dados);
                    $criados[] = new ProdutoResource($produtoCriado->load(['categoria', 'unidadeMedida', 'empresa']));
                } catch (\Exception $e) {
                    $erros[] = [
                        'index'   => $index,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            if (! empty($erros)) {
                DB::rollBack();

                return [
                    'ok'      => false,
                    'http'    => 422,
                    'message' => 'Alguns produtos não puderam ser cadastrados.',
                    'criados' => $criados,
                    'erros'   => $erros,
                ];
            }

            DB::commit();

            return [
                'ok'       => true,
                'produtos' => $criados,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'ok'      => false,
                'http'    => 500,
                'message' => 'Não foi possível cadastrar os produtos. Verifique os dados e tente novamente.',
            ];
        }
    }

    /**
     * @return array{ok: true, message: string}|array{ok: false, http: int, error?: string, message: string}
     */
    public function excluirEmLote(Request $request): array
    {
        $usuarioAutenticado = Auth::user();
        $empresasIds = $usuarioAutenticado->empresas->pluck('id')->toArray();

        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return [
                'ok'      => false,
                'http'    => 400,
                'error'   => 'Dados inválidos',
                'message' => 'Selecione pelo menos um produto para remover.',
            ];
        }

        $produtos = Produto::whereIn('id', $ids)->get();

        foreach ($produtos as $produto) {
            if (! in_array($produto->empresa_id, $empresasIds, true)) {
                return [
                    'ok'      => false,
                    'http'    => 403,
                    'error'   => 'Acesso não permitido',
                    'message' => 'Você não tem acesso a alguns dos produtos selecionados.',
                ];
            }

            if ($produto->itens()->exists()) {
                return [
                    'ok'      => false,
                    'http'    => 400,
                    'error'   => 'Não foi possível remover',
                    'message' => "O produto '{$produto->nome}' está sendo usado em pedidos.",
                ];
            }
        }

        DB::beginTransaction();
        try {
            Produto::whereIn('id', $ids)->delete();

            DB::commit();

            return [
                'ok'      => true,
                'message' => count($ids) . ' produto(s) deletado(s) com sucesso',
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'ok'      => false,
                'http'    => 500,
                'error'   => 'Não foi possível remover',
                'message' => 'Tente novamente em alguns instantes.',
            ];
        }
    }
}
