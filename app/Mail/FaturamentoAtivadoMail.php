<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FaturamentoAtivadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $usuario,
        public float $valor,
        public string $vencimento
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Assinatura PetGre ativada',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.faturamento-ativado');
    }
}
