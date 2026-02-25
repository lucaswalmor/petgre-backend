<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteClienteControllerTest extends TestCase
{
    use RefreshDatabase;

    private function criarEmpresaCompleta(): Empresa
    {
        $nichoId = DB::table('nichos_empresa')->insertGetId([
            'nome' => 'Petshop',
            'slug' => 'petshop-' . uniqid(),
            'imagem' => null,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return Empresa::create([
            'razao_social' => 'Empresa Site LTDA ' . uniqid(),
            'nome_fantasia' => 'Site Teste',
            'slug' => 'site-teste-' . uniqid(),
            'email' => 'site@example.com',
            'telefone' => '34999999999',
            'cpf_cnpj' => '123456780001' . rand(80, 99),
            'nicho_id' => $nichoId,
            'cadastro_completo' => true,
            'ativo' => true,
        ]);
    }

    /**
     * GET /api/site/empresas - rota pública lista empresas ativas e completas
     */
    public function test_get_empresas_sucesso(): void
    {
        $this->criarEmpresaCompleta();

        $response = $this->getJson('/api/site/empresas');

        $response->assertOk();
    }

    /**
     * GET /api/site/empresa/{slug} - rota pública detalhe da empresa
     */
    public function test_get_empresa_por_slug_sucesso(): void
    {
        $empresa = $this->criarEmpresaCompleta();

        $response = $this->getJson("/api/site/empresa/{$empresa->slug}");

        $response->assertOk();
    }

    /**
     * GET /api/site/perfil - cliente autenticado vê próprio perfil
     */
    public function test_get_perfil_sucesso(): void
    {
        $user = User::factory()->create([
            'tipo_cadastro' => 1,
            'nome' => 'Cliente Site',
            'email' => 'cliente@site.com',
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/site/perfil');

        $response->assertOk();
    }
}
