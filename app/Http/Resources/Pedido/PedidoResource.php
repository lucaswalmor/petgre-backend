<?php

namespace App\Http\Resources\Pedido;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PedidoResource extends JsonResource
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
            'codigo' => $this->codigo ?? 'PED-' . str_pad($this->id, 6, '0', STR_PAD_LEFT),
            'usuario_id' => $this->usuario_id,
            'empresa_id' => $this->empresa_id,
            'status_pedido_id' => $this->status_pedido_id,
            'pagamento_id' => $this->pagamento_id,
            'subtotal' => $this->subtotal,
            'desconto' => $this->desconto,
            'frete' => $this->frete,
            'total' => $this->total,
            'observacoes' => $this->observacoes,
            'ativo' => $this->ativo,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,

            // Relacionamentos
            'usuario' => $this->whenLoaded('usuario', function () {
                return [
                    'id' => $this->usuario->id,
                    'nome' => $this->usuario->nome,
                    'email' => $this->usuario->email,
                    'telefone' => $this->usuario->telefone,
                ];
            }),

            'empresa' => $this->whenLoaded('empresa', function () {
                return [
                    'id' => $this->empresa->id,
                    'nome_fantasia' => $this->empresa->nome_fantasia,
                    'razao_social' => $this->empresa->razao_social,
                    'slug' => $this->empresa->slug,
                    'telefone' => $this->empresa->telefone,
                    'path_logo' => $this->empresa->path_logo ?? null,
                ];
            }),

            'status_pedido' => $this->whenLoaded('statusPedido', function () {
                return [
                    'id' => $this->statusPedido->id,
                    'nome' => $this->statusPedido->nome,
                    'slug' => $this->statusPedido->slug,
                ];
            }),

            'forma_pagamento' => $this->whenLoaded('formaPagamento', function () {
                return [
                    'id' => $this->formaPagamento->id,
                    'nome' => $this->formaPagamento->nome,
                    'slug' => $this->formaPagamento->slug,
                ];
            }),

            'endereco' => $this->whenLoaded('endereco', function () {
                // O pedido_endereco tem relação com usuario_enderecos
                if ($this->endereco && $this->endereco->relationLoaded('endereco') && $this->endereco->endereco) {
                    $enderecoUsuario = $this->endereco->endereco;
                    return [
                        'id' => $enderecoUsuario->id,
                        'cep' => $enderecoUsuario->cep,
                        'rua' => $enderecoUsuario->rua,
                        'numero' => $enderecoUsuario->numero,
                        'complemento' => $enderecoUsuario->complemento,
                        'bairro' => $enderecoUsuario->bairro,
                        'cidade' => $enderecoUsuario->cidade,
                        'estado' => $enderecoUsuario->estado,
                        'ponto_referencia' => $enderecoUsuario->ponto_referencia,
                        'observacoes' => $this->endereco->observacoes, // Observações do pedido_endereco
                    ];
                }
                return null;
            }),

            'itens' => $this->whenLoaded('itens', function () {
                return $this->itens->map(function ($item) {
                    $produtoData = null;
                    if ($item->relationLoaded('produto') && $item->produto) {
                        $produtoData = [
                            'id' => $item->produto->id,
                            'nome' => $item->produto->nome,
                            'url_imagem' => $item->produto->url_imagem ?? null,
                            'vende_granel' => $item->produto->vende_granel ?? false,
                        ];
                        
                        if ($item->produto->relationLoaded('unidadeMedida') && $item->produto->unidadeMedida) {
                            $produtoData['unidade_medida'] = [
                                'id' => $item->produto->unidadeMedida->id,
                                'nome' => $item->produto->unidadeMedida->nome,
                                'sigla' => $item->produto->unidadeMedida->sigla,
                            ];
                        }
                    }
                    
                    $kitData = null;
                    if ($item->kit_id && $item->relationLoaded('kit') && $item->kit) {
                        $kitData = ['id' => $item->kit->id, 'nome' => $item->kit->nome];
                    }

                    return [
                        'id' => $item->id,
                        'produto_id' => $item->produto_id,
                        'kit_id' => $item->kit_id,
                        'kit' => $kitData,
                        'quantidade' => $item->quantidade,
                        'preco_unitario' => $item->preco_unitario,
                        'preco_total' => $item->preco_total,
                        'observacoes' => $item->observacoes,
                        'produto' => $produtoData,
                    ];
                });
            }),

            'historico_status' => $this->whenLoaded('historicoStatus', function () {
                return $this->historicoStatus->map(function ($historico) {
                    $statusPedidoData = null;
                    if ($historico->relationLoaded('statusPedido') && $historico->statusPedido) {
                        $statusPedidoData = [
                            'id' => $historico->statusPedido->id,
                            'nome' => $historico->statusPedido->nome,
                            'slug' => $historico->statusPedido->slug,
                        ];
                    }
                    
                    return [
                        'id' => $historico->id,
                        'status_pedido' => $statusPedidoData,
                        'observacoes' => $historico->observacoes,
                        'created_at' => $historico->created_at,
                    ];
                });
            }),

            'avaliacao' => $this->whenLoaded('avaliacao', function () {
                return [
                    'id' => $this->avaliacao->id,
                    'nota' => $this->avaliacao->nota,
                    'descricao' => $this->avaliacao->descricao,
                    'created_at' => $this->avaliacao->created_at,
                ];
            }),

            // Campos calculados
            'subtotal_formatado' => 'R$ ' . number_format($this->subtotal, 2, ',', '.'),
            'desconto_formatado' => 'R$ ' . number_format($this->desconto, 2, ',', '.'),
            'frete_formatado' => 'R$ ' . number_format($this->frete, 2, ',', '.'),
            'total_formatado' => 'R$ ' . number_format($this->total, 2, ',', '.'),

            // Dados do pet extraídos das observações (para serviços)
            'nome_pet' => $this->extrairNomePetDasObservacoes(),

            'quantidade_itens' => $this->whenLoaded('itens', function () {
                return $this->itens->sum('quantidade');
            }),

            'pode_ser_avaliado' => $this->when($this->statusPedido, function () {
                return $this->statusPedido->slug === 'entregue' && !$this->avaliacao;
            }),

            'tempo_decorrido' => $this->created_at->diffForHumans(),
        ];
    }

    /**
     * Extrair nome do pet das observações do pedido
     * Formato esperado: "DADOS DO PET:\nNome: NomeDoPet"
     */
    private function extrairNomePetDasObservacoes(): ?string
    {
        $observacoes = $this->observacoes;
        if (!$observacoes) {
            return null;
        }

        // Procura por "Nome: X" após "DADOS DO PET:"
        if (preg_match('/DADOS DO PET:[\s\S]*?Nome:\s*([^\n]+)/i', $observacoes, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}