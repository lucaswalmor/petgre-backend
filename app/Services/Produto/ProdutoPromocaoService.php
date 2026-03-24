<?php

namespace App\Services\Produto;

use App\Services\CalculosService;

class ProdutoPromocaoService
{
    /**
     * @return array{preco_promocional: float, percentual: float}
     */
    public function calcular(float $precoOriginal, ?float $precoPromocional, ?float $percentual): array
    {
        if ($percentual !== null) {
            $precoPromocional = CalculosService::calcularPrecoPromocionalPorPercentual($precoOriginal, $percentual);
        } elseif ($precoPromocional !== null) {
            $percentual = CalculosService::calcularPercentualDesconto($precoOriginal, $precoPromocional);
        } else {
            return [
                'preco_promocional' => $precoOriginal,
                'percentual'        => 0,
            ];
        }

        return [
            'preco_promocional' => round($precoPromocional, 2),
            'percentual'        => round($percentual, 2),
        ];
    }
}
