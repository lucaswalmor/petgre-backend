<?php

namespace App\Services\SiteCliente;

use App\Http\Resources\Pedido\PedidoResource;
use App\Models\Kit;
use App\Models\Pedido;
use App\Models\PedidoEndereco;
use App\Models\PedidoHistoricoStatus;
use App\Models\PedidoItems;
use App\Models\Produto;
use App\Models\StatusPedidos;
use App\Models\UsuarioEnderecos;
use App\Models\User;
use App\Models\EmpresaCupomUsado;
use App\Models\SistemaCupomUsado;
use App\Services\CalculosService;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiteClientePedidoClienteService
{
    public function __construct(
        private PushNotificationService $pushNotificationService
    ) {}

    public function listarPedidos(User $usuario): array
    {
        $pedidos = Pedido::where('usuario_id', $usuario->id)
            ->with(['empresa', 'statusPedido', 'itens.produto', 'formaPagamento', 'avaliacao'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return [
            'success'   => true,
            'pedidos'   => PedidoResource::collection($pedidos),
            'paginacao' => [
                'total'            => $pedidos->total(),
                'per_page'         => $pedidos->perPage(),
                'current_page'     => $pedidos->currentPage(),
                'last_page'        => $pedidos->lastPage(),
                'has_more_pages'   => $pedidos->hasMorePages(),
            ],
        ];
    }

    public function obterPedido(User $usuario, int|string $id): array
    {
        $pedido = Pedido::where('usuario_id', $usuario->id)
            ->with([
                'empresa',
                'statusPedido',
                'itens.produto.unidadeMedida',
                'endereco.endereco',
                'formaPagamento',
                'historicoStatus.statusPedido',
                'avaliacao',
            ])
            ->findOrFail($id);

        return [
            'success' => true,
            'pedido'  => new PedidoResource($pedido),
        ];
    }

    /**
     * @return array{ok: true, http: int, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function criarPedido(Request $request, User $usuario): array
    {
        $request->validate([
            'empresa_id'              => 'required|exists:empresas,id',
            'pagamento_id'            => 'required|exists:formas_pagamento,id',
            'subtotal'                => 'required|numeric|min:0',
            'desconto'                => 'nullable|numeric|min:0',
            'frete'                   => 'nullable|numeric|min:0',
            'total'                   => 'required|numeric|min:0',
            'observacoes'             => 'nullable|string',
            'cupom_tipo'              => 'nullable|in:sistema,empresa',
            'cupom_id'                => 'nullable|integer',
            'cupom_valor'             => 'nullable|numeric|min:0',
            'itens'                   => ['required', 'array', 'min:1'],
            'itens.*.produto_id'      => 'nullable|exists:produtos,id',
            'itens.*.kit_id'          => 'nullable|exists:kits,id',
            'itens.*.quantidade'      => 'required|numeric|min:0.1',
            'itens.*.preco_unitario'  => 'required|numeric|min:0',
            'itens.*.subtotal'        => 'required|numeric|min:0',
            'itens.*.observacoes'     => 'nullable|string',
            'endereco.endereco_id'    => 'required|exists:usuario_enderecos,id',
            'endereco.observacoes'    => 'nullable|string',
        ]);

        foreach ($request->itens as $item) {
            $hasProduto = ! empty($item['produto_id']);
            $hasKit = ! empty($item['kit_id']);
            if (! $hasProduto && ! $hasKit) {
                return [
                    'ok'   => false,
                    'http' => 422,
                    'body' => [
                        'success' => false,
                        'error'   => 'Cada item deve ter produto_id ou kit_id.',
                        'message' => 'Cada item deve ter produto_id ou kit_id.',
                    ],
                ];
            }
            if ($hasProduto && $hasKit) {
                return [
                    'ok'   => false,
                    'http' => 422,
                    'body' => [
                        'success' => false,
                        'error'   => 'Cada item deve ter apenas produto_id ou kit_id.',
                        'message' => 'Cada item deve ter apenas produto_id ou kit_id.',
                    ],
                ];
            }
        }

        $endereco = UsuarioEnderecos::where('id', $request->endereco['endereco_id'])
            ->where('usuario_id', $usuario->id)
            ->first();

        if (! $endereco) {
            return [
                'ok'   => false,
                'http' => 400,
                'body' => [
                    'success' => false,
                    'error'   => 'Endereço inválido',
                    'message' => 'O endereço selecionado não pertence ao seu usuário.',
                ],
            ];
        }

        DB::beginTransaction();
        try {
            $pedido = Pedido::create([
                'usuario_id'         => $usuario->id,
                'empresa_id'         => $request->empresa_id,
                'status_pedido_id'   => StatusPedidos::where('slug', 'pendente')->first()->id,
                'pagamento_id'       => $request->pagamento_id,
                'subtotal'           => $request->subtotal,
                'desconto'           => $request->desconto ?? 0,
                'frete'              => $request->frete ?? 0,
                'total'              => $request->total,
                'observacoes'        => $request->observacoes,
                'cupom_tipo'         => $request->cupom_tipo,
                'cupom_id'           => $request->cupom_id,
                'cupom_valor'        => $request->cupom_valor ?? 0,
                'ativo'              => true,
            ]);

            if ($request->has('cupom_id') && $request->cupom_id) {
                if ($request->cupom_tipo === 'sistema') {
                    SistemaCupomUsado::create([
                        'sistema_cupom_id' => $request->cupom_id,
                        'usuario_id'       => $usuario->id,
                        'pedido_id'        => $pedido->id,
                    ]);
                } elseif ($request->cupom_tipo === 'empresa') {
                    EmpresaCupomUsado::create([
                        'empresa_cupom_id' => $request->cupom_id,
                        'usuario_id'       => $usuario->id,
                        'pedido_id'        => $pedido->id,
                    ]);
                }
            }

            if ($request->has('itens') && is_array($request->itens)) {
                foreach ($request->itens as $item) {
                    if (! empty($item['kit_id'])) {
                        $kit = Kit::with(['itens.produto'])->where('id', $item['kit_id'])->where('empresa_id', $request->empresa_id)->first();
                        if (! $kit) {
                            throw new \InvalidArgumentException('Kit inválido ou não pertence à empresa.');
                        }
                        $qtdKit = (float) $item['quantidade'];
                        foreach ($kit->itens as $kitItem) {
                            $produto = $kitItem->produto;
                            $qtdItem = $kitItem->quantidade * $qtdKit;
                            $precoUnit = $produto ? CalculosService::getPrecoEfetivo($produto) : 0;
                            PedidoItems::create([
                                'pedido_id'       => $pedido->id,
                                'produto_id'      => $kitItem->produto_id,
                                'kit_id'          => $kit->id,
                                'quantidade'      => $qtdItem,
                                'preco_unitario'  => $precoUnit,
                                'preco_total'     => $precoUnit * $qtdItem,
                                'observacoes'     => $item['observacoes'] ?? null,
                            ]);
                        }
                    } else {
                        $produto = Produto::find($item['produto_id']);
                        $precoUnit = $produto ? CalculosService::getPrecoEfetivo($produto) : (float) ($item['preco_unitario'] ?? 0);
                        $qtd = (float) ($item['quantidade'] ?? 0);
                        PedidoItems::create([
                            'pedido_id'       => $pedido->id,
                            'produto_id'      => $item['produto_id'],
                            'quantidade'      => $qtd,
                            'preco_unitario'  => $precoUnit,
                            'preco_total'     => $precoUnit * $qtd,
                            'observacoes'     => $item['observacoes'] ?? null,
                        ]);
                    }
                }
            }

            if ($request->has('endereco')) {
                PedidoEndereco::create([
                    'pedido_id'   => $pedido->id,
                    'endereco_id' => $request->endereco['endereco_id'],
                    'observacoes' => $request->endereco['observacoes'] ?? null,
                ]);
            }

            PedidoHistoricoStatus::create([
                'pedido_id'        => $pedido->id,
                'status_pedido_id' => $pedido->status_pedido_id,
                'observacoes'      => 'Pedido criado via site',
            ]);

            DB::commit();

            $pedido->load(['empresa.configuracoes']);

            $codigo = '#' . str_pad((string) $pedido->id, 6, '0', STR_PAD_LEFT);
            $this->pushNotificationService->sendNewOrderToEmpresa($pedido->empresa_id, $codigo);

            return [
                'ok'   => true,
                'http' => 201,
                'body' => [
                    'success'         => true,
                    'message'         => 'Pedido criado com sucesso',
                    'pedido'          => new PedidoResource($pedido),
                    'whatsapp_numero' => $pedido->empresa->configuracoes ? $pedido->empresa->configuracoes->whatsapp_pedidos_formatado : null,
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'ok'   => false,
                'http' => 500,
                'body' => [
                    'success' => false,
                    'error'   => 'Erro ao criar pedido',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }
}
