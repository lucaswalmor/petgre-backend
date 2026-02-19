<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NovoClienteMail extends Mailable
{
    use Queueable, SerializesModels;

    public $usuario;

    /**
     * Create a new message instance.
     */
    public function __construct($usuario)
    {
        $this->usuario = $usuario;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bem-vindo ao PetGre - Sua conta foi criada!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Embed logo como base64 para funcionar em qualquer cliente de email
        $logoPath = public_path('logo3_semfundo.png');
        $logoBase64 = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : null;

        return new Content(
            view: 'emails.novo-cliente',
            with: [
                'usuario' => $this->usuario,
                'logoBase64' => $logoBase64,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
