<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NovoLojistaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $empresa;
    public $usuario;

    /**
     * Create a new message instance.
     */
    public function __construct($empresa, $usuario)
    {
        $this->empresa = $empresa;
        $this->usuario = $usuario;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bem-vindo ao PetGre - Sua empresa foi cadastrada!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.novo-lojista',
            with: [
                'empresa'   => $this->empresa,
                'usuario'   => $this->usuario,
                'logoUrl'   => 'https://i.ibb.co/d4dYC6FF/logo3-semfundo.png',
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