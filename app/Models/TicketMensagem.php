<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketMensagem extends Model
{
    protected $table = 'ticket_mensagens';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'tipo_remetente',
        'mensagem',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
