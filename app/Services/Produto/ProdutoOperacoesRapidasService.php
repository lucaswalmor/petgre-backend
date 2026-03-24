<?php

namespace App\Services\Produto;

use App\Helpers\VerificaEmpresa;
use App\Models\Produto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProdutoOperacoesRapidasService
{
    public function alternarDestaque(string $id): ?Produto
    {
        $produto = Produto::findOrFail($id);

        if (! VerificaEmpresa::verificaEmpresaPertenceAoUsuario($produto->empresa_id)) {
            return null;
        }

        $produto->destaque = ! $produto->destaque;
        $produto->save();

        return $produto->load(['categoria', 'unidadeMedida', 'empresa']);
    }

    public function alternarAtivo(string $id): ?Produto
    {
        $produto = Produto::findOrFail($id);

        if (! VerificaEmpresa::verificaEmpresaPertenceAoUsuario($produto->empresa_id)) {
            return null;
        }

        $produto->ativo = ! $produto->ativo;
        $produto->save();

        return $produto->load(['categoria', 'unidadeMedida', 'empresa']);
    }

    public function duplicar(string $id): ?Produto
    {
        $produto = Produto::findOrFail($id);

        if (! VerificaEmpresa::verificaEmpresaPertenceAoUsuario($produto->empresa_id)) {
            return null;
        }

        DB::beginTransaction();
        try {
            $novoNome = $produto->nome . ' - Cópia';
            $contador = 1;
            while (Produto::where('empresa_id', $produto->empresa_id)->where('nome', $novoNome)->exists()) {
                $contador++;
                $novoNome = $produto->nome . " - Cópia {$contador}";
            }

            $novoProduto = $produto->replicate(['imagem', 'sku', 'slug']);
            $novoProduto->nome = $novoNome;
            $novoProduto->slug = Str::slug($novoNome) . '-' . uniqid();
            $novoProduto->sku = null;
            $novoProduto->imagem = null;
            $novoProduto->tem_promocao = $produto->tem_promocao && $produto->preco_promocional ? true : false;
            $novoProduto->save();

            DB::commit();

            return $novoProduto->load(['categoria', 'unidadeMedida', 'empresa']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
