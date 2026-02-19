<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    /**
     * Enviar email usando o mailer configurado (log ou smtp)
     */
    public function sendEmail(string $to, string $subject, string $body, ?string $fromName = null): bool
    {
        try {
            $fromName = $fromName ?: config('app.name', 'PetGre');

            // Usar Mail facade do Laravel (funciona com qualquer mailer configurado)
            Mail::raw($body, function ($message) use ($to, $subject, $fromName) {
                $message->to($to)
                        ->subject($subject)
                        ->from(config('mail.from.address'), $fromName);
            });

            Log::info("Email enviado com sucesso para: {$to}");
            return true;

        } catch (\Exception $e) {
            Log::error("Erro ao enviar email para {$to}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar email usando Mailable (para emails mais complexos)
     */
    public function sendMailable(string $to, $mailable): bool
    {
        try {
            Mail::to($to)->send($mailable);

            Log::info("Mailable enviado com sucesso para: {$to}");
            return true;

        } catch (\Exception $e) {
            Log::error("Erro ao enviar mailable para {$to}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Testar configuração de email
     */
    public function testEmail(string $to): array
    {
        $subject = 'Teste de Email - PetGre';
        $body = "Este é um email de teste do sistema PetGre.\n\nEnviado em: " . now()->format('d/m/Y H:i:s');

        $success = $this->sendEmail($to, $subject, $body);

        return [
            'success' => $success,
            'to' => $to,
            'subject' => $subject,
            'mailer' => config('mail.default'),
            'from' => config('mail.from.address'),
            'timestamp' => now()
        ];
    }
}