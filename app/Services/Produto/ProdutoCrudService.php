<?php

namespace App\Services\Produto;

use App\Helpers\VerificaEmpresa;
use App\Http\Requests\Produto\ProdutoStoreRequest;
use App\Http\Requests\Produto\ProdutoUpdateRequest;
use App\Models\Produto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProdutoCrudService
{
    public function obterDetalhe(string $id): ?Produto
    {
        $produto = Produto::with(['categoria', 'unidadeMedida', 'empresa'])->findOrFail($id);

        if (! VerificaEmpresa::verificaEmpresaPertenceAoUsuario($produto->empresa_id)) {
            return null;
        }

        return $produto;
    }

    public function criar(ProdutoStoreRequest $request): Produto
    {
        DB::beginTransaction();
        try {
            $dados = $request->only([
                'categoria_id',
                'unidade_medida_id',
                'tipo',
                'nome',
                'imagem',
                'slug',
                'descricao',
                'preco',
                'estoque',
                'destaque',
                'ativo',
                'marca',
                'sku',
                'preco_custo',
                'estoque_minimo',
                'ativar_estoque_minimo',
                'peso',
                'altura',
                'largura',
                'comprimento',
                'ordem',
                'preco_promocional',
                'preco_promocional_percentual',
                'promocao_ate',
                'tem_promocao',
                'vende_granel',
                'tipo_porte',
                'preco_pequeno',
                'preco_medio',
                'preco_grande',
                'porte_descricao_pequeno',
                'porte_descricao_medio',
                'porte_descricao_grande',
                'duracao_estimada',
                'inclui_servico',
            ]);

            $dados['empresa_id'] = $request->empresa_id;

            if ($request->tipo === 'servico' && $request->tipo_porte === 'todos' && ($dados['preco'] === null || $dados['preco'] === '')) {
                $dados['preco'] = $request->preco_pequeno ?? 0;
            }

            $dados['estoque'] = $request->tipo === 'servico' ? 0 : ($request->estoque ?? 0);
            $dados['destaque'] = $request->boolean('destaque', false);
            $dados['ativo'] = $request->boolean('ativo', true);
            $temPrecoPromocional = $request->filled('preco_promocional') && $request->preco_promocional > 0;
            $temPercentual = $request->filled('preco_promocional_percentual') && $request->preco_promocional_percentual > 0;
            $dados['tem_promocao'] = $request->boolean('tem_promocao', false) && ($temPrecoPromocional || $temPercentual);
            $dados['slug'] = $request->slug ?: Str::slug($request->nome);

            $produto = Produto::create($dados);

            DB::commit();

            return $produto->load(['categoria', 'unidadeMedida', 'empresa']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function atualizar(ProdutoUpdateRequest $request, string $id): Produto
    {
        $produto = Produto::findOrFail($id);

        if (! VerificaEmpresa::verificaEmpresaPertenceAoUsuario($produto->empresa_id)) {
            throw new \InvalidArgumentException('acesso_negado');
        }

        DB::beginTransaction();
        try {
            $updateData = $request->only([
                'categoria_id',
                'unidade_medida_id',
                'tipo',
                'nome',
                'imagem',
                'slug',
                'descricao',
                'preco',
                'estoque',
                'destaque',
                'ativo',
                'marca',
                'sku',
                'preco_custo',
                'estoque_minimo',
                'ativar_estoque_minimo',
                'peso',
                'altura',
                'largura',
                'comprimento',
                'ordem',
                'preco_promocional',
                'preco_promocional_percentual',
                'promocao_ate',
                'tem_promocao',
                'vende_granel',
                'tipo_porte',
                'preco_pequeno',
                'preco_medio',
                'preco_grande',
                'porte_descricao_pequeno',
                'porte_descricao_medio',
                'porte_descricao_grande',
                'duracao_estimada',
                'inclui_servico',
            ]);

            if ($request->filled('nome') && (! $request->has('slug') || empty($request->slug))) {
                $updateData['slug'] = Str::slug($request->nome);
            }

            if ($request->has('tipo') && $request->tipo === 'servico') {
                $updateData['estoque'] = 0;
            }

            if ($request->has('tem_promocao')) {
                $temPrecoPromocional = $request->filled('preco_promocional') && $request->preco_promocional > 0;
                $temPercentual = $request->filled('preco_promocional_percentual') && $request->preco_promocional_percentual > 0;
                $updateData['tem_promocao'] = $request->boolean('tem_promocao', false) && ($temPrecoPromocional || $temPercentual);
            }

            $produto->update($updateData);

            DB::commit();

            return $produto->load(['categoria', 'unidadeMedida', 'empresa']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @return array{ok: true, produto_id: string}|array{ok: false, codigo: 'acesso'|'em_pedidos'}
     */
    public function excluir(string $id): array
    {
        $produto = Produto::findOrFail($id);

        if (! VerificaEmpresa::verificaEmpresaPertenceAoUsuario($produto->empresa_id)) {
            return ['ok' => false, 'codigo' => 'acesso'];
        }

        if ($produto->itens()->exists()) {
            return ['ok' => false, 'codigo' => 'em_pedidos'];
        }

        $produto->delete();

        return ['ok' => true, 'produto_id' => $id];
    }
}
