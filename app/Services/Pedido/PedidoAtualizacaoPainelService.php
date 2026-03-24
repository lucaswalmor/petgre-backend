<?php

namespace App\Services\Pedido;

use App\Helpers\VerificaEmpresa;
use App\Http\Requests\Pedido\PedidoUpdateRequest;
use App\Http\Resources\Pedido\PedidoResource;
use App\Models\EmpresaCupomUsado;
use App\Models\EmpresaResgateCupom;
use App\Models\Pedido;
use App\Models\PedidoHistoricoStatus;
use App\Models\SistemaCupomUsado;
use App\Models\StatusPedidos;
use App\Services\NotificacaoPedidoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PedidoAtualizacaoPainelService
{
    public function __construct(
        private NotificacaoPedidoService $notificacaoPedidoService
    ) {}

    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function atualizar(PedidoUpdateRequest $request, string $id): array
    {
        $pedido = Pedido::findOrFail($id);

        if (! VerificaEmpresa::verificaEmpresaPertenceAoUsuario($pedido->empresa_id)) {
            return [
                'ok'   => false,
                'http' => 403,
                'body' => [
                    'success' => false,
                    'error'   => 'Acesso negado',
                    'message' => 'Você não tem permissão para alterar este pedido.',
                ],
            ];
        }

        DB::beginTransaction();
        try {
            $statusAnteriorId = $pedido->status_pedido_id;
            $idCancelado = StatusPedidos::where('slug', 'cancelado')->first()->id;
            $updateData = [];

            if ($request->has('status_pedido_id')) {
                $updateData['status_pedido_id'] = $request->status_pedido_id;

                PedidoHistoricoStatus::create([
                    'pedido_id'        => $pedido->id,
                    'status_pedido_id' => $request->status_pedido_id,
                    'observacoes'      => $request->status_observacoes ?? null,
                ]);
            }

            if ($request->has('observacoes')) {
                $updateData['observacoes'] = $request->observacoes;
            }

            $pedido->update($updateData);

            if ($request->has('status_pedido_id')) {
                $statusSlug = StatusPedidos::find($request->status_pedido_id)->slug;

                if ($statusSlug === 'entregue' && $pedido->cupom_tipo === 'sistema') {
                    $resgateExistente = EmpresaResgateCupom::where('pedido_id', $pedido->id)->exists();

                    if (! $resgateExistente) {
                        $cupomUsado = SistemaCupomUsado::where('pedido_id', $pedido->id)->first();

                        EmpresaResgateCupom::create([
                            'empresa_id'             => $pedido->empresa_id,
                            'sistema_cupom_usado_id' => $cupomUsado ? $cupomUsado->id : null,
                            'pedido_id'              => $pedido->id,
                            'empresa_usuario_id'     => Auth::id(),
                            'valor'                  => $pedido->cupom_valor,
                            'status'                 => 'pendente',
                            'data_solicitacao'       => now(),
                        ]);
                    }
                } elseif ($statusSlug === 'cancelado') {
                    if ($statusAnteriorId != $idCancelado) {
                        $pedido->load('itens.produto');
                        foreach ($pedido->itens as $item) {
                            $produto = $item->produto;
                            if (! $produto || $produto->tipo === 'servico') {
                                continue;
                            }
                            $qty = (float) $item->quantidade;
                            $qtyEstoque = $produto->vende_granel ? $qty / 1000 : $qty;
                            if ($produto->estoque !== null && $qtyEstoque > 0) {
                                $produto->adicionarEstoque($qtyEstoque);
                            }
                        }
                    }

                    $resgate = EmpresaResgateCupom::where('pedido_id', $pedido->id)
                        ->whereIn('status', ['pendente', 'aprovado'])
                        ->first();

                    if ($resgate) {
                        $resgate->update(['status' => 'cancelado']);
                    }

                    if ($pedido->cupom_tipo === 'sistema') {
                        SistemaCupomUsado::where('pedido_id', $pedido->id)->delete();
                    } elseif ($pedido->cupom_tipo === 'empresa') {
                        EmpresaCupomUsado::where('pedido_id', $pedido->id)->delete();
                    }
                }
            }

            DB::commit();

            if ($request->has('status_pedido_id') && $statusAnteriorId != $request->status_pedido_id) {
                $this->notificacaoPedidoService->notificarClienteStatusAlterado($pedido, $request->status_pedido_id, $request->status_observacoes ?? null);
            }

            $pedido->load([
                'usuario',
                'empresa',
                'statusPedido',
                'formaPagamento',
                'endereco.endereco',
                'itens.produto',
                'itens.kit',
                'historicoStatus.statusPedido',
            ]);

            return [
                'ok'   => true,
                'body' => [
                    'success' => true,
                    'message' => 'Pedido atualizado com sucesso',
                    'pedido'  => new PedidoResource($pedido),
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'ok'   => false,
                'http' => 500,
                'body' => [
                    'success' => false,
                    'error'   => 'Erro ao atualizar pedido',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }
}
