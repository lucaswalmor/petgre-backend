<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsuarioFaturamentoPedidos extends Model
{
    protected $table = 'usuario_faturamento_pedidos';

    protected $fillable = [
        'usuario_id',
        'mes_referencia',
        'total_pedidos',
        'assinatura_disparada',
    ];

    protected $casts = [
        'assinatura_disparada' => 'boolean',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
