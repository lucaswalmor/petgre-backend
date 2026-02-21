<?php

namespace App\Http\Resources\PausaAgendada;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PausaAgendadaResource extends JsonResource
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
            'data_inicio' => $this->data_inicio?->format('Y-m-d H:i:s'),
            'data_fim' => $this->data_fim?->format('Y-m-d H:i:s'),
            'motivo' => $this->motivo,
            'recorrente' => $this->recorrente,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
