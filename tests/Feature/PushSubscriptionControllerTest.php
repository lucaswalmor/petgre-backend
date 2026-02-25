<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushSubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * GET /api/push/vapid-public-key - retorna chave
     */
    public function test_vapid_public_key_sucesso(): void
    {
        $user = User::factory()->create(['tipo_cadastro' => 0]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/push/vapid-public-key');

        $response->assertOk()
            ->assertJsonStructure(['vapid_public_key']);
    }

    /**
     * POST /api/push/subscribe - sucesso
     */
    public function test_store_subscribe_sucesso(): void
    {
        $user = User::factory()->create(['tipo_cadastro' => 0]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test',
            'keys' => [
                'p256dh' => 'key_p256dh_example',
                'auth' => 'key_auth_example',
            ],
        ]);

        $response->assertOk()
            ->assertJsonFragment(['success' => true]);
    }

    /**
     * POST /api/push/subscribe - 422 quando dados faltando
     */
    public function test_store_subscribe_dados_invalidos_retorna_422(): void
    {
        $user = User::factory()->create(['tipo_cadastro' => 0]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/push/subscribe', [
            'endpoint' => 'https://example.com',
        ]);

        $response->assertStatus(422);
    }
}
