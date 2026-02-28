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
        public ?string $pixCopiaCola,
        public string $tipo = 'desativada' // 'vencida' ou 'desativada' ou 'aviso_previo'
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = match($this->tipo) {
            'vencida' => 'PetGre - Fatura Vencida',
            'desativada' => 'PetGre - Assinatura Suspensa',
            'aviso_previo' => 'PetGre - Lembrete: Fatura Vence em 3 Dias',
            default => 'PetGre - Assinatura PetGre – pagamento em atraso'
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.assinatura-inativa');
    }
}
