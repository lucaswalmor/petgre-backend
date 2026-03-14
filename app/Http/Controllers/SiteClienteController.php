<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Empresa;
use App\Models\Pedido;
use App\Models\PedidoItems;
use App\Models\PedidoEndereco;
use App\Models\PedidoHistoricoStatus;
use App\Models\NichosEmpresa;
use App\Models\UsuarioEnderecos;
use App\Models\UsuarioCupom;
use App\Models\EmpresaCupom;
use App\Models\EmpresaCupomUsado;
use App\Models\SistemaCupom;
use App\Models\SistemaCupomUsado;
use App\Models\StatusPedidos;
use App\Http\Resources\SiteEmpresaResource;
use App\Http\Resources\Pedido\PedidoResource;
use App\Http\Resources\Usuario\UsuarioResource;
use App\Http\Resources\Api\ApiResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Categorias;
use App\Models\User;
use App\Models\UsuarioLog;
use App\Models\Kit;
use App\Models\Produto;
use Illuminate\Support\Facades\Log;
use App\Services\PushNotificationService;
use App\Services\CalculosService;

class SiteClienteController extends Controller
{
    /**
     * Listar empresas para o site (Público)
     */
    public function getEmpresas(Request $request)
    {
        Log::info($request->all());
        $query = Empresa::where('ativo', true)
            ->where('cadastro_completo', true)
            ->with(['nicho', 'horarios', 'pausasAgendadas', 'avaliacoes', 'bairrosEntregas.bairro', 'configuracoes'])
            ->withCount('avaliacoes')
            ->withAvg('avaliacoes', 'nota');

        // Filtro por nicho
        if ($request->has('nicho_id') && !empty($request->nicho_id)) {
            $query->where('nicho_id', $request->nicho_id);
        }

        // Filtro por busca
        if ($request->has('q') && !empty(trim($request->q))) {
            $query->where(function($q) use ($request) {
                $q->where('nome_fantasia', 'like', '%' . $request->q . '%')
                  ->orWhere('razao_social', 'like', '%' . $request->q . '%');
            });
        }

        // Filtro por bairro
        if ($request->has('bairro') && !empty(trim($request->bairro))) {
            $query->whereHas('bairrosEntregas', function($q) use ($request) {
                $q->whereHas('bairro', function($qb) use ($request) {
                    $qb->where('nome', $request->bairro)
                       ->where('ativo', true);
                })
                ->where('ativo', true);
            });
        }

        // Filtro por status (abertas agora)
        if ($request->has('abertas') && $request->abertas == 'true') {
            $query->whereHas('horarios', function($q) {
                $diaSemana = now()->dayOfWeek;
                $horaAtual = now()->format('H:i:s');
                
                $q->where('dia_semana', $diaSemana)
                  ->where('horario_inicio', '<=', $horaAtual)
                  ->where('horario_fim', '>=', $horaAtual);
            });
        }

        // Filtro por avaliação mínima
        if ($request->has('avaliacao_minima') && $request->avaliacao_minima > 0) {
            $query->having('avaliacoes_avg_nota', '>=', $request->avaliacao_minima);
        }

        // Filtro por tipo de serviço (entrega/retirada)
        if ($request->has('faz_entrega') && $request->faz_entrega == 'true') {
            $query->whereHas('configuracoes', function($q) {
                $q->where('faz_entrega', true);
            });
        }

        if ($request->has('faz_retirada') && $request->faz_retirada == 'true') {
            $query->whereHas('configuracoes', function($q) {
                $q->where('faz_retirada', true);
            });
        }

        // Filtro por favoritos do usuário
        if ($request->has('has_favoritos') && $request->has_favoritos == 'true') {
            if ($request->has('person') && !empty($request->person)) {
                $usuario = User::find($request->person);
                if ($usuario) {
                    $favoritosIds = $usuario->empresaFavoritos()->pluck('empresa_id')->toArray();
                    if (!empty($favoritosIds)) {
                        $query->whereIn('id', $favoritosIds);
                    } else {
                        // Se não tem favoritos, retorna query vazia
                        $query->where('id', 0);
                    }
                }
            } else {
                // Se não enviou o ID do usuário, retorna query vazia
                $query->where('id', 0);
            }
        }

        // Ordenação
        if ($request->has('ordenacao') && !empty($request->ordenacao)) {
            switch ($request->ordenacao) {
                case 'avaliacao':
                    $query->orderByDesc('avaliacoes_avg_nota');
                    break;
                case 'nome_asc':
                    $query->orderBy('nome_fantasia', 'asc');
                    break;
                case 'nome_desc':
                    $query->orderBy('nome_fantasia', 'desc');
                    break;
                default:
                    // Relevância (padrão)
                    $query->orderByDesc('avaliacoes_count')
                          ->orderByDesc('avaliacoes_avg_nota');
            }
        } else {
            // Ordenação padrão por relevância
            $query->orderByDesc('avaliacoes_count')
                  ->orderByDesc('avaliacoes_avg_nota');
        }

        $empresas = $query->paginate(20);
        $nichos = NichosEmpresa::where('ativo', true)->get(['id', 'nome', 'imagem', 'slug']);

        Log::info($empresas);
        Log::info($nichos);
        return response()->json([
            'success' => true,
            'empresas' => SiteEmpresaResource::collection($empresas),
            'nichos' => $nichos,
            'paginacao' => [
                'total' => $empresas->total(),
                'per_page' => $empresas->perPage(),
                'current_page' => $empresas->currentPage(),
                'last_page' => $empresas->lastPage(),
                'has_more_pages' => $empresas->hasMorePages(),
            ]
        ]);
    }

