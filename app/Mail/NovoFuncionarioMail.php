<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NovoFuncionarioMail extends Mailable
{
    use Queueable, SerializesModels;

    public $usuario;
    public $empresa;
    public $senha;

    /**
     * Create a new message instance.
     */
    public function __construct($usuario, $empresa, $senha)
    {
        $this->usuario = $usuario;
        $this->empresa = $empresa;
        $this->senha = $senha;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bem-vindo à equipe - ' . $this->empresa->nome_fantasia,
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
            view: 'emails.novo-funcionario',
            with: [
                'usuario' => $this->usuario,
                'empresa' => $this->empresa,
                'senha' => $this->senha,
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
