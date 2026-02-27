<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function criarNichoId(): int
    {
        return DB::table('nichos_empresa')->insertGetId([
            'nome' => 'Petshop',
            'slug' => 'petshop-' . uniqid(),
            'imagem' => null,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function criarEmpresa(string $nomeFantasia = 'Loja Teste'): Empresa
    {
        $nichoId = $this->criarNichoId();
        return Empresa::create([
            'razao_social' => $nomeFantasia . ' LTDA ' . uniqid(),
            'nome_fantasia' => $nomeFantasia,
            'slug' => \Illuminate\Support\Str::slug($nomeFantasia) . '-' . uniqid(),
            'email' => 'empresa' . uniqid() . '@example.com',
            'telefone' => '34999999999',
            'cpf_cnpj' => '12.345.678/0001-' . str_pad((string) random_int(10, 99), 2, '0'),
            'nicho_id' => $nichoId,
            'cadastro_completo' => false,
            'ativo' => true,
        ]);
    }

    /**
     * POST /api/login - sucesso lojista
     */
    public function test_login_sucesso_lojista(): void
    {
        $user = User::factory()->create([
            'email' => 'lojista@example.com',
            'password' => Hash::make('senha123'),
            'tipo_cadastro' => 0,
            'ativo' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'lojista@example.com',
            'password' => 'senha123',
            'tipo_login' => 'lojista',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['success' => true, 'message' => 'Login realizado com sucesso'])
            ->assertJsonStructure(['token', 'user', 'empresas']);
    }

    /**
     * POST /api/login - lojista com uma empresa retorna array empresas com 1 item
     */
    public function test_login_lojista_com_uma_empresa_retorna_empresas_com_um_item(): void
    {
        $empresa = $this->criarEmpresa('Matriz');
        $user = User::factory()->create([
            'email' => 'lojista1@example.com',
            'password' => Hash::make('senha123'),
            'tipo_cadastro' => 0,
            'ativo' => true,
        ]);
        $user->empresas()->attach($empresa->id);

        $response = $this->postJson('/api/login', [
            'email' => 'lojista1@example.com',
            'password' => 'senha123',
            'tipo_login' => 'lojista',
        ]);

        $response->assertOk()
            ->assertJsonPath('empresas.0.id', $empresa->id)
            ->assertJsonPath('empresas.0.nome_fantasia', 'Matriz')
            ->assertJsonPath('empresas.0.is_matriz', true)
            ->assertJsonCount(1, 'empresas');
        $this->assertNotEmpty($response->json('token'));
    }

    /**
     * POST /api/login - lojista com múltiplas empresas retorna array empresas e token
     */
    public function test_login_lojista_com_multiplas_empresas_retorna_empresas_array(): void
    {
        $e1 = $this->criarEmpresa('Loja A');
        $e2 = $this->criarEmpresa('Loja B');
        $user = User::factory()->create([
            'email' => 'lojista2@example.com',
            'password' => Hash::make('senha123'),
            'tipo_cadastro' => 0,
            'ativo' => true,
        ]);
        $user->empresas()->attach([$e1->id, $e2->id]);

        $response = $this->postJson('/api/login', [
            'email' => 'lojista2@example.com',
            'password' => 'senha123',
            'tipo_login' => 'lojista',
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'empresas');
        $this->assertNotEmpty($response->json('token'));
        $ids = array_column($response->json('empresas'), 'id');
        $this->assertContains($e1->id, $ids);
        $this->assertContains($e2->id, $ids);
        foreach ($response->json('empresas') as $emp) {
            $this->assertArrayHasKey('id', $emp);
            $this->assertArrayHasKey('nome_fantasia', $emp);
            $this->assertArrayHasKey('is_matriz', $emp);
        }
    }

    /**
     * POST /api/login - sucesso cliente
     */
    public function test_login_sucesso_cliente(): void
    {
        $user = User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => Hash::make('senha123'),
            'tipo_cadastro' => 1,
            'ativo' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'cliente@example.com',
            'password' => 'senha123',
            'tipo_login' => 'cliente',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['success' => true])
            ->assertJsonStructure(['token', 'user']);
    }

    /**
     * POST /api/login - 422 tipo_login inválido (validação do request)
     */
    public function test_login_tipo_invalido_retorna_422(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'senha123',
            'tipo_login' => 'invalido',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Dados de login inválidos.'])
            ->assertJsonStructure(['errors' => ['tipo_login']]);
    }

    /**
     * POST /api/login - 422 dados inválidos (email/password faltando)
     */
    public function test_login_dados_invalidos_retorna_422(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'invalido',
            'password' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Dados de login inválidos.'])
            ->assertJsonStructure(['errors']);
    }

    /**
     * POST /api/login - 401 credenciais inválidas
     */
    public function test_login_credenciais_invalidas_retorna_401(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'tipo_cadastro' => 0,
            'password' => Hash::make('outrasenha'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'senhaerrada',
            'tipo_login' => 'lojista',
        ]);

        $response->assertStatus(401)
            ->assertJsonFragment(['message' => 'Credenciais inválidas. Verifique seu email e senha.']);
    }

    /**
     * POST /api/login - 403 usuário inativo
     */
    public function test_login_usuario_inativo_retorna_403(): void
    {
        User::factory()->create([
            'email' => 'inativo@example.com',
            'password' => Hash::make('senha123'),
            'tipo_cadastro' => 0,
            'ativo' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inativo@example.com',
            'password' => 'senha123',
            'tipo_login' => 'lojista',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Sua conta está desativada. Entre em contato com o administrador.']);
    }

    /**
     * POST /api/logout - sucesso
     */
    public function test_logout_sucesso(): void
    {
        $user = User::factory()->create(['tipo_cadastro' => 0]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/logout');

        $response->assertOk()
            ->assertJsonFragment(['success' => true, 'message' => 'Logout realizado com sucesso']);
    }

    /**
     * GET /api/user - sucesso
     */
    public function test_user_retorna_usuario_autenticado(): void
    {
        $user = User::factory()->create([
            'nome' => 'João',
            'email' => 'joao@example.com',
            'tipo_cadastro' => 1,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertOk()
            ->assertJsonFragment(['success' => true])
            ->assertJsonPath('user.email', 'joao@example.com');
    }
}
