<?php

namespace App\Http\Resources\Produto;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProdutoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'empresa_id' => $this->empresa_id,
            'categoria_id' => $this->categoria_id,
            'unidade_medida_id' => $this->unidade_medida_id,
            'tipo' => $this->tipo,
            'nome' => $this->nome,
            'imagem' => $this->imagem,
            'slug' => $this->slug,
            'descricao' => $this->descricao,
            'preco' => $this->preco,
            'estoque' => $this->estoque,
            'destaque' => $this->destaque,
            'ativo' => $this->ativo,

            // Novas colunas
            'marca' => $this->marca,
            'sku' => $this->sku,
            'preco_custo' => $this->preco_custo,
            'estoque_minimo' => $this->estoque_minimo,
            'ativar_estoque_minimo' => $this->ativar_estoque_minimo ?? false,
            'peso' => $this->peso,
            'altura' => $this->altura,
            'largura' => $this->largura,
            'comprimento' => $this->comprimento,
            'ordem' => $this->ordem,
            'preco_promocional' => $this->preco_promocional,
            'preco_promocional_percentual' => $this->preco_promocional_percentual,
            'promocao_ate' => $this->promocao_ate?->format('Y-m-d'),
            'tem_promocao' => $this->tem_promocao,
            'vende_granel' => $this->vende_granel,

            // Campos de serviço
            'tipo_porte' => $this->tipo_porte,
            'preco_pequeno' => $this->preco_pequeno,
            'preco_medio' => $this->preco_medio,
            'preco_grande' => $this->preco_grande,
            'porte_descricao_pequeno' => $this->porte_descricao_pequeno,
            'porte_descricao_medio' => $this->porte_descricao_medio,
            'porte_descricao_grande' => $this->porte_descricao_grande,
            'duracao_estimada' => $this->duracao_estimada,
            'inclui_servico' => $this->inclui_servico,

            // Relacionamentos
            'empresa' => $this->whenLoaded('empresa', function () {
                return [
                    'id' => $this->empresa->id,
                    'nome_fantasia' => $this->empresa->nome_fantasia,
                    'razao_social' => $this->empresa->razao_social,
                    'slug' => $this->empresa->slug,
                ];
            }),

            'categoria' => $this->whenLoaded('categoria', function () {
                return [
                    'id' => $this->categoria->id,
                    'nome' => $this->categoria->nome,
                    'slug' => $this->categoria->slug,
                    'imagem' => $this->categoria->imagem,
                ];
            }),

            'unidade_medida' => $this->whenLoaded('unidadeMedida', function () {
                return [
                    'id' => $this->unidadeMedida->id,
                    'nome' => $this->unidadeMedida->nome,
                    'sigla' => $this->unidadeMedida->sigla,
                ];
            }),

            // Campos calculados
            'preco_formatado' => $this->when($this->preco, function () {
                return 'R$ ' . number_format($this->preco, 2, ',', '.');
            }),

            'estoque_formatado' => $this->when($this->estoque !== null, function () {
                return number_format($this->estoque, 3, ',', '.');
            }),

            'tem_estoque' => $this->when($this->estoque !== null, function () {
                return $this->estoque > 0;
            }),

            // Máximo que o cliente pode pedir (para travar quantidade no carrinho): serviço = null (ilimitado), granel = estoque em gramas, outro = estoque em unidades
            'quantidade_maxima' => $this->when($this->tipo !== 'servico' && $this->estoque !== null, function () {
                if ($this->vende_granel) {
                    return (int) floor((float) $this->estoque * 1000);
                }
                return (int) floor((float) $this->estoque);
            }),

            // Campos calculados adicionais
            'preco_atual' => $this->getPrecoAtual(),
            'esta_em_promocao' => $this->estaEmPromocao(),
            'estoque_baixo' => $this->estoqueBaixo(),
            'margem_lucro' => $this->getMargemLucro(),
            'vende_a_granel' => $this->vendeAGranel(),

            'preco_atual_formatado' => $this->when($this->getPrecoAtual(), function () {
                return 'R$ ' . number_format($this->getPrecoAtual(), 2, ',', '.');
            }),

            'preco_custo_formatado' => $this->when($this->preco_custo, function () {
                return 'R$ ' . number_format($this->preco_custo, 2, ',', '.');
            }),

            'preco_promocional_formatado' => $this->when($this->preco_promocional, function () {
                return 'R$ ' . number_format($this->preco_promocional, 2, ',', '.');
            }),

            'peso_formatado' => $this->when($this->peso, function () {
                return number_format($this->peso, 3, ',', '.') . ' kg';
            }),

            'dimensoes_formatado' => $this->when($this->altura && $this->largura && $this->comprimento, function () {
                return $this->altura . ' x ' . $this->largura . ' x ' . $this->comprimento . ' cm';
            }),

            // URLs úteis
            'url_imagem' => $this->when($this->imagem, function () {
                return $this->imagem;
            }),
        ];
    }
}