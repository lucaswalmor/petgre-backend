<?php

namespace Tests\Feature;

use App\Models\Permissao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PermissaoControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * GET /api/permissoes - sucesso lista permissões ativas
     */
    public function test_index_sucesso(): void
    {
        Permissao::unguarded(function () {
            Permissao::create([
                'nome' => 'Dashboard',
                'slug' => 'dashboard.index',
                'ativo' => true,
            ]);
        });
        $user = User::factory()->create(['tipo_cadastro' => 0]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/permissoes');

        $response->assertOk()
            ->assertJsonStructure(['permissoes']);
        $this->assertGreaterThanOrEqual(1, count($response->json('permissoes')));
    }

    /**
     * GET /api/permissoes - retorna apenas ativas
     */
    public function test_index_retorna_apenas_ativas(): void
    {
        Permissao::unguarded(function () {
            Permissao::create(['nome' => 'Ativa', 'slug' => 'ativa', 'ativo' => true]);
            Permissao::create(['nome' => 'Inativa', 'slug' => 'inativa', 'ativo' => false]);
        });
        $user = User::factory()->create(['tipo_cadastro' => 0]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/permissoes');

        $response->assertOk();
        $slugs = array_column($response->json('permissoes'), 'slug');
        $this->assertContains('ativa', $slugs);
        $this->assertNotContains('inativa', $slugs);
    }
}