    /**
     * Detalhes de uma empresa (Público)
     */
    public function getEmpresa($slug)
    {
        $empresa = Empresa::where('slug', $slug)
            ->where('ativo', true)
            ->where('cadastro_completo', true)
            ->with([
                'nicho',
                'endereco',
                'horarios',
                'pausasAgendadas',
                'bairrosEntregas.bairro',
                'produtos' => function($query) {
                    $query->where('ativo', true)->with(['categoria', 'unidadeMedida']);
                },
                'kits' => function($query) {
                    $query->where('ativo', true)->with(['itens.produto']);
                },
                'formasPagamentos.formaPagamento',
                'configuracoes',
                'avaliacoes' => function($query) {
                    $query->with('usuario:id,nome')->latest()->limit(10);
                }
            ])
            ->firstOrFail();

        $categorias = Categorias::where('ativo', true)->get();

        // Registrar log de acesso à loja apenas se usuário estiver autenticado
        $usuario = Auth::user();
        if ($usuario) {
            $lojaAberta = $empresa->isAberta();
            $acao = $lojaAberta ? 'acesso_loja_aberta' : 'acesso_loja_fechada';

            UsuarioLog::create([
                'usuario_id' => $usuario->id,
                'empresa_id' => $empresa->id,
                'acao' => $acao,
                'dados_adicionais' => [
                    'horario_acesso' => now()->format('H:i:s'),
                    'dia_semana' => now()->dayOfWeek
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }

        // Buscar ordenação das seções
        $ordenacao = \App\Models\EmpresaOrdenacaoListaLoja::where('empresa_id', $empresa->id)
            ->where('ativo', true)
            ->orderBy('ordem')
            ->pluck('secao')
            ->toArray();

        // Se não tiver ordenação configurada, padrão: servicos, produtos, kits
        if (empty($ordenacao)) {
            $ordenacao = ['servicos', 'produtos', 'kits'];
        }

        return response()->json([
            'success' => true,
            'empresa' => new SiteEmpresaResource($empresa),
            'categorias' => $categorias,
            'ordenacao_secoes' => $ordenacao
        ]);
    }

    /**
     * Obter ordenação pública da loja (para site cliente)
     */
    public function getOrdenacaoPublica($empresaId)
    {
        $ordenacao = \App\Models\EmpresaOrdenacaoListaLoja::where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderBy('ordem')
            ->pluck('secao')
            ->toArray();

        // Se não tiver ordenação configurada, padrão: servicos, produtos, kits
        if (empty($ordenacao)) {
            $ordenacao = ['servicos', 'produtos', 'kits'];
        }

        return response()->json([
            'success' => true,
            'ordenacao' => $ordenacao
        ]);
    }

    /**
     * Histórico de pedidos do usuário (Privado)
     */
    public function getPedidos(Request $request)
    {
        $usuario = Auth::user();
        
        $pedidos = Pedido::where('usuario_id', $usuario->id)
            ->with(['empresa', 'statusPedido', 'itens.produto', 'formaPagamento', 'avaliacao'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'pedidos' => PedidoResource::collection($pedidos),
            'paginacao' => [
                'total' => $pedidos->total(),
                'per_page' => $pedidos->perPage(),
                'current_page' => $pedidos->currentPage(),
                'last_page' => $pedidos->lastPage(),
                'has_more_pages' => $pedidos->hasMorePages(),
            ]
        ]);
    }

    /**
     * Detalhes de um pedido (Privado)
     */
    public function getPedido($id)
    {
        $usuario = Auth::user();
        
        $pedido = Pedido::where('usuario_id', $usuario->id)
            ->with([
                'empresa', 
                'statusPedido', 
                'itens.produto.unidadeMedida', 
                'endereco.endereco', 
                'formaPagamento', 
                'historicoStatus.statusPedido',
                'avaliacao'
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'pedido' => new PedidoResource($pedido)
        ]);
    }

    /**
     * Perfil do usuário (Privado)
     */
    public function getPerfil()
    {
        $usuario = Auth::user();
        $usuario->load(['enderecos', 'empresas']);

        return response()->json([
            'success' => true,
            'usuario' => new UsuarioResource($usuario)
        ]);
    }

    /**
     * Endereços do usuário (Privado)
     */
    public function getEnderecos()
    {
        $usuario = Auth::user();
        $enderecos = UsuarioEnderecos::where('usuario_id', $usuario->id)
            ->where('ativo', true)
            ->get();

        return response()->json([
            'success' => true,
            'enderecos' => $enderecos
        ]);
    }

    /**
     * Cupons do usuário (Privado)
     */
    public function meusCupons()
    {
        $usuario = Auth::user();

        // Cupons do sistema atribuídos ao usuário
        $cuponsSistema = UsuarioCupom::where('usuario_id', $usuario->id)
            ->naoUtilizados()
            ->with('cupom')
            ->get();

        return response()->json([
            'success' => true,
            'cupons' => $cuponsSistema
        ]);
    }

    /**
     * Criar pedido (Privado - Cliente)
     */
    public function storePedido(Request $request)
    {
        $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'pagamento_id' => 'required|exists:formas_pagamento,id',
            'subtotal' => 'required|numeric|min:0',
            'desconto' => 'nullable|numeric|min:0',
            'frete' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'observacoes' => 'nullable|string',
            'cupom_tipo' => 'nullable|in:sistema,empresa',
            'cupom_id' => 'nullable|integer',
            'cupom_valor' => 'nullable|numeric|min:0',
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.produto_id' => 'nullable|exists:produtos,id',
            'itens.*.kit_id' => 'nullable|exists:kits,id',
            'itens.*.quantidade' => 'required|numeric|min:0.1',
            'itens.*.preco_unitario' => 'required|numeric|min:0',
            'itens.*.subtotal' => 'required|numeric|min:0',
            'itens.*.observacoes' => 'nullable|string',
            'endereco.endereco_id' => 'required|exists:usuario_enderecos,id',
            'endereco.observacoes' => 'nullable|string',
        ]);

        foreach ($request->itens as $item) {
            $hasProduto = !empty($item['produto_id']);
            $hasKit = !empty($item['kit_id']);
            if (!$hasProduto && !$hasKit) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cada item deve ter produto_id ou kit_id.',
                    'message' => 'Cada item deve ter produto_id ou kit_id.',
                ], 422);
            }
            if ($hasProduto && $hasKit) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cada item deve ter apenas produto_id ou kit_id.',
                    'message' => 'Cada item deve ter apenas produto_id ou kit_id.',
                ], 422);
            }
        }

        $usuario = Auth::user();

        // Verificar se o endereço pertence ao usuário
        $endereco = UsuarioEnderecos::where('id', $request->endereco['endereco_id'])
            ->where('usuario_id', $usuario->id)
            ->first();

        if (!$endereco) {
            return response()->json([
                'success' => false,
                'error' => 'Endereço inválido',
                'message' => 'O endereço selecionado não pertence ao seu usuário.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Criar pedido
            $pedido = Pedido::create([
                'usuario_id' => $usuario->id,
                'empresa_id' => $request->empresa_id,
                'status_pedido_id' => StatusPedidos::where('slug', 'pendente')->first()->id,
                'pagamento_id' => $request->pagamento_id,
                'subtotal' => $request->subtotal,
                'desconto' => $request->desconto ?? 0,
                'frete' => $request->frete ?? 0,
                'total' => $request->total,
                'observacoes' => $request->observacoes,
                'cupom_tipo' => $request->cupom_tipo,
                'cupom_id' => $request->cupom_id,
                'cupom_valor' => $request->cupom_valor ?? 0,
                'ativo' => true,
            ]);

            // Registrar uso do cupom se existir
            if ($request->has('cupom_id') && $request->cupom_id) {
                if ($request->cupom_tipo === 'sistema') {
                    SistemaCupomUsado::create([
                        'sistema_cupom_id' => $request->cupom_id,
                        'usuario_id' => $usuario->id,
                        'pedido_id' => $pedido->id,
                    ]);
                } elseif ($request->cupom_tipo === 'empresa') {
                    EmpresaCupomUsado::create([
                        'empresa_cupom_id' => $request->cupom_id,
                        'usuario_id' => $usuario->id,
                        'pedido_id' => $pedido->id,
                    ]);
                }
            }

            // Criar itens do pedido (produto direto ou expansão de kit)
            if ($request->has('itens') && is_array($request->itens)) {
                foreach ($request->itens as $item) {
                    if (!empty($item['kit_id'])) {
                        $kit = Kit::with(['itens.produto'])->where('id', $item['kit_id'])->where('empresa_id', $request->empresa_id)->first();
                        if (!$kit) {
                            throw new \InvalidArgumentException('Kit inválido ou não pertence à empresa.');
                        }
                        $qtdKit = (float) $item['quantidade'];
                        foreach ($kit->itens as $kitItem) {
                            $produto = $kitItem->produto;
                            $qtdItem = $kitItem->quantidade * $qtdKit;
                            $precoUnit = $produto ? CalculosService::getPrecoEfetivo($produto) : 0;
                            PedidoItems::create([
                                'pedido_id' => $pedido->id,
                                'produto_id' => $kitItem->produto_id,
                                'kit_id' => $kit->id,
                                'quantidade' => $qtdItem,
                                'preco_unitario' => $precoUnit,
                                'preco_total' => $precoUnit * $qtdItem,
                                'observacoes' => $item['observacoes'] ?? null,
                            ]);
                        }
                    } else {
                        $produto = Produto::find($item['produto_id']);
                        $precoUnit = $produto ? CalculosService::getPrecoEfetivo($produto) : (float) ($item['preco_unitario'] ?? 0);
                        $qtd = (float) ($item['quantidade'] ?? 0);
                        PedidoItems::create([
                            'pedido_id' => $pedido->id,
                            'produto_id' => $item['produto_id'],
                            'quantidade' => $qtd,
                            'preco_unitario' => $precoUnit,
                            'preco_total' => $precoUnit * $qtd,
                            'observacoes' => $item['observacoes'] ?? null,
                        ]);
                    }
                }
            }

            // Criar endereço do pedido
            if ($request->has('endereco')) {
                PedidoEndereco::create([
                    'pedido_id' => $pedido->id,
                    'endereco_id' => $request->endereco['endereco_id'],
                    'observacoes' => $request->endereco['observacoes'] ?? null,
                ]);
            }

            // Criar histórico inicial
            PedidoHistoricoStatus::create([
                'pedido_id' => $pedido->id,
                'status_pedido_id' => $pedido->status_pedido_id,
                'observacoes' => 'Pedido criado via site',
            ]);

            DB::commit();

            $codigo = '#' . str_pad((string) $pedido->id, 6, '0', STR_PAD_LEFT);
            app(PushNotificationService::class)->sendNewOrderToEmpresa($pedido->empresa_id, $codigo);

            return response()->json([
                'success' => true,
                'message' => 'Pedido criado com sucesso',
                'pedido' => new PedidoResource($pedido),
                'whatsapp_numero' => $pedido->empresa->configuracoes ? $pedido->empresa->configuracoes->whatsapp_pedidos_formatado : null
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar pedido',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar perfil do usuário (Privado)
     */
    public function atualizarPerfil(Request $request)
    {
        $usuario = Auth::user();

        $request->validate([
            'nome' => 'required|string|min:3|max:255',
            'telefone' => [
                'nullable',
                'string',
                'max:20',
                function ($attribute, $value, $fail) use ($usuario) {
                    if (empty($value)) {
                        return;
                    }

                    // Verificar se já existe outro CLIENTE (tipo_cadastro = 1) com este telefone
                    $exists = \App\Models\User::where('telefone', $value)
                        ->where('tipo_cadastro', 1)
                        ->where('id', '!=', $usuario->id)
                        ->exists();

                    if ($exists) {
                        $fail('Este telefone já está sendo usado por outro cliente.');
                    }
                },
            ],
        ]);

        $usuario->update([
            'nome' => $request->nome,
            'telefone' => $request->telefone,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perfil atualizado com sucesso',
            'usuario' => new UsuarioResource($usuario)
        ]);
    }

    /**
     * Alterar senha do usuário (Privado)
     */
    public function alterarSenha(Request $request)
    {
        $usuario = Auth::user();

        $request->validate([
            'senha_atual' => 'required|string',
            'senha_nova' => 'required|string|min:8|different:senha_atual',
            'senha_confirmacao' => 'required|string|same:senha_nova',
        ], [
            'senha_atual.required' => 'A senha atual é obrigatória',
            'senha_nova.required' => 'A nova senha é obrigatória',
            'senha_nova.min' => 'A nova senha deve ter no mínimo 8 caracteres',
            'senha_nova.different' => 'A nova senha deve ser diferente da senha atual',
            'senha_confirmacao.required' => 'A confirmação da senha é obrigatória',
            'senha_confirmacao.same' => 'As senhas não conferem',
        ]);

        // Verificar se a senha atual está correta
        if (!Hash::check($request->senha_atual, $usuario->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Senha atual incorreta'
            ], 401);
        }

        // Atualizar senha
        $usuario->update([
            'password' => Hash::make($request->senha_nova)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Senha alterada com sucesso'
        ]);
    }

    /**
     * Excluir conta do usuário logado - Fake Delete (Privado)
     */
    public function excluirConta()
    {
        $usuario = Auth::user();

        // Apenas clientes (tipo_cadastro = 1) podem usar este endpoint
        if ($usuario->tipo_cadastro !== 1) {
            return response()->json([
                'success' => false,
                'error' => 'Acesso negado',
                'message' => 'Esta rota é exclusiva para clientes.'
            ], 403);
        }

        // Fake delete: marca como inativo em vez de deletar
        $usuario->update(['ativo' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Conta desativada com sucesso. Você pode reativá-la futuramente fazendo login.'
        ]);
    }

    /**
     * Listar produtos de empresas que atendem o bairro (Público)
     */
    public function getProdutos(Request $request)
    {
        // Buscar produtos de empresas ativas e com cadastro completo
        $query = Produto::where('ativo', true)
            ->whereHas('empresa', function($q) {
                $q->where('ativo', true)
                  ->where('cadastro_completo', true);
            })
            ->with(['empresa', 'categoria', 'unidadeMedida']);

        // Filtro por bairro (obrigatório - apenas empresas que atendem o bairro)
        if ($request->has('bairro') && !empty(trim($request->bairro))) {
            $query->whereHas('empresa.bairrosEntregas', function($q) use ($request) {
                $q->whereHas('bairro', function($qb) use ($request) {
                    $qb->where('nome', $request->bairro)
                       ->where('ativo', true);
                })
                ->where('ativo', true);
            });
        }

        // Filtro por busca no nome do produto
        if ($request->has('q') && !empty(trim($request->q))) {
            $termoBusca = trim($request->q);
            $query->where(function($q) use ($termoBusca) {
                $q->where('nome', 'like', '%' . $termoBusca . '%')
                  ->orWhere('descricao', 'like', '%' . $termoBusca . '%');
            });
        }

        // Filtro por categoria
        if ($request->has('categoria_id') && !empty($request->categoria_id)) {
            $query->where('categoria_id', $request->categoria_id);
        }

        // Filtro por promoção - produtos com preco_promocional > 0 e data válida
        if ($request->has('promocao_only') && $request->promocao_only == 'true') {
            $query->where('preco_promocional', '>', 0)
                  ->where(function($q) {
                      $q->whereNull('promocao_ate')
                        ->orWhere('promocao_ate', '>=', now());
                  });
        }

        // Ordenação
        if ($request->has('ordenacao') && !empty($request->ordenacao)) {
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
                    // Prioridade: produtos com preco_promocional > 0 primeiro
                    $query->orderByRaw('(CASE WHEN preco_promocional > 0 AND (promocao_ate IS NULL OR promocao_ate >= NOW()) THEN 0 ELSE 1 END)')
                          ->orderBy('nome', 'asc');
                    break;
                default:
                    $query->orderBy('nome', 'asc');
            }
        } else {
            // Ordenação padrão: promoções primeiro (produtos com preco_promocional > 0 e data válida)
            $query->orderByRaw('(CASE WHEN preco_promocional > 0 AND (promocao_ate IS NULL OR promocao_ate >= NOW()) THEN 0 ELSE 1 END)')
                  ->orderBy('nome', 'asc');
        }

        $produtos = $query->paginate(20);
        $categorias = Categorias::where('ativo', true)->get(['id', 'nome']);

        // Formatar resposta
        $produtosFormatados = $produtos->map(function($produto) {
            // Calcular se está em promoção
            $estaEmPromocao = $produto->preco_promocional > 0 &&
                (is_null($produto->promocao_ate) || $produto->promocao_ate >= now());

            return [
                'id' => $produto->id,
                'nome' => $produto->nome,
                'descricao' => $produto->descricao,
                'preco' => $produto->preco,
                'preco_promocional' => $produto->preco_promocional,
                'preco_atual' => CalculosService::getPrecoEfetivo($produto),
                'esta_em_promocao' => $estaEmPromocao,
                'url_imagem' => $produto->url_imagem,
                'quantidade_estoque' => $produto->quantidade_estoque,
                'vende_granel' => $produto->vende_granel,
                'categoria' => $produto->categoria ? [
                    'id' => $produto->categoria->id,
                    'nome' => $produto->categoria->nome
                ] : null,
                'unidade_medida' => $produto->unidadeMedida ? [
                    'id' => $produto->unidadeMedida->id,
                    'nome' => $produto->unidadeMedida->nome,
                    'sigla' => $produto->unidadeMedida->sigla
                ] : null,
                'empresa' => [
                    'id' => $produto->empresa->id,
                    'nome_fantasia' => $produto->empresa->nome_fantasia,
                    'slug' => $produto->empresa->slug,
                    'empresa_aberta' => $produto->empresa->isAberta(),
                    'path_logo' => $produto->empresa->path_logo,
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'produtos' => $produtosFormatados,
            'categorias' => $categorias,
            'paginacao' => [
                'total' => $produtos->total(),
                'per_page' => $produtos->perPage(),
                'current_page' => $produtos->currentPage(),
                'last_page' => $produtos->lastPage(),
                'has_more_pages' => $produtos->hasMorePages(),
            ]
        ]);
    }
}