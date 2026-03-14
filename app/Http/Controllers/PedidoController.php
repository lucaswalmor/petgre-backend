<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\Pedido\PedidoStoreRequest;
use App\Http\Requests\Pedido\PedidoUpdateRequest;
use App\Http\Resources\Pedido\PedidoResource;
use App\Http\Resources\Api\ApiResourceCollection;
use App\Models\Pedido;
use App\Models\PedidoItems;
use App\Models\PedidoEndereco;
use App\Models\PedidoHistoricoStatus;
use App\Models\EmpresaCupom;
use App\Models\EmpresaCupomUsado;
use App\Models\SistemaCupom;
use App\Models\SistemaCupomUsado;
use App\Models\UsuarioCupom;
use App\Models\StatusPedidos;
use App\Models\EmpresaResgateCupom;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\VerificaEmpresa;
use App\Models\EmpresaAvaliacao;
use App\Models\Kit;
use App\Models\Produto;
use App\Services\PushNotificationService;
use App\Services\FaturamentoService;
use App\Services\EmailService;
use App\Services\CalculosService;
use App\Services\EvolutionMensagensService;
use App\Services\NotificacaoEstoqueService;
use App\Services\NotificacaoPedidoService;
use Carbon\Carbon;

class PedidoController extends Controller
{
    private EvolutionMensagensService $evolutionMensagens;

    public function __construct(EvolutionMensagensService $evolutionMensagens)
    {
        $this->evolutionMensagens = $evolutionMensagens;
    }

