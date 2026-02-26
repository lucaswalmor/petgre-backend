<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $table = 'tickets';

    protected $fillable = [
        'empresa_id',
        'assunto',
        'status',
        'criado_por',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function criadoPor()
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function mensagens()
    {
        return $this->hasMany(TicketMensagem::class, 'ticket_id')->orderBy('created_at');
    }
}
