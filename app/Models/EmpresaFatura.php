<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpresaFatura extends Model
{
    protected $table = 'empresa_faturas';

    protected $fillable = [
        'usuario_id',
        'empresa_id',
        'asaas_payment_id',
        'mes_referencia',
        'valor',
        'status',
        'vencimento',
        'pago_em',
        'quantidade_pedidos',
        'quantidade_filiais',
        'pix_qrcode_base64',
        'pix_copia_cola',
        'link_fatura',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'vencimento' => 'date',
        'pago_em' => 'date',
        'quantidade_pedidos' => 'integer',
        'quantidade_filiais' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * Verificar se a fatura está paga
     */
    public function estaPaga(): bool
    {
        return $this->status === 'pago';
    }

    /**
     * Verificar se a fatura está vencida
     */
    public function estaVencida(): bool
    {
        return $this->status === 'vencido' ||
            ($this->status === 'pendente' && $this->vencimento && $this->vencimento->isPast());
    }
}
