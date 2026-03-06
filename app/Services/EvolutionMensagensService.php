<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionMensagensService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.evolution_api.url', ''), '/');
        $this->apiKey = config('services.evolution_api.key', '');
    }

    private function headers(): array
    {
        return [
            'apikey' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Envia mensagem de texto para um número via WhatsApp.
     *
     * @param string $instanceName Nome da instância (ex: LOJA_PRINCIPAL_1)
     * @param string $numero Número do destinatário (formato: 5534999999999)
     * @param string $mensagem Texto da mensagem
     * @return array{success: bool, message?: string}
     */
    public function enviarMensagemTexto(string $instanceName, string $numero, string $mensagem): array
    {
        try {
            // Remove qualquer caractere não numérico do número
            $numeroLimpo = preg_replace('/[^0-9]/', '', $numero);

            if (empty($numeroLimpo)) {
                return [
                    'success' => false,
                    'message' => 'Número de telefone inválido',
                ];
            }

            $response = Http::withHeaders($this->headers())
                ->post("{$this->baseUrl}/message/sendText/{$instanceName}", [
                    'number' => $numeroLimpo,
                    'text' => $mensagem,
                    'options' => [
                        'delay' => 1200,
                        'presence' => 'composing',
                    ],
                ]);

            $body = $response->json();

            if (!$response->successful()) {
                Log::warning('Evolution API: falha ao enviar mensagem', [
                    'instance' => $instanceName,
                    'numero' => $numeroLimpo,
                    'status' => $response->status(),
                    'response' => $body,
                ]);

                return [
                    'success' => false,
                    'message' => $body['message'] ?? $body['error'] ?? 'Erro ao enviar mensagem',
                ];
            }

            Log::info('Evolution API: mensagem enviada com sucesso', [
                'instance' => $instanceName,
                'numero' => $numeroLimpo,
            ]);

            return ['success' => true];
        } catch (\Throwable $e) {
            Log::error('Evolution API: exceção ao enviar mensagem', [
                'instance' => $instanceName,
                'numero' => $numero,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Formata número de telefone para o formato internacional (55 + DDD + número).
     *
     * @param string $telefone Telefone no formato brasileiro
     * @return string|null Número formatado ou null se inválido
     */
    public function formatarNumeroInternacional(string $telefone): ?string
    {
        // Remove tudo exceto números
        $numeros = preg_replace('/[^0-9]/', '', $telefone);

        // Se começar com 0, remove
        if (strpos($numeros, '0') === 0) {
            $numeros = substr($numeros, 1);
        }

        // Se não começar com 55, adiciona
        if (!str_starts_with($numeros, '55')) {
            $numeros = '55' . $numeros;
        }

        // Validação básica: deve ter pelo menos 12 dígitos (55 + DDD + 9 dígitos)
        if (strlen($numeros) < 12) {
            return null;
        }

        return $numeros;
    }
}
