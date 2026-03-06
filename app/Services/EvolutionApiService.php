<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionApiService
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
     * Criar instância na Evolution API.
     *
     * @return array{success: bool, data?: array, error?: string}
     */
    public function criarInstancia(string $nomeInstancia): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->post("{$this->baseUrl}/instance/create", [
                    'instanceName' => $nomeInstancia,
                    'integration' => 'WHATSAPP-BAILEYS',
                ]);

            $body = $response->json();
            if (!$response->successful()) {
                Log::warning('Evolution API criarInstancia falhou', [
                    'instance' => $nomeInstancia,
                    'status' => $response->status(),
                    'body' => $body,
                ]);
                return [
                    'success' => false,
                    'error' => $body['message'] ?? $body['error'] ?? 'Erro ao criar instância',
                ];
            }
            return ['success' => true, 'data' => $body];
        } catch (\Throwable $e) {
            Log::error('Evolution API criarInstancia exceção', ['instance' => $nomeInstancia, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Buscar QR Code da instância.
     *
     * @return array{success: bool, base64?: string, pairingCode?: string, data?: array, error?: string}
     */
    public function buscarQrCode(string $nomeInstancia): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get("{$this->baseUrl}/instance/connect/{$nomeInstancia}");

            $body = $response->json();
            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => $body['response']['message'][0] ?? $body['error'] ?? 'Erro ao buscar QR Code',
                ];
            }
            $base64 = $body['base64'] ?? $body['code'] ?? null;
            $pairingCode = $body['pairingCode'] ?? null;
            return [
                'success' => true,
                'base64' => $base64,
                'pairingCode' => $pairingCode,
                'data' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('Evolution API buscarQrCode exceção', ['instance' => $nomeInstancia, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Buscar status de conexão da instância (open, connecting, close).
     *
     * @return array{success: bool, state?: string, error?: string}
     */
    public function buscarStatus(string $nomeInstancia): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get("{$this->baseUrl}/instance/connectionState/{$nomeInstancia}");

            $body = $response->json();
            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => $body['response']['message'][0] ?? $body['error'] ?? 'Erro ao buscar status',
                ];
            }
            $state = $body['state'] ?? $body['instance']['state'] ?? 'close';
            return ['success' => true, 'state' => strtolower($state)];
        } catch (\Throwable $e) {
            Log::error('Evolution API buscarStatus exceção', ['instance' => $nomeInstancia, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Desconectar instância (logout).
     *
     * @return array{success: bool, data?: array, error?: string}
     */
    public function desconectarInstancia(string $nomeInstancia): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->delete("{$this->baseUrl}/instance/logout/{$nomeInstancia}");

            $body = $response->json();
            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => $body['response']['message'][0] ?? $body['error'] ?? 'Erro ao desconectar',
                ];
            }
            return ['success' => true, 'data' => $body];
        } catch (\Throwable $e) {
            Log::error('Evolution API desconectarInstancia exceção', ['instance' => $nomeInstancia, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deletar instância da Evolution API.
     *
     * @return array{success: bool, data?: array, error?: string}
     */
    public function deletarInstancia(string $nomeInstancia): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->delete("{$this->baseUrl}/instance/delete/{$nomeInstancia}");

            $body = $response->json();
            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => $body['response']['message'][0] ?? $body['error'] ?? 'Erro ao deletar instância',
                ];
            }
            return ['success' => true, 'data' => $body];
        } catch (\Throwable $e) {
            Log::error('Evolution API deletarInstancia exceção', ['instance' => $nomeInstancia, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
