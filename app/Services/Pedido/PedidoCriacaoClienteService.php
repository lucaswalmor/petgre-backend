<?php

namespace App\Services\Pedido;

use App\Http\Requests\Pedido\PedidoStoreRequest;
use App\Http\Resources\Pedido\PedidoResource;
use App\Models\EmpresaCupomUsado;
use App\Models\Kit;
use App\Models\Pedido;
use App\Models\PedidoEndereco;
use App\Models\PedidoHistoricoStatus;
use App\Models\PedidoItems;
use App\Models\Produto;
use App\Models\SistemaCupomUsado;
use App\Models\StatusPedidos;
use App\Models\User;
use App\Services\CalculosService;
use App\Services\NotificacaoEstoqueService;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\DB;

class PedidoCriacaoClienteService
{
    public function __construct(
        private PedidoDominioAuxiliarService $dominioAuxiliar,
        private PushNotificationService $pushNotificationService,
        private NotificacaoEstoqueService $notificacaoEstoqueService,
    ) {}

    /**
     * @return array{ok: true, http: int, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function criar(PedidoStoreRequest $request, User $usuario): array
    {
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
                'foi_entrega'        => $request->foi_entrega ?? false,
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

            $tipoPedido = $this->dominioAuxiliar->determinarTipoPedido($request->itens);
            $pedido->tipo_pedido = $tipoPedido;

            if ($tipoPedido === 'servico' || $tipoPedido === 'misto') {
                $dataAgendamento = $this->dominioAuxiliar->extrairDataAgendamento($request->observacoes);
                if ($dataAgendamento) {
                    $pedido->data_agendamento = $dataAgendamento;
                }
            }

            $pedido->save();

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

            if ($request->boolean('foi_entrega') && $request->has('endereco') && $request->endereco['endereco_id']) {
                PedidoEndereco::create([
                    'pedido_id'   => $pedido->id,
                    'endereco_id' => $request->endereco['endereco_id'],
                    'observacoes' => $request->endereco['observacoes'] ?? null,
                ]);
            }

            PedidoHistoricoStatus::create([
                'pedido_id'        => $pedido->id,
                'status_pedido_id' => $pedido->status_pedido_id,
                'observacoes'      => 'Pedido criado',
            ]);

            $pedido->load('itens.produto');
            foreach ($pedido->itens as $item) {
                $produto = $item->produto;
                if (! $produto || $produto->tipo === 'servico') {
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
            $this->pushNotificationService->sendNewOrderToEmpresa($pedido->empresa_id, $codigo);

            $pedido->load('itens.produto.empresa');
            $produtosNotificados = [];
            foreach ($pedido->itens as $item) {
                $produto = $item->produto;
                if (! $produto || $produto->tipo === 'servico') {
                    continue;
                }
                if (in_array($produto->id, $produtosNotificados, true)) {
                    continue;
                }
                if (! $produto->ativar_estoque_minimo || $produto->estoque_minimo === null) {
                    continue;
                }
                if ((float) $produto->estoque >= (float) $produto->estoque_minimo) {
                    continue;
                }
                $empresa = $produto->empresa;
                if (! $empresa) {
                    continue;
                }
                $this->notificacaoEstoqueService->notificarEstoqueMinimo($produto, $empresa);
                $produtosNotificados[] = $produto->id;
            }

            $pedido->load([
                'usuario',
                'empresa',
                'statusPedido',
                'formaPagamento',
                'endereco.endereco',
                'itens.produto',
                'itens.kit',
            ]);

            $whatsappNumero = null;
            if ($pedido->empresa->configuracoes && $pedido->empresa->configuracoes->whatsapp_pedidos) {
                $whatsappNumero = preg_replace('/[^\d]/', '', $pedido->empresa->configuracoes->whatsapp_pedidos);
            }

            return [
                'ok'   => true,
                'http' => 201,
                'body' => [
                    'success'         => true,
                    'message'         => 'Pedido criado com sucesso',
                    'pedido'          => new PedidoResource($pedido),
                    'whatsapp_numero' => $whatsappNumero,
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
