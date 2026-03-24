<?php

namespace App\Services\Produto;

use App\Models\Categorias;
use App\Models\PlanilhaTerceiros;
use App\Models\UnidadeMedida;

class ProdutoCatalogoAuxiliarService
{
    public function listarCategorias(): array
    {
        $categorias = Categorias::orderBy('nome')->get();

        return [
            'success'    => true,
            'categorias' => $categorias,
        ];
    }

    public function listarUnidadesMedidas(): array
    {
        $unidades = UnidadeMedida::orderBy('nome')->get();

        return [
            'success'  => true,
            'unidades' => $unidades,
        ];
    }

    public function listarTerceiros(): array
    {
        $planilhasTerceiros = PlanilhaTerceiros::all();

        return [
            'success'             => true,
            'planilhas_terceiros' => $planilhasTerceiros,
        ];
    }
}
