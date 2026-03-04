<?php

namespace App\Services;

use App\Models\Produto;

class CalculosService
{
    /**
     * Calcula o percentual de desconto com base no preço original e no preço promocional digitado.
     * Fórmula: ((precoOriginal - precoPromocional) / precoOriginal) * 100
     */
    public static function calcularPercentualDesconto(float $precoOriginal, float $precoPromocional): float
    {
        if ($precoOriginal <= 0) {
            return 0.0;
        }
        $desconto = (($precoOriginal - $precoPromocional) / $precoOriginal) * 100;
        return round(max(0, min(100, $desconto)), 2);
    }

    /**
     * Calcula o preço promocional com base no percentual de desconto digitado.
     * Fórmula: precoOriginal * (1 - percentual / 100)
     */
    public static function calcularPrecoPromocionalPorPercentual(float $precoOriginal, float $percentual): float
    {
        $percentual = max(0, min(100, $percentual));
        $preco = $precoOriginal * (1 - $percentual / 100);
        return round($preco, 2);
    }

    /**
     * Retorna o preço efetivo a ser cobrado pelo produto (promocional se válido, senão normal).
     * Regra: se tem_promocao = true, preco_promocional preenchido e promocao_ate null ou >= hoje → preco_promocional.
     * Caso contrário → preco.
     */
    public static function getPrecoEfetivo(Produto $produto): float
    {
        $emPromocao = $produto->tem_promocao
            && $produto->preco_promocional !== null
            && (float) $produto->preco_promocional > 0
            && (!$produto->promocao_ate || $produto->promocao_ate >= now()->startOfDay());

        return $emPromocao
            ? (float) $produto->preco_promocional
            : (float) $produto->preco;
    }
}
