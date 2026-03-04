<?php

namespace App\Http\Resources\Kit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $precoSomaItens = 0;
        $itensFormatted = [];
        if ($this->relationLoaded('itens')) {
            foreach ($this->itens as $item) {
                $produto = $item->relationLoaded('produto') ? $item->produto : $item->produto;
                $precoProduto = $produto ? (float) $produto->preco : 0;
                $precoSomaItens += $precoProduto * (int) $item->quantidade;
                $itensFormatted[] = [
                    'produto_id' => $item->produto_id,
                    'nome_produto' => $produto ? $produto->nome : null,
                    'quantidade' => (int) $item->quantidade,
                    'preco_produto' => $produto ? number_format((float) $produto->preco, 2, '.', '') : null,
                ];
            }
        }

        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'imagem' => $this->imagem,
            'preco' => number_format((float) $this->preco, 2, '.', ''),
            'ativo' => $this->ativo,
            'itens' => $itensFormatted,
            'preco_soma_itens' => number_format($precoSomaItens, 2, '.', ''),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
