<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssinaturaInativaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $usuario,
        public float $valor,
        public string $vencimento,
        public ?string $linkFatura,
        public ?string $pixCopiaCola
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Assinatura PetGre – pagamento em atraso',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.assinatura-inativa');
    }
}
