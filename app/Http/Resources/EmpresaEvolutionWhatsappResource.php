<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmpresaEvolutionWhatsappResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'empresa_id' => $this->empresa_id,
            'instance_name' => $this->instance_name,
            'status' => $this->status,
            'conectado_em' => $this->conectado_em?->toIso8601String(),
        ];
    }
}
