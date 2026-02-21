<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpresaPausaAgendada extends Model
{
    protected $table = 'empresa_pausas_agendadas';

    protected $fillable = [
        'empresa_id',
        'data_inicio',
        'data_fim',
        'motivo',
        'recorrente',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'datetime',
            'data_fim' => 'datetime',
            'recorrente' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
