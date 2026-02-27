<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmpresaFaturaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $incluirPix = $this->resource->getAttribute('incluir_pix_qrcode') ?? false;

        return [
            'id' => $this->id,
            'mes_referencia' => $this->mes_referencia,
            'valor' => 'R$ ' . number_format((float) $this->valor, 2, ',', '.'),
            'status' => $this->status,
            'vencimento' => $this->vencimento?->format('d/m/Y'),
            'pago_em' => $this->pago_em?->format('d/m/Y'),
            'pix_copia_cola' => $this->when($incluirPix, $this->pix_copia_cola),
            'pix_qrcode_base64' => $this->when($incluirPix, $this->pix_qrcode_base64),
            'link_fatura' => $this->link_fatura,
        ];
    }
}
