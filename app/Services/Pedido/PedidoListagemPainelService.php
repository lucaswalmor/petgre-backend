<?php

namespace App\Services\Pedido;

use App\Http\Resources\Api\ApiResourceCollection;
use App\Models\Pedido;
use Illuminate\Http\Request;

class PedidoListagemPainelService
{
    private const ORDER_BY_PERMITIDOS = [
        'created_at',
        'updated_at',
        'id',
        'total',
        'subtotal',
        'status_pedido_id',
    ];

    public function listarPaginado(Request $request): ApiResourceCollection
    {
        $empresaId = $request->empresa_id;
        $query = Pedido::with([
            'usuario',
            'empresa',
            'statusPedido',
            'formaPagamento',
            'endereco.endereco',
            'itens.produto',
            'itens.kit',
        ])->where('empresa_id', $empresaId);

        if ($request->has('status_id') && $request->status_id) {
            $query->where('status_pedido_id', $request->status_id);
        }

        if ($request->has('usuario_id') && $request->usuario_id) {
            $query->where('usuario_id', $request->usuario_id);
        }

        if ($request->has('data_inicio') && $request->data_inicio) {
            $query->where('created_at', '>=', $request->data_inicio . ' 00:00:00');
        }

        if ($request->has('data_fim') && $request->data_fim) {
            $query->where('created_at', '<=', $request->data_fim . ' 23:59:59');
        }

        if ($request->has('tipo') && $request->tipo) {
            $query->where('tipo_pedido', $request->tipo);
        }

        $orderBy = $request->get('order_by', 'created_at');
        if (! in_array($orderBy, self::ORDER_BY_PERMITIDOS, true)) {
            $orderBy = 'created_at';
        }
        $orderDirection = strtolower((string) $request->get('order_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($orderBy, $orderDirection);

        $perPage = (int) $request->get('per_page', 15);
        if ($perPage < 1) {
            $perPage = 15;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $pedidos = $query->paginate($perPage);

        return new ApiResourceCollection($pedidos);
    }
}
