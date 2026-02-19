<?php

namespace App\Http\Resources\EmpresaAvaliacao;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmpresaAvaliacaoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Dados de identificação do cliente (nome, telefone, etc.) são
     * intencionalmente omitidos para preservar a privacidade dos clientes.
     * O lojista visualiza apenas nota, descrição e data.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'empresa_id' => $this->empresa_id,
            'pedido_id'  => $this->pedido_id,
            'nota'       => $this->nota,
            'descricao'  => $this->descricao,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Dados do pedido (sem identificação do cliente)
            'pedido' => $this->whenLoaded('pedido', function () {
                return [
                    'id'     => $this->pedido->id,
                    'codigo' => $this->pedido->codigo ?? 'PED-' . str_pad($this->pedido->id, 6, '0', STR_PAD_LEFT),
                ];
            }),

            // Status da solicitação de moderação (se houver)
            'moderacao' => $this->whenLoaded('moderacao', function () {
                if (!$this->moderacao) return null;
                return [
                    'id'     => $this->moderacao->id,
                    'status' => $this->moderacao->status,
                    'motivo' => $this->moderacao->motivo,
                ];
            }),

            // Campos formatados
            'nota_formatada'         => number_format($this->nota, 1, ',', '.'),
            'data_formatada'         => $this->created_at->format('d/m/Y H:i'),
            'dias_desde_avaliacao'   => $this->created_at->diffInDays(now()),
        ];
    }
}
