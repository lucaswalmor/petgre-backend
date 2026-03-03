<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\EmpresaCupom;
use App\Models\User;
use App\Models\UsuarioEmpresas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmpresaCuponsControllerTest extends TestCase
{
    use RefreshDatabase;

    private static int $cpfCnpjSufixo = 50;

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
            'razao_social' => 'Empresa Cupom LTDA ' . uniqid(),
            'nome_fantasia' => 'Cupom Teste',
            'slug' => 'cupom-' . uniqid(),
            'email' => 'cupom@example.com',
            'telefone' => '34999999999',
            'cpf_cnpj' => '123456780001' . (self::$cpfCnpjSufixo++),
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
     * GET /api/cupons - 403 quando usuário sem empresa
     */
    public function test_index_sem_empresa_retorna_403(): void
    {
        $user = User::factory()->create(['tipo_cadastro' => 0]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/cupons');

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Usuário não está associado a nenhuma empresa ativa.']);
    }

    /**
     * GET /api/cupons - sucesso quando usuário tem empresa
     */
    public function test_index_sucesso(): void
    {
        $empresa = $this->criarEmpresa();
        $user = User::factory()->create(['tipo_cadastro' => 0]);
        $this->vincularUsuarioEmpresa($user, $empresa);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/cupons');

        $response->assertOk();
    }

    /**
     * POST /api/cupons - criar cupom com sucesso
     */
    public function test_store_sucesso(): void
    {
        $empresa = $this->criarEmpresa();
        $user = User::factory()->create(['tipo_cadastro' => 0]);
        $this->vincularUsuarioEmpresa($user, $empresa);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cupons', [
            'codigo' => 'PROMO10',
            'tipo' => 'percentual',
            'valor' => 10,
            'valor_minimo' => 50,
            'data_inicio' => now()->toDateTimeString(),
            'data_fim' => now()->addMonth()->toDateTimeString(),
            'limite_uso' => 100,
            'ativo' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true, 'message' => 'Cupom criado com sucesso'])
            ->assertJsonStructure(['cupom']);
        $this->assertDatabaseHas('empresa_cupons', ['codigo' => 'PROMO10', 'empresa_id' => $empresa->id]);
    }

    /**
     * GET /api/cupons/{id} - show sucesso
     */
    public function test_show_sucesso(): void
    {
        $empresa = $this->criarEmpresa();
        $user = User::factory()->create(['tipo_cadastro' => 0]);
        $this->vincularUsuarioEmpresa($user, $empresa);
        $cupom = EmpresaCupom::create([
            'empresa_id' => $empresa->id,
            'codigo' => 'SHOW20',
            'tipo' => 'percentual',
            'valor' => 20,
            'data_inicio' => now(),
            'data_fim' => now()->addMonth(),
            'ativo' => true,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/cupons/{$cupom->id}");

        $response->assertOk()
            ->assertJsonFragment(['success' => true])
            ->assertJsonPath('cupom.codigo', 'SHOW20');
    }

    /**
     * PUT /api/cupons/{id}/toggle-ativo - sucesso
     */
    public function test_toggle_ativo_sucesso(): void
    {
        $empresa = $this->criarEmpresa();
        $user = User::factory()->create(['tipo_cadastro' => 0]);
        $this->vincularUsuarioEmpresa($user, $empresa);
        $cupom = EmpresaCupom::create([
            'empresa_id' => $empresa->id,
            'codigo' => 'TOGGLE30',
            'tipo' => 'percentual',
            'valor' => 30,
            'data_inicio' => now(),
            'data_fim' => now()->addMonth(),
            'ativo' => true,
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/cupons/{$cupom->id}/toggle-ativo");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Status do cupom alterado com sucesso']);
    }
}
