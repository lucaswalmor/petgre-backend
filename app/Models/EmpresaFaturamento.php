<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpresaFaturamento extends Model
{
    protected $table = 'empresa_faturamento';

    protected $fillable = [
        'usuario_id',
        'nome_titular',
        'tipo_documento_titular',
        'cpf_cnpj',
        'email',
        'telefone',
        'chave_pix',
        'tipo_chave_pix',
        'assinatura_ativa',
        'asaas_customer_id',
        'asaas_subscription_id',
        'valor_atual',
        'data_ativacao',
    ];

    protected $casts = [
        'assinatura_ativa' => 'boolean',
        'valor_atual' => 'decimal:2',
        'data_ativacao' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
