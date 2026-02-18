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
use OpenSpout\Reader\XLSX\Reader as XLSXReader;
use OpenSpout\Writer\XLSX\Writer as XLSXWriter;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Entity\Style\Color;

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

    /**
     * Importar produtos via planilha
     */
    public function importar(Request $request)
    {
        $request->validate([
            'arquivo' => 'required|file|mimes:xlsx,xls|max:5120', // 5MB máximo
        ]);

        try {
            $arquivo = $request->file('arquivo');

            // Validar tamanho máximo de arquivo (5MB)
            if ($arquivo->getSize() > 5242880) {
                return response()->json([
                    'error' => 'Arquivo muito grande',
                    'message' => 'O arquivo deve ter no máximo 5MB'
                ], 400);
            }

            // Ler cabeçalho para identificar o tipo da planilha
            $cabecalho = $this->lerCabecalhoPlanilha($arquivo);

            // Detectar tipo da planilha (por enquanto apenas Petgre)
            $services = [
                'petgre' => \App\Services\Importacao\PetgreImportacaoService::class,
            ];

            $serviceEncontrado = null;
            foreach ($services as $tipo => $serviceClass) {
                $service = new $serviceClass();
                if ($service->validarEstrutura($cabecalho)) {
                    $serviceEncontrado = $service;
                    break;
                }
            }

            if (!$serviceEncontrado) {
                return response()->json([
                    'error' => 'Formato de planilha não reconhecido',
                    'message' => 'A estrutura da planilha não corresponde a nenhum formato suportado. Baixe o modelo correto.'
                ], 400);
            }

            // Processar importação
            $resultado = $serviceEncontrado->importar($arquivo);

            return response()->json([
                'message' => 'Importação concluída',
                'total' => $resultado['total'],
                'importados' => $resultado['importados'],
                'erros' => $resultado['erros']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro na importação',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download do modelo de planilha
     */
    public function downloadModelo(Request $request)
    {
        try {
            $cabecalho = [
                'Nome*',
                'Descrição',
                'Categoria',
                'Unidade de Medida',
                'Preço*',
                'Estoque',
                'Marca',
                'SKU',
                'Preço de Custo',
                'Estoque Mínimo',
                'Peso (kg)',
                'Altura (cm)',
                'Largura (cm)',
                'Comprimento (cm)',
                'Ordem',
                'Preço Promocional',
                'Promoção Até (YYYY-MM-DD)',
                'Vende a Granel (S/N)',
                'Tipo (produto/serviço)',
                'Ativo (S/N)',
                'Destaque (S/N)'
            ];

            // Criar HTML formatado que o Excel pode abrir com múltiplas planilhas
            $htmlContent = $this->criarHtmlParaExcelMultiplasPlanilhas($cabecalho);

            // Criar arquivo temporário
            $tempFile = tempnam(sys_get_temp_dir(), 'modelo_produtos_') . '.xls';

            // Escrever conteúdo no arquivo
            file_put_contents($tempFile, $htmlContent);

            // Retornar arquivo para download
            return response()->download($tempFile, 'modelo_produtos_petgre.xls')->deleteFileAfterSend();

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao gerar modelo',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cria HTML formatado para Excel com múltiplas planilhas
     */
    private function criarHtmlParaExcelMultiplasPlanilhas(array $cabecalho): string
    {
        $html = '<?xml version="1.0"?>';
        $html .= '<?mso-application progid="Excel.Sheet"?>';
        $html .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
        $html .= ' xmlns:o="urn:schemas-microsoft-com:office:office"';
        $html .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"';
        $html .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"';
        $html .= ' xmlns:html="http://www.w3.org/TR/REC-html40">';

        // Styles
        $html .= '<Styles>';
        $html .= '<Style ss:ID="Default" ss:Name="Normal">';
        $html .= '<Alignment ss:Vertical="Bottom"/>';
        $html .= '<Borders/>';
        $html .= '<Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000"/>';
        $html .= '<Interior/>';
        $html .= '<NumberFormat/>';
        $html .= '<Protection/>';
        $html .= '</Style>';
        $html .= '<Style ss:ID="HeaderStyle">';
        $html .= '<Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="14" ss:Color="#FFFFFF" ss:Bold="1"/>';
        $html .= '<Interior ss:Color="#3b82f6" ss:Pattern="Solid"/>';
        $html .= '</Style>';
        $html .= '<Style ss:ID="CellStyle">';
        $html .= '<Borders>';
        $html .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>';
        $html .= '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>';
        $html .= '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>';
        $html .= '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>';
        $html .= '</Borders>';
        $html .= '</Style>';
        $html .= '</Styles>';

        // Planilha 1: Modelo
        $html .= '<Worksheet ss:Name="Modelo">';
        $html .= '<Table ss:ExpandedColumnCount="' . count($cabecalho) . '" ss:ExpandedRowCount="1" x:FullColumns="1" x:FullRows="1">';

        // Definir larguras das colunas
        foreach ($cabecalho as $coluna) {
            $largura = $this->calcularLarguraColuna($coluna);
            $html .= '<Column ss:Width="' . $largura . '"/>';
        }

        // Cabeçalho
        $html .= '<Row>';
        foreach ($cabecalho as $coluna) {
            $html .= '<Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">' . htmlspecialchars($coluna) . '</Data></Cell>';
        }
        $html .= '</Row>';

        $html .= '</Table>';
        $html .= '</Worksheet>';

        // Planilha 2: REGRAS
        $html .= '<Worksheet ss:Name="REGRAS">';
        $html .= '<Table ss:ExpandedColumnCount="3" ss:ExpandedRowCount="25" x:FullColumns="1" x:FullRows="1">';

        // Definir larguras para a planilha de regras
        $html .= '<Column ss:Width="200"/>';
        $html .= '<Column ss:Width="150"/>';
        $html .= '<Column ss:Width="400"/>';

        // Cabeçalho da planilha de regras
        $html .= '<Row>';
        $html .= '<Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Campo</Data></Cell>';
        $html .= '<Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Obrigatório</Data></Cell>';
        $html .= '<Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Regras de Preenchimento</Data></Cell>';
        $html .= '</Row>';

        // Regras para cada campo
        $regras = $this->getRegrasCampos();
        foreach ($regras as $regra) {
            $html .= '<Row>';
            $html .= '<Cell ss:StyleID="CellStyle"><Data ss:Type="String">' . htmlspecialchars($regra['campo']) . '</Data></Cell>';
            $html .= '<Cell ss:StyleID="CellStyle"><Data ss:Type="String">' . htmlspecialchars($regra['obrigatorio']) . '</Data></Cell>';
            $html .= '<Cell ss:StyleID="CellStyle"><Data ss:Type="String">' . htmlspecialchars($regra['regras']) . '</Data></Cell>';
            $html .= '</Row>';
        }

        $html .= '</Table>';
        $html .= '</Worksheet>';

        $html .= '</Workbook>';

        return $html;
    }

    /**
     * Retorna as regras de preenchimento para cada campo
     */
    private function getRegrasCampos(): array
    {
        return [
            [
                'campo' => 'Nome*',
                'obrigatorio' => 'Sim',
                'regras' => 'Nome do produto. Máximo 255 caracteres. Deve ser único por empresa.'
            ],
            [
                'campo' => 'Descrição',
                'obrigatorio' => 'Não',
                'regras' => 'Descrição detalhada do produto. Máximo 1000 caracteres.'
            ],
            [
                'campo' => 'Categoria',
                'obrigatorio' => 'Não',
                'regras' => 'Categoria do produto. Deve existir no sistema (ex: Rações, Brinquedos, Higiene).'
            ],
            [
                'campo' => 'Unidade de Medida',
                'obrigatorio' => 'Não',
                'regras' => 'Unidade de venda (ex: Unidade, Pacote, Quilo, Litro). Deve existir no sistema.'
            ],
            [
                'campo' => 'Preço*',
                'obrigatorio' => 'Sim',
                'regras' => 'Preço de venda. Use ponto como separador decimal (ex: 29.90).'
            ],
            [
                'campo' => 'Estoque',
                'obrigatorio' => 'Não',
                'regras' => 'Quantidade em estoque. Para serviços, deixe em branco ou 0.'
            ],
            [
                'campo' => 'Marca',
                'obrigatorio' => 'Não',
                'regras' => 'Marca/fabricante do produto.'
            ],
            [
                'campo' => 'SKU',
                'obrigatorio' => 'Não',
                'regras' => 'Código único do produto. Deve ser único por empresa.'
            ],
            [
                'campo' => 'Preço de Custo',
                'obrigatorio' => 'Não',
                'regras' => 'Preço pago pelo produto. Use ponto como separador decimal.'
            ],
            [
                'campo' => 'Estoque Mínimo',
                'obrigatorio' => 'Não',
                'regras' => 'Quantidade mínima para alertar reposição. Padrão: 0.'
            ],
            [
                'campo' => 'Peso (kg)',
                'obrigatorio' => 'Não',
                'regras' => 'Peso do produto em quilogramas. Use ponto como separador decimal.'
            ],
            [
                'campo' => 'Altura (cm)',
                'obrigatorio' => 'Não',
                'regras' => 'Altura em centímetros. Para cálculo de frete.'
            ],
            [
                'campo' => 'Largura (cm)',
                'obrigatorio' => 'Não',
                'regras' => 'Largura em centímetros. Para cálculo de frete.'
            ],
            [
                'campo' => 'Comprimento (cm)',
                'obrigatorio' => 'Não',
                'regras' => 'Comprimento em centímetros. Para cálculo de frete.'
            ],
            [
                'campo' => 'Ordem',
                'obrigatorio' => 'Não',
                'regras' => 'Ordem de exibição. Número inteiro. Padrão: 0.'
            ],
            [
                'campo' => 'Preço Promocional',
                'obrigatorio' => 'Não',
                'regras' => 'Preço em promoção. Use ponto como separador decimal.'
            ],
            [
                'campo' => 'Promoção Até (YYYY-MM-DD)',
                'obrigatorio' => 'Não',
                'regras' => 'Data limite da promoção. Formato: YYYY-MM-DD (ex: 2024-12-31).'
            ],
            [
                'campo' => 'Vende a Granel (S/N)',
                'obrigatorio' => 'Não',
                'regras' => 'S = Sim, N = Não. Permite venda fracionada.'
            ],
            [
                'campo' => 'Tipo (produto/serviço)',
                'obrigatorio' => 'Não',
                'regras' => 'Digite "produto" ou "serviço". Serviços não têm estoque.'
            ],
            [
                'campo' => 'Ativo (S/N)',
                'obrigatorio' => 'Não',
                'regras' => 'S = Produto ativo, N = Produto inativo. Padrão: S.'
            ],
            [
                'campo' => 'Destaque (S/N)',
                'obrigatorio' => 'Não',
                'regras' => 'S = Produto em destaque, N = Normal. Padrão: N.'
            ]
        ];
    }

    /**
     * Calcula largura mínima da coluna baseada no texto
     */
    private function calcularLarguraColuna(string $texto): int
    {
        $comprimento = strlen($texto);

        // Mapeamento de larguras mínimas por tipo de conteúdo
        $largurasEspeciais = [
            'Nome*' => 200,
            'Descrição' => 250,
            'Categoria' => 120,
            'Unidade de Medida' => 160,
            'Preço*' => 100,
            'Estoque' => 100,
            'Marca' => 120,
            'SKU' => 100,
            'Preço de Custo' => 140,
            'Estoque Mínimo' => 140,
            'Peso (kg)' => 100,
            'Altura (cm)' => 110,
            'Largura (cm)' => 120,
            'Comprimento (cm)' => 140,
            'Ordem' => 80,
            'Preço Promocional' => 160,
            'Promoção Até (YYYY-MM-DD)' => 200,
            'Vende a Granel (S/N)' => 160,
            'Tipo (produto/serviço)' => 180,
            'Ativo (S/N)' => 110,
            'Destaque (S/N)' => 130,
        ];

        // Retornar largura específica se existir, senão calcular baseada no comprimento
        if (isset($largurasEspeciais[$texto])) {
            return $largurasEspeciais[$texto];
        }

        // Largura mínima baseada no comprimento (aproximadamente 8px por caractere)
        return max(120, $comprimento * 8);
    }

    /**
     * Converte array para CSV
     */
    private function arrayToCsv(array $data): string
    {
        $output = '';

        foreach ($data as $row) {
            $escapedRow = array_map(function ($field) {
                // Escapar campos que contenham vírgulas ou aspas
                if (strpos($field, ',') !== false || strpos($field, '"') !== false) {
                    return '"' . str_replace('"', '""', $field) . '"';
                }
                return $field;
            }, $row);

            $output .= implode(',', $escapedRow) . "\n";
        }

        return $output;
    }

    /**
     * Lê o cabeçalho da planilha
     */
    private function lerCabecalhoPlanilha($arquivo): array
    {
        $extensao = strtolower($arquivo->getClientOriginalExtension());

        if ($extensao === 'xlsx') {
            $reader = new \OpenSpout\Reader\XLSX\Reader();
        } elseif ($extensao === 'xls') {
            $reader = new \OpenSpout\Reader\XLSX\Reader();
        } else {
            throw new \Exception('Formato de arquivo não suportado');
        }

        $reader->open($arquivo->getPathname());

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cabecalho = $row->toArray();
                $reader->close();
                return $cabecalho;
            }
        }

        $reader->close();
        return [];
    }
}