<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpresaFatura extends Model
{
    protected $table = 'empresa_faturas';

    protected $fillable = [
        'usuario_id',
        'mes_referencia',
        'valor',
        'status',
        'vencimento',
        'pago_em',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'vencimento' => 'date',
        'pago_em' => 'date',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
