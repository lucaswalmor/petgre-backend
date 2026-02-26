<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NovoTicketMail extends Mailable
{
    use Queueable, SerializesModels;

    public $ticket;

    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket->load(['criadoPor', 'empresa', 'mensagens']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'PetGre - Novo chamado #' . $this->ticket->id . ': ' . $this->ticket->assunto,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.novo-ticket',
            with: [
                'ticket' => $this->ticket,
                'logoUrl' => 'https://i.ibb.co/d4dYC6FF/logo3-semfundo.png',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