    /**
     * Retorna estatísticas consolidadas para os cards da tela de pedidos.
     */
    public function estatisticas(Request $request)
    {
        $empresaId      = $request->empresa_id;
        $hoje           = Carbon::today();
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

        return response()->json([
            'success' => true,
            'estatisticas' => [
                'pedidos_hoje'      => $pedidosHoje,
                'faturamento_mes'   => round((float) $faturamentoMes, 2),
                'pedidos_pendentes' => $pedidosPendentes,
                'avaliacao_media'   => $avaliacaoMedia ? round((float) $avaliacaoMedia, 1) : null,
            ],
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $empresaId = $request->empresa_id;
        $query = Pedido::with([
            'usuario',
            'empresa',
            'statusPedido',
            'formaPagamento',
            'endereco.endereco',
            'itens.produto',
            'itens.kit'
        ])->where('empresa_id', $empresaId);

        // Filtros opcionais

        if ($request->has('status_id') && $request->status_id) {
            $query->where('status_pedido_id', $request->status_id);
        }

        if ($request->has('usuario_id') && $request->usuario_id) {
            $query->where('usuario_id', $request->usuario_id);
        }

        // Filtros de data
        if ($request->has('data_inicio') && $request->data_inicio) {
            $query->where('created_at', '>=', $request->data_inicio . ' 00:00:00');
        }

        if ($request->has('data_fim') && $request->data_fim) {
            $query->where('created_at', '<=', $request->data_fim . ' 23:59:59');
        }

        // Filtro por tipo de pedido (produto, servico, misto)
        if ($request->has('tipo') && $request->tipo) {
            $query->where('tipo_pedido', $request->tipo);
        }

        // Ordenação
        $orderBy = $request->get('order_by', 'created_at');
        $orderDirection = $request->get('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        // Paginação
        $perPage = $request->get('per_page', 15);
        $pedidos = $query->paginate($perPage);

        return new ApiResourceCollection($pedidos);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PedidoStoreRequest $request)
    {
        DB::beginTransaction();
        try {
            $usuario = Auth::user();

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
                'foi_entrega' => $request->foi_entrega ?? false,
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

            // Determinar tipo do pedido (produto, servico, misto) com base nos itens
            $tipoPedido = $this->determinarTipoPedido($request->itens);
            $pedido->tipo_pedido = $tipoPedido;

            // Se for serviço, extrair data de agendamento das observações
            if ($tipoPedido === 'servico' || $tipoPedido === 'misto') {
                $dataAgendamento = $this->extrairDataAgendamento($request->observacoes);
                if ($dataAgendamento) {
                    $pedido->data_agendamento = $dataAgendamento;
                }
            }

            $pedido->save();

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

            // Criar endereço do pedido (apenas se foi entrega e endereço foi enviado)
            if ($request->boolean('foi_entrega') && $request->has('endereco') && $request->endereco['endereco_id']) {
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
                'observacoes' => 'Pedido criado',
            ]);

            // Baixa de estoque dos produtos do pedido
            $pedido->load('itens.produto');
            foreach ($pedido->itens as $item) {
                $produto = $item->produto;
                if (!$produto || $produto->tipo === 'servico') {
                    continue;
                }
                $qty = (float) $item->quantidade;
                $qtyEstoque = $produto->vende_granel ? $qty / 1000 : $qty;
                if ($produto->estoque !== null && $qtyEstoque > 0) {
                    $produto->reduzirEstoque($qtyEstoque);
                }
            }

            DB::commit();

            $codigo = '#' . str_pad((string) $pedido->id, 6, '0', STR_PAD_LEFT);
            app(PushNotificationService::class)->sendNewOrderToEmpresa($pedido->empresa_id, $codigo);

            // Notificar lojista quando produto com "Ativar Estoque Mínimo" atingir estoque abaixo do mínimo
            $pedido->load('itens.produto.empresa');
            $produtosNotificados = [];
            foreach ($pedido->itens as $item) {
                $produto = $item->produto;
                if (!$produto || $produto->tipo === 'servico') {
                    continue;
                }
                if (in_array($produto->id, $produtosNotificados)) {
                    continue;
                }
                if (!$produto->ativar_estoque_minimo || $produto->estoque_minimo === null) {
                    continue;
                }
                if ((float) $produto->estoque >= (float) $produto->estoque_minimo) {
                    continue;
                }
                $empresa = $produto->empresa;
                if (!$empresa) {
                    continue;
                }
                // Enviar notificações de estoque mínimo (email e WhatsApp)
                app(NotificacaoEstoqueService::class)->notificarEstoqueMinimo($produto, $empresa);
                $produtosNotificados[] = $produto->id;
            }

            // Carregar relacionamentos
            $pedido->load([
                'usuario',
                'empresa',
                'statusPedido',
                'formaPagamento',
                'endereco.endereco',
                'itens.produto',
                'itens.kit'
            ]);

            // Buscar número do WhatsApp da empresa (formatado, apenas números)
            $whatsappNumero = null;
            if ($pedido->empresa->configuracoes && $pedido->empresa->configuracoes->whatsapp_pedidos) {
                $whatsappNumero = preg_replace('/[^\d]/', '', $pedido->empresa->configuracoes->whatsapp_pedidos);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pedido criado com sucesso',
                'pedido' => new PedidoResource($pedido),
                'whatsapp_numero' => $whatsappNumero
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
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $pedido = Pedido::with([
            'usuario',
            'empresa',
            'statusPedido',
            'formaPagamento',
            'endereco.endereco',
            'itens.produto',
            'itens.kit',
            'historicoStatus.statusPedido',
            'avaliacao'
        ])->findOrFail($id);

        $usuario = Auth::user();

        // Verificar se usuário tem acesso ao pedido
        if ($pedido->usuario_id !== $usuario->id && !VerificaEmpresa::verificaEmpresaPertenceAoUsuario($pedido->empresa_id)) {
            return response()->json([
                'success' => false,
                'error' => 'Acesso negado',
                'message' => 'Você não tem permissão para visualizar este pedido.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'pedido' => new PedidoResource($pedido)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PedidoUpdateRequest $request, string $id)
    {
        $pedido = Pedido::findOrFail($id);

        // Verificar se usuário tem acesso ao pedido (apenas empresa pode alterar status)
        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($pedido->empresa_id)) {
            return response()->json([
                'success' => false,
                'error' => 'Acesso negado',
                'message' => 'Você não tem permissão para alterar este pedido.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $statusAnteriorId = $pedido->status_pedido_id;
            $idCancelado = StatusPedidos::where('slug', 'cancelado')->first()->id;
            $updateData = [];

            // Apenas empresa pode alterar status
            if ($request->has('status_pedido_id')) {
                $updateData['status_pedido_id'] = $request->status_pedido_id;

                // Criar histórico de status
                PedidoHistoricoStatus::create([
                    'pedido_id' => $pedido->id,
                    'status_pedido_id' => $request->status_pedido_id,
                    'observacoes' => $request->status_observacoes ?? null,
                ]);
            }

            if ($request->has('observacoes')) {
                $updateData['observacoes'] = $request->observacoes;
            }

            $pedido->update($updateData);

            // Lógica de resgate de cupom do sistema quando o pedido for entregue
            if ($request->has('status_pedido_id')) {
                $statusSlug = StatusPedidos::find($request->status_pedido_id)->slug;

                if ($statusSlug === 'entregue' && $pedido->cupom_tipo === 'sistema') {
                    // Verificar se já existe um resgate para este pedido
                    $resgateExistente = EmpresaResgateCupom::where('pedido_id', $pedido->id)->exists();

                    if (!$resgateExistente) {
                        // Buscar o registro de uso do cupom do sistema
                        $cupomUsado = SistemaCupomUsado::where('pedido_id', $pedido->id)->first();

                        EmpresaResgateCupom::create([
                            'empresa_id' => $pedido->empresa_id,
                            'sistema_cupom_usado_id' => $cupomUsado ? $cupomUsado->id : null,
                            'pedido_id' => $pedido->id,
                            'empresa_usuario_id' => Auth::id(),
                            'valor' => $pedido->cupom_valor,
                            'status' => 'pendente',
                            'data_solicitacao' => now(),
                        ]);
                    }
                } elseif ($statusSlug === 'cancelado') {
                    // Reposição de estoque ao cancelar (apenas se não estava já cancelado)
                    if ($statusAnteriorId != $idCancelado) {
                        $pedido->load('itens.produto');
                        foreach ($pedido->itens as $item) {
                            $produto = $item->produto;
                            if (!$produto || $produto->tipo === 'servico') {
                                continue;
                            }
                            $qty = (float) $item->quantidade;
                            $qtyEstoque = $produto->vende_granel ? $qty / 1000 : $qty;
                            if ($produto->estoque !== null && $qtyEstoque > 0) {
                                $produto->adicionarEstoque($qtyEstoque);
                            }
                        }
                    }

                    // Cancelar o resgate da empresa se estiver pendente ou aprovado
                    $resgate = EmpresaResgateCupom::where('pedido_id', $pedido->id)
                        ->whereIn('status', ['pendente', 'aprovado'])
                        ->first();

                    if ($resgate) {
                        $resgate->update(['status' => 'cancelado']);
                    }

                    // Devolver o cupom ao cliente removendo o registro de uso
                    if ($pedido->cupom_tipo === 'sistema') {
                        SistemaCupomUsado::where('pedido_id', $pedido->id)->delete();
                    } elseif ($pedido->cupom_tipo === 'empresa') {
                        EmpresaCupomUsado::where('pedido_id', $pedido->id)->delete();
                    }
                }
            }

            DB::commit();

            // Enviar notificação WhatsApp ao cliente se o status mudou
            if ($request->has('status_pedido_id') && $statusAnteriorId != $request->status_pedido_id) {
                app(NotificacaoPedidoService::class)->notificarClienteStatusAlterado($pedido, $request->status_pedido_id, $request->status_observacoes ?? null);
            }

            // Recarregar relacionamentos
            $pedido->load([
                'usuario',
                'empresa',
                'statusPedido',
                'formaPagamento',
                'endereco.endereco',
                'itens.produto',
                'itens.kit',
                'historicoStatus.statusPedido'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pedido atualizado com sucesso',
                'pedido' => new PedidoResource($pedido)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar pedido',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $pedido = Pedido::findOrFail($id);

        // Verificar se usuário tem acesso ao pedido
        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($pedido->empresa_id)) {
            return response()->json([
                'success' => false,
                'error' => 'Acesso negado',
                'message' => 'Você não tem permissão para excluir este pedido.'
            ], 403);
        }

        // Verificar se pedido pode ser excluído (apenas pendentes)
        if ($pedido->statusPedido->slug !== 'pendente') {
            return response()->json([
                'success' => false,
                'error' => 'Não é possível excluir este pedido',
                'message' => 'Apenas pedidos pendentes podem ser excluídos.'
            ], 400);
        }

        $pedido->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pedido excluído com sucesso'
        ]);
    }

    /**
     * Determinar o tipo do pedido (produto, servico, misto) baseado nos itens
     */
    private function determinarTipoPedido(array $itens): string
    {
        $temProduto = false;
        $temServico = false;

        foreach ($itens as $item) {
            if (!empty($item['kit_id'])) {
                // Kits são considerados produtos
                $temProduto = true;
            } else {
                $produto = Produto::find($item['produto_id'] ?? null);
                if ($produto) {
                    if ($produto->tipo === 'servico') {
                        $temServico = true;
                    } else {
                        $temProduto = true;
                    }
                }
            }
        }

        if ($temProduto && $temServico) {
            return 'misto';
        } elseif ($temServico) {
            return 'servico';
        } else {
            return 'produto';
        }
    }

    /**
     * Extrair data de agendamento das observações do pedido
     * Formato esperado: "Data Preferencial: 15/03/2026 às 11:00" ou similar
     */
    private function extrairDataAgendamento(?string $observacoes): ?string
    {
        if (!$observacoes) {
            return null;
        }

        // Procurar por padrões de data nas observações
        // Padrões: "Data Preferencial: DD/MM/YYYY", "Data: DD/MM/YYYY", "Agendado para: DD/MM/YYYY"
        $padroes = [
            '/Data Preferencial:\s*(\d{2}\/\d{2}\/\d{4})/i',
            '/Data:\s*(\d{2}\/\d{2}\/\d{4})/i',
            '/Agendado para:\s*(\d{2}\/\d{2}\/\d{4})/i',
        ];

        foreach ($padroes as $padrao) {
            if (preg_match($padrao, $observacoes, $matches)) {
                $dataBr = $matches[1];
                // Converter de DD/MM/YYYY para YYYY-MM-DD
                $partes = explode('/', $dataBr);
                if (count($partes) === 3) {
                    return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
                }
            }
        }

        return null;
    }

    /**
     * Validar cupom antes de fazer pedido
     */
    public function validarCupom(Request $request)
    {
        $request->validate([
            'cupom_codigo' => 'required|string',
            'empresa_id' => 'required|exists:empresas,id',
            'valor_compra' => 'required|numeric|min:0',
        ]);

        $usuario = Auth::user();
        $codigo = $request->cupom_codigo;
        $empresaId = $request->empresa_id;
        $valorCompra = $request->valor_compra;

        // Tentar encontrar cupom da empresa primeiro
        $cupomEmpresa = EmpresaCupom::where('codigo', $codigo)
            ->where('empresa_id', $empresaId)
            ->first();

        if ($cupomEmpresa) {
            // Verificar se cupom da empresa é válido
            if (!$cupomEmpresa->isValido()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cupom inválido',
                    'message' => 'Este cupom não está mais válido.'
                ], 400);
            }

            // Verificar se usuário já usou este cupom
            if ($cupomEmpresa->usuarioJaUsou($usuario->id)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cupom já utilizado',
                    'message' => 'Você já utilizou este cupom anteriormente.'
                ], 400);
            }

            // Verificar valor mínimo
            if ($cupomEmpresa->valor_minimo && $valorCompra < $cupomEmpresa->valor_minimo) {
                return response()->json([
                    'success' => false,
                    'error' => 'Valor insuficiente',
                    'message' => "O valor mínimo para usar este cupom é R$ " . number_format($cupomEmpresa->valor_minimo, 2, ',', '.')
                ], 400);
            }

            $valorDesconto = $cupomEmpresa->calcularDesconto($valorCompra);

            return response()->json([
                'success' => true,
                'cupom' => [
                    'id' => $cupomEmpresa->id,
                    'codigo' => $cupomEmpresa->codigo,
                    'tipo' => $cupomEmpresa->tipo,
                    'valor' => $cupomEmpresa->valor,
                    'valor_minimo' => $cupomEmpresa->valor_minimo,
                    'tipo_cupom' => 'empresa',
                    'empresa_id' => $cupomEmpresa->empresa_id,
                ],
                'desconto' => [
                    'valor' => $valorDesconto,
                    'valor_formatado' => 'R$ ' . number_format($valorDesconto, 2, ',', '.'),
                ],
                'total_com_desconto' => $valorCompra - $valorDesconto,
                'total_formatado' => 'R$ ' . number_format($valorCompra - $valorDesconto, 2, ',', '.'),
            ]);
        }

        // Se não encontrou cupom da empresa, tentar cupom do sistema
        $cupomSistema = SistemaCupom::where('codigo', $codigo)->first();

        if ($cupomSistema) {
            // Verificar se cupom do sistema é válido
            if (!$cupomSistema->isValido()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cupom inválido',
                    'message' => 'Este cupom não está mais válido.'
                ], 400);
            }

            // Verificar se usuário já usou este cupom
            if ($cupomSistema->usuarioJaUsou($usuario->id)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cupom já utilizado',
                    'message' => 'Você já utilizou este cupom anteriormente.'
                ], 400);
            }

            // Verificar se usuário tem este cupom atribuído
            if (!$cupomSistema->usuarioTemCupom($usuario->id)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cupom não disponível',
                    'message' => 'Este cupom não está disponível para você.'
                ], 400);
            }

            $valorDesconto = $cupomSistema->calcularDesconto($valorCompra);

            return response()->json([
                'success' => true,
                'cupom' => [
                    'id' => $cupomSistema->id,
                    'codigo' => $cupomSistema->codigo,
                    'tipo' => $cupomSistema->tipo,
                    'valor' => $cupomSistema->valor,
                    'tipo_cupom' => 'sistema',
                ],
                'desconto' => [
                    'valor' => $valorDesconto,
                    'valor_formatado' => 'R$ ' . number_format($valorDesconto, 2, ',', '.'),
                ],
                'total_com_desconto' => $valorCompra - $valorDesconto,
                'total_formatado' => 'R$ ' . number_format($valorCompra - $valorDesconto, 2, ',', '.'),
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'Cupom não encontrado',
            'message' => 'O cupom informado não existe ou não é válido para esta empresa.'
        ], 404);
    }

}