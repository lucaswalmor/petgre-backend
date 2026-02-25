<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use App\Models\UsuarioEmpresas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function criarNichoEmpresa(): int
    {
        $slug = 'petshop-' . uniqid();
        return DB::table('nichos_empresa')->insertGetId([
            'nome' => 'Petshop',
            'slug' => $slug,
            'imagem' => null,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function criarEmpresa(): Empresa
    {
        $nichoId = $this->criarNichoEmpresa();
        return Empresa::create([
            'razao_social' => 'Empresa LTDA ' . uniqid(),
            'nome_fantasia' => 'Teste',
            'slug' => 'teste-' . uniqid(),
            'email' => 'e@example.com',
            'telefone' => '34999999999',
            'cpf_cnpj' => '123456780001' . rand(50, 99),
            'nicho_id' => $nichoId,
            'cadastro_completo' => false,
            'ativo' => true,
        ]);
    }

    private function vincularUsuarioEmpresa(User $u, Empresa $e): void
    {
        UsuarioEmpresas::create(['usuario_id' => $u->id, 'empresa_id' => $e->id]);
    }

    /**
     * GET /api/dashboard - 404 quando usuário não tem empresa
     */
    public function test_get_dados_sem_empresa_retorna_404(): void
    {
        $user = User::factory()->create(['tipo_cadastro' => 0]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(404)
            ->assertJsonFragment(['message' => 'Nenhuma empresa encontrada para este usuário.']);
    }
}
