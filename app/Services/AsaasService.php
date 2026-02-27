<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsaasService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.asaas.base_url', 'https://api.asaas.com/v3'), '/');
        $this->apiKey = config('services.asaas.api_key', '');
    }

    private function request(string $method, string $path, array $data = []): array
    {
        $url = $this->baseUrl . $path;
        $response = Http::withHeaders([
            'access_token' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->{strtolower($method)}($url, $method !== 'GET' ? $data : []);

        $body = $response->json() ?? [];
        if (!$response->successful()) {
            Log::warning('Asaas API error', ['url' => $url, 'status' => $response->status(), 'body' => $body]);
        }
        return $body;
    }

    public function criarCliente(array $dados): array
    {
        $payload = [
            'name' => $dados['name'] ?? '',
            'cpfCnpj' => $dados['cpfCnpj'] ?? '',
            'email' => $dados['email'] ?? '',
            'phone' => $dados['phone'] ?? '',
        ];
        return $this->request('POST', '/customers', $payload);
    }

    public function atualizarCliente(string $asaasCustomerId, array $dados): array
    {
        $payload = array_filter([
            'email' => $dados['email'] ?? null,
            'phone' => $dados['phone'] ?? null,
        ], fn ($v) => $v !== null);
        return $this->request('PUT', '/customers/' . $asaasCustomerId, $payload);
    }

    public function criarAssinatura(string $customerId, float $valor, string $nextDueDate): array
    {
        $payload = [
            'customer' => $customerId,
            'billingType' => 'PIX',
            'value' => $valor,
            'nextDueDate' => $nextDueDate,
            'cycle' => 'MONTHLY',
            'description' => 'Assinatura PetGre',
        ];
        return $this->request('POST', '/subscriptions', $payload);
    }

    public function atualizarAssinatura(string $subscriptionId, float $novoValor): array
    {
        return $this->request('PUT', '/subscriptions/' . $subscriptionId, ['value' => $novoValor]);
    }

    public function cancelarAssinatura(string $subscriptionId): array
    {
        return $this->request('DELETE', '/subscriptions/' . $subscriptionId);
    }

    public function buscarPagamento(string $paymentId): array
    {
        return $this->request('GET', '/payments/' . $paymentId);
    }
}
