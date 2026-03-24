<?php

namespace App\Services\Pedido;

use App\Helpers\VerificaEmpresa;
use App\Models\Pedido;

class PedidoExclusaoService
{
    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function excluirSePendente(string $id): array
    {
        $pedido = Pedido::with('statusPedido')->findOrFail($id);

        if (! VerificaEmpresa::verificaEmpresaPertenceAoUsuario($pedido->empresa_id)) {
            return [
                'ok'   => false,
                'http' => 403,
                'body' => [
                    'success' => false,
                    'error'   => 'Acesso negado',
                    'message' => 'Você não tem permissão para excluir este pedido.',
                ],
            ];
        }

        if ($pedido->statusPedido->slug !== 'pendente') {
            return [
                'ok'   => false,
                'http' => 400,
                'body' => [
                    'success' => false,
                    'error'   => 'Não é possível excluir este pedido',
                    'message' => 'Apenas pedidos pendentes podem ser excluídos.',
                ],
            ];
        }

        $pedido->delete();

        return [
            'ok'   => true,
            'body' => [
                'success' => true,
                'message' => 'Pedido excluído com sucesso',
            ],
        ];
    }
}
