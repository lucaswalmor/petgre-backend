<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmpresaFaturamentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuario_id,
            'nome_titular' => $this->nome_titular,
            'cpf_cnpj' => $this->cpf_cnpj,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'chave_pix' => $this->chave_pix,
            'tipo_chave_pix' => $this->tipo_chave_pix,
            'assinatura_ativa' => (bool) $this->assinatura_ativa,
        ];
    }
}
