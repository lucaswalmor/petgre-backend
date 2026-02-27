<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpresaFaturamento extends Model
{
    protected $table = 'empresa_faturamento';

    protected $fillable = [
        'usuario_id',
        'email',
        'telefone',
        'chave_pix',
        'tipo_chave_pix',
        'assinatura_ativa',
    ];

    protected $casts = [
        'assinatura_ativa' => 'boolean',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
