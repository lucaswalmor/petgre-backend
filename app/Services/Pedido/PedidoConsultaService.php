<?php

namespace App\Services\Pedido;

use App\Helpers\VerificaEmpresa;
use App\Http\Resources\Pedido\PedidoResource;
use App\Models\Pedido;
use App\Models\User;

class PedidoConsultaService
{
    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function obterDetalhe(string $id, User $usuario): array
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
            'avaliacao',
        ])->findOrFail($id);

        if ($pedido->usuario_id !== $usuario->id && ! VerificaEmpresa::verificaEmpresaPertenceAoUsuario($pedido->empresa_id)) {
            return [
                'ok'   => false,
                'http' => 403,
                'body' => [
                    'success' => false,
                    'error'   => 'Acesso negado',
                    'message' => 'Você não tem permissão para visualizar este pedido.',
                ],
            ];
        }

        return [
            'ok'   => true,
            'body' => [
                'success' => true,
                'pedido'  => new PedidoResource($pedido),
            ],
        ];
    }
}
