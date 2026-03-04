<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\CalculosService;
use App\Models\Produto;

class CalculosServiceTest extends TestCase
{
    /**
     * Percentual de desconto: preço original 100, promocional 80 → 20%
     */
    public function test_calcular_percentual_desconto(): void
    {
        $this->assertEquals(20.0, CalculosService::calcularPercentualDesconto(100.0, 80.0));
        $this->assertEquals(0.0, CalculosService::calcularPercentualDesconto(100.0, 100.0));
        $this->assertEquals(50.0, CalculosService::calcularPercentualDesconto(100.0, 50.0));
        $this->assertEquals(0.0, CalculosService::calcularPercentualDesconto(0, 10.0));
    }

    /**
     * Preço promocional por percentual: 100 com 20% → 80
     */
    public function test_calcular_preco_promocional_por_percentual(): void
    {
        $this->assertEquals(80.0, CalculosService::calcularPrecoPromocionalPorPercentual(100.0, 20.0));
        $this->assertEquals(100.0, CalculosService::calcularPrecoPromocionalPorPercentual(100.0, 0));
        $this->assertEquals(50.0, CalculosService::calcularPrecoPromocionalPorPercentual(100.0, 50.0));
    }

    /**
     * getPrecoEfetivo: em promoção válida retorna preco_promocional, senão preco
     */
    public function test_get_preco_efetivo_retorna_promocional_quando_valido(): void
    {
        $produto = new Produto([
            'preco' => 100,
            'preco_promocional' => 80,
            'tem_promocao' => true,
            'promocao_ate' => now()->addDay(),
        ]);
        $this->assertEquals(80.0, CalculosService::getPrecoEfetivo($produto));
    }

    public function test_get_preco_efetivo_retorna_preco_quando_sem_promocao(): void
    {
        $produto = new Produto([
            'preco' => 100,
            'preco_promocional' => 80,
            'tem_promocao' => false,
            'promocao_ate' => null,
        ]);
        $this->assertEquals(100.0, CalculosService::getPrecoEfetivo($produto));
    }

    public function test_get_preco_efetivo_retorna_preco_quando_promocao_vencida(): void
    {
        $produto = new Produto([
            'preco' => 100,
            'preco_promocional' => 80,
            'tem_promocao' => true,
            'promocao_ate' => now()->subDay(),
        ]);
        $this->assertEquals(100.0, CalculosService::getPrecoEfetivo($produto));
    }
}
