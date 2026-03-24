<?php

namespace App\Services\Pedido;

use App\Models\Produto;

class PedidoDominioAuxiliarService
{
    /**
     * @param  array<int, array<string, mixed>>  $itens
     */
    public function determinarTipoPedido(array $itens): string
    {
        $temProduto = false;
        $temServico = false;

        foreach ($itens as $item) {
            if (! empty($item['kit_id'])) {
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
        }
        if ($temServico) {
            return 'servico';
        }

        return 'produto';
    }

    public function extrairDataAgendamento(?string $observacoes): ?string
    {
        if (! $observacoes) {
            return null;
        }

        $padroes = [
            '/Data Preferencial:\s*(\d{2}\/\d{2}\/\d{4})(?:\s*às\s*(\d{2}:\d{2}))?/i',
            '/Data:\s*(\d{2}\/\d{2}\/\d{4})(?:\s*às\s*(\d{2}:\d{2}))?/i',
            '/Agendado para:\s*(\d{2}\/\d{2}\/\d{4})(?:\s*às\s*(\d{2}:\d{2}))?/i',
        ];

        foreach ($padroes as $padrao) {
            if (preg_match($padrao, $observacoes, $matches)) {
                $dataBr = $matches[1];
                $hora = $matches[2] ?? null;

                $partes = explode('/', $dataBr);
                if (count($partes) === 3) {
                    $dataFormatada = $partes[2] . '-' . $partes[1] . '-' . $partes[0];

                    if ($hora) {
                        return $dataFormatada . ' ' . $hora . ':00';
                    }

                    return $dataFormatada;
                }
            }
        }

        return null;
    }
}
