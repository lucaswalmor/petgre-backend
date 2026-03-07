<?php

namespace App\Http\Resources\EmpresaCupom;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmpresaCupomResource extends JsonResource
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
            'codigo' => $this->codigo,
            'tipo' => $this->tipo,
            'tipo_formatado' => $this->tipo === 'percentual' ? 'Percentual' : 'Valor Fixo',
            'valor' => $this->valor,
            'valor_formatado' => $this->tipo === 'percentual'
                ? $this->valor . '%'
                : 'R$ ' . number_format($this->valor, 2, ',', '.'),
            'valor_minimo' => $this->valor_minimo,
            'valor_minimo_formatado' => $this->valor_minimo
                ? 'R$ ' . number_format($this->valor_minimo, 2, ',', '.')
                : null,
            'data_inicio' => $this->data_inicio?->format('Y-m-d H:i:s'),
            'data_fim' => $this->data_fim?->format('Y-m-d H:i:s'),
            'limite_uso' => $this->limite_uso,
            'ativo' => $this->ativo,
            'status' => $this->isValido() ? 'ativo' : 'inativo',
            'usos_totais' => $this->usos()->count(),
            'usos_atuais' => $this->usos()->count(),
            'empresa' => $this->whenLoaded('empresa', [
                'id' => $this->empresa->id,
                'nome_fantasia' => $this->empresa->nome_fantasia,
                'slug' => $this->empresa->slug,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}