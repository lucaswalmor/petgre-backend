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
            'tipo_documento_titular' => $this->tipo_documento_titular,
            'cpf_cnpj' => $this->cpf_cnpj,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'chave_pix' => $this->chave_pix,
            'tipo_chave_pix' => $this->tipo_chave_pix,
            'assinatura_ativa' => (bool) $this->assinatura_ativa,
            'asaas_customer_id' => $this->asaas_customer_id,
            'asaas_subscription_id' => $this->asaas_subscription_id,
            'valor_atual' => $this->valor_atual ? (float) $this->valor_atual : null,
            'data_ativacao' => $this->data_ativacao?->toIso8601String(),
        ];
    }
}
