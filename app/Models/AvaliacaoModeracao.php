<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AvaliacaoModeracao extends Model
{
    use HasFactory;

    protected $table = 'avaliacoes_moderacao';

    protected $fillable = [
        'avaliacao_id',
        'empresa_id',
        'motivo',
        'status',
        'observacao_moderador',
    ];

    const STATUS_PENDENTE    = 'pendente';
    const STATUS_EM_ANALISE  = 'em_analise';
    const STATUS_APROVADO    = 'aprovado';
    const STATUS_REJEITADO   = 'rejeitado';

    public function avaliacao()
    {
        return $this->belongsTo(EmpresaAvaliacao::class, 'avaliacao_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
