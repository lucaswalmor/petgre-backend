<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\Produto\ProdutoStoreRequest;
use App\Http\Requests\Produto\ProdutoUpdateRequest;
use App\Http\Resources\Produto\ProdutoResource;
use App\Models\Produto;
use App\Models\Categorias;
use App\Models\UnidadeMedida;
use App\Http\Requests\Produto\ProdutoLoteRequest;
use Illuminate\Support\Facades\Auth;
use App\Helpers\VerificaEmpresa;
use App\Http\Requests\Produto\ProdutoUploadImageRequest;
use App\Models\Empresa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProdutoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $usuarioAutenticado = Auth::user();

        // Todos os usuários veem apenas produtos das suas empresas
        $empresasIds = $usuarioAutenticado->empresas->pluck('id');

        $query = Produto::whereIn('empresa_id', $empresasIds)
            ->with(['categoria', 'unidadeMedida', 'empresa']);

        // Busca por texto (nome, descrição, SKU, marca)
        if ($request->filled('q')) {
            $busca = $request->get('q');
            $query->where(function ($subQuery) use ($busca) {
                $subQuery
                    ->where('nome', 'like', "%{$busca}%")
                    ->orWhere('descricao', 'like', "%{$busca}%")
                    ->orWhere('sku', 'like', "%{$busca}%")
                    ->orWhere('marca', 'like', "%{$busca}%");
            });
        }

        // Filtros opcionais
        if ($request->has('empresa_id') && $request->empresa_id) {
            $query->where('empresa_id', $request->empresa_id);
        }

        if ($request->has('categoria_id') && $request->categoria_id) {
            $query->where('categoria_id', $request->categoria_id);
        }

        if ($request->has('tipo') && $request->tipo) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('ativo') && $request->ativo !== null) {
            $query->where('ativo', $request->boolean('ativo'));
        }

        if ($request->has('destaque') && $request->destaque !== null) {
            $query->where('destaque', $request->boolean('destaque'));
        }

        if ($request->has('somente_promocao') && $request->boolean('somente_promocao')) {
            $query->where('tem_promocao', true);
        }

        if ($request->has('vende_granel') && $request->vende_granel !== null) {
            $query->where('vende_granel', $request->boolean('vende_granel'));
        }

        // Estoque: baixo (<= estoque_minimo), zerado (<=0)
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

        // Ordenação
        $orderBy = $request->get('order_by', 'created_at');
        $orderDirection = $request->get('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        // Paginação
        $perPage = $request->get('per_page', 15);
        $produtos = $query->paginate($perPage);

        return response()->json([
            'produtos' => ProdutoResource::collection($produtos),
            'paginacao' => [
                'total' => $produtos->total(),
                'per_page' => $produtos->perPage(),
                'current_page' => $produtos->currentPage(),
                'last_page' => $produtos->lastPage(),
                'from' => $produtos->firstItem(),
                'to' => $produtos->lastItem(),
                'has_more_pages' => $produtos->hasMorePages(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProdutoStoreRequest $request)
    {
        DB::beginTransaction();
        try {
            // Verificar se a empresa pertence ao usuário autenticado
            if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($request->empresa_id)) {
                return response()->json([
                    'error' => 'Acesso negado',
                    'message' => 'Você não tem permissão para criar produtos nesta empresa.'
                ], 403);
            }

            $dados = $request->only([
                'empresa_id',
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
                'peso',
                'altura',
                'largura',
                'comprimento',
                'ordem',
                'preco_promocional',
                'promocao_ate',
                'tem_promocao',
                'vende_granel',
            ]);

            // Ajustes de defaults
            $dados['estoque'] = $request->tipo === 'servico' ? 0 : ($request->estoque ?? 0);
            $dados['destaque'] = $request->boolean('destaque', false);
            $dados['ativo'] = $request->boolean('ativo', true);
            $dados['tem_promocao'] = $request->boolean('tem_promocao', false) && $request->preco_promocional;
            $dados['slug'] = $request->slug ?: Str::slug($request->nome);

            $produto = Produto::create($dados);

            DB::commit();

            // Carregar relacionamentos
            $produto->load(['categoria', 'unidadeMedida', 'empresa']);

            return response()->json([
                'message' => 'Produto criado com sucesso',
                'produto' => new ProdutoResource($produto)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Erro ao criar produto',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $produto = Produto::with(['categoria', 'unidadeMedida', 'empresa'])->findOrFail($id);

        // Verificar se o usuário tem acesso ao produto (mesma empresa)
        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($produto->empresa_id)) {
            return response()->json([
                'error' => 'Acesso negado',
                'message' => 'Você não tem permissão para visualizar este produto.'
            ], 403);
        }

        return response()->json([
            'produto' => new ProdutoResource($produto)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProdutoUpdateRequest $request, string $id)
    {
        $produto = Produto::findOrFail($id);

        // Verificar se o usuário tem acesso ao produto (mesma empresa)
        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($produto->empresa_id)) {
            return response()->json([
                'error' => 'Acesso negado',
                'message' => 'Você não tem permissão para editar este produto.'
            ], 403);
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
                'peso',
                'altura',
                'largura',
                'comprimento',
                'ordem',
                'preco_promocional',
                'promocao_ate',
                'tem_promocao',
                'vende_granel',
            ]);

            if ($request->filled('nome') && (!$request->has('slug') || empty($request->slug))) {
                $updateData['slug'] = Str::slug($request->nome);
            }

            if ($request->has('tipo') && $request->tipo === 'servico') {
                $updateData['estoque'] = 0;
            }

            if ($request->has('tem_promocao')) {
                $updateData['tem_promocao'] = $request->boolean('tem_promocao', false) && $request->preco_promocional;
            }

            $produto->update($updateData);

            DB::commit();

            // Recarregar relacionamentos
            $produto->load(['categoria', 'unidadeMedida', 'empresa']);

            return response()->json([
                'message' => 'Produto atualizado com sucesso',
                'produto' => new ProdutoResource($produto)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Erro ao atualizar produto',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $produto = Produto::findOrFail($id);

        // Verificar se o usuário tem acesso ao produto (mesma empresa)
        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($produto->empresa_id)) {
            return response()->json([
                'error' => 'Acesso negado',
                'message' => 'Você não tem permissão para deletar este produto.'
            ], 403);
        }

        // Verificar se o produto está sendo usado em pedidos
        if ($produto->itens()->exists()) {
            return response()->json([
                'error' => 'Não é possível deletar este produto',
                'message' => 'O produto está sendo usado em pedidos existentes.'
            ], 400);
        }

        // Soft delete
        $produto->delete();

        return response()->json([
            'message' => 'Produto deletado com sucesso'
        ]);
    }

    /**
     * Toggle destaque do produto
     */
    public function toggleDestaque(string $id)
    {
        $produto = Produto::findOrFail($id);

        // Verificar se o usuário tem acesso ao produto (mesma empresa)
        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($produto->empresa_id)) {
            return response()->json([
                'error' => 'Acesso negado',
                'message' => 'Você não tem permissão para alterar este produto.'
            ], 403);
        }

        $produto->destaque = !$produto->destaque;
        $produto->save();

        return response()->json([
            'message' => 'Status de destaque alterado com sucesso',
            'produto' => new ProdutoResource($produto->load(['categoria', 'unidadeMedida', 'empresa']))
        ]);
    }

    /**
     * Toggle status ativo do produto
     */
    public function toggleAtivo(string $id)
    {
        $produto = Produto::findOrFail($id);

        // Verificar se o usuário tem acesso ao produto (mesma empresa)
        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($produto->empresa_id)) {
            return response()->json([
                'error' => 'Acesso negado',
                'message' => 'Você não tem permissão para alterar este produto.'
            ], 403);
        }

        $produto->ativo = !$produto->ativo;
        $produto->save();

        return response()->json([
            'message' => 'Status do produto alterado com sucesso',
            'produto' => new ProdutoResource($produto->load(['categoria', 'unidadeMedida', 'empresa']))
        ]);
    }

    /**
     * Buscar produtos por nome ou categoria
     */
    public function search(Request $request)
    {
        $usuarioAutenticado = Auth::user();
        $empresasIds = $usuarioAutenticado->empresas->pluck('id');

        $query = $request->get('q', '');
        $categoriaId = $request->get('categoria_id');
        $tipo = $request->get('tipo');

        $produtos = Produto::whereIn('empresa_id', $empresasIds)
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

        return response()->json([
            'produtos' => ProdutoResource::collection($produtos)
        ]);
    }


    /**
     * Upload ou atualização de imagem do produto
     */
    public function uploadImage(ProdutoUploadImageRequest $request, string $id)
    {
        try {
            $produto = Produto::findOrFail($id);

            // Verificar se o usuário tem acesso ao produto (mesma empresa)
            if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($produto->empresa_id)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Acesso negado',
                    'message' => 'Você não tem permissão para acessar este produto.'
                ], 403);
            }

            $dadosAtualizacao = [];

            if ($request->hasFile('imagem')) {
                // Remove imagem anterior se existir
                if ($produto->imagem) {
                    $imagemPathRelativo = str_replace(env('CLOUDFLARE_R2_PUBLIC_URL') . '/', '', $produto->imagem);
                    Storage::disk('r2')->delete($imagemPathRelativo);
                }

                $imagemPath = $request->file('imagem')->store("empresas/produtos/{$produto->empresa_id}/{$produto->id}/produto", 'r2');
                $dadosAtualizacao['imagem'] = env('CLOUDFLARE_R2_PUBLIC_URL') . '/' . $imagemPath;
            }

            if (!empty($dadosAtualizacao)) {
                $produto->update($dadosAtualizacao);

                return response()->json([
                    'success' => true,
                    'message' => 'Imagem do produto atualizada com sucesso',
                    'produto' => new ProdutoResource($produto->load(['categoria', 'unidadeMedida', 'empresa']))
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Nenhuma imagem foi enviada'
            ], 400);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Produto não encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro interno do servidor',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicar produto
     */
    public function duplicar(string $id)
    {
        $produto = Produto::findOrFail($id);

        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($produto->empresa_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Você não tem permissão para duplicar este produto.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $novoNome = $produto->nome . ' - Cópia';
            $contador = 1;
            while (Produto::where('empresa_id', $produto->empresa_id)->where('nome', $novoNome)->exists()) {
                $contador++;
                $novoNome = $produto->nome . " - Cópia {$contador}";
            }

            $novoProduto = $produto->replicate(['imagem', 'sku', 'slug']);
            $novoProduto->nome = $novoNome;
            $novoProduto->slug = Str::slug($novoNome) . '-' . uniqid();
            $novoProduto->sku = null;
            $novoProduto->imagem = null;
            $novoProduto->tem_promocao = $produto->tem_promocao && $produto->preco_promocional ? true : false;
            $novoProduto->save();

            DB::commit();

            $novoProduto->load(['categoria', 'unidadeMedida', 'empresa']);

            return response()->json([
                'success' => true,
                'message' => 'Produto duplicado com sucesso',
                'produto' => new ProdutoResource($novoProduto)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erro ao duplicar produto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cadastro em lote de produtos
     */
    public function storeLote(\App\Http\Requests\Produto\ProdutoLoteRequest $request)
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

                if (!$empresaId || !in_array($empresaId, $empresasIds)) {
                    $erros[] = [
                        'index' => $index,
                        'message' => 'Empresa inválida para o usuário autenticado'
                    ];
                    continue;
                }

                try {
                    $dados = [
                        'empresa_id' => $empresaId,
                        'categoria_id' => $produtoDados['categoria_id'],
                        'unidade_medida_id' => $produtoDados['unidade_medida_id'],
                        'tipo' => $produtoDados['tipo'] ?? 'produto',
                        'nome' => $produtoDados['nome'],
                        'slug' => Str::slug($produtoDados['nome']),
                        'descricao' => $produtoDados['descricao'] ?? null,
                        'preco' => $produtoDados['preco'],
                        'estoque' => ($produtoDados['tipo'] ?? 'produto') === 'servico' ? 0 : ($produtoDados['estoque'] ?? 0),
                        'destaque' => $produtoDados['destaque'] ?? false,
                        'ativo' => $produtoDados['ativo'] ?? true,
                        'marca' => $produtoDados['marca'] ?? null,
                        'sku' => $produtoDados['sku'] ?? null,
                        'preco_custo' => $produtoDados['preco_custo'] ?? null,
                        'estoque_minimo' => $produtoDados['estoque_minimo'] ?? 0,
                        'peso' => $produtoDados['peso'] ?? null,
                        'altura' => $produtoDados['altura'] ?? null,
                        'largura' => $produtoDados['largura'] ?? null,
                        'comprimento' => $produtoDados['comprimento'] ?? null,
                        'ordem' => $produtoDados['ordem'] ?? 0,
                        'preco_promocional' => $produtoDados['preco_promocional'] ?? null,
                        'promocao_ate' => $produtoDados['promocao_ate'] ?? null,
                        'tem_promocao' => ($produtoDados['tem_promocao'] ?? false) && !empty($produtoDados['preco_promocional']),
                        'vende_granel' => $produtoDados['vende_granel'] ?? false,
                    ];

                    $produtoCriado = Produto::create($dados);
                    $criados[] = new ProdutoResource($produtoCriado->load(['categoria', 'unidadeMedida', 'empresa']));
                } catch (\Exception $e) {
                    $erros[] = [
                        'index' => $index,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            if (!empty($erros)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Alguns produtos não puderam ser cadastrados.',
                    'criados' => $criados,
                    'erros' => $erros,
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produtos cadastrados com sucesso.',
                'produtos' => $criados,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erro ao cadastrar produtos em lote.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar categorias
     */
    public function listarCategorias()
    {
        $categorias = Categorias::orderBy('nome')->get();

        return response()->json([
            'success' => true,
            'categorias' => $categorias,
        ]);
    }

    /**
     * Deletar produtos em lote
     */
    public function destroyLote(Request $request)
    {
        $usuarioAutenticado = Auth::user();
        $empresasIds = $usuarioAutenticado->empresas->pluck('id')->toArray();

        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json([
                'error' => 'IDs inválidos',
                'message' => 'Nenhum ID de produto foi fornecido.'
            ], 400);
        }

        // Verificar se todos os produtos pertencem às empresas do usuário
        $produtos = Produto::whereIn('id', $ids)->get();

        foreach ($produtos as $produto) {
            if (!in_array($produto->empresa_id, $empresasIds)) {
                return response()->json([
                    'error' => 'Acesso negado',
                    'message' => 'Você não tem permissão para deletar alguns dos produtos selecionados.'
                ], 403);
            }

            // Verificar se o produto está sendo usado em pedidos
            if ($produto->itens()->exists()) {
                return response()->json([
                    'error' => 'Não é possível deletar produtos em lote',
                    'message' => "O produto '{$produto->nome}' está sendo usado em pedidos existentes."
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            // Soft delete dos produtos
            Produto::whereIn('id', $ids)->delete();

            DB::commit();

            return response()->json([
                'message' => count($ids) . ' produto(s) deletado(s) com sucesso'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Erro ao deletar produtos',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar unidades de medida
     */
    public function listarUnidadesMedidas()
    {
        $unidades = UnidadeMedida::orderBy('nome')->get();

        return response()->json([
            'success' => true,
            'unidades' => $unidades,
        ]);
    }
}