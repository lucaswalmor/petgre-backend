<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpresaEvolutionWhatsapp extends Model
{
    protected $table = 'empresa_evolution_whatsapp';

    protected $fillable = [
        'empresa_id',
        'instance_name',
        'status',
        'conectado_em',
    ];

    protected $casts = [
        'conectado_em' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
