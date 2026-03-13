<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $table = 'leads';

    protected $fillable = [
        'nome',
        'email',
        'whatsapp',
        'tipo_empresa',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'ip_address',
        'user_agent',
        'referrer',
        'contato_em',
        'status',
        'observacoes',
    ];

    protected $casts = [
        'contato_em' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'novo',
    ];
}
