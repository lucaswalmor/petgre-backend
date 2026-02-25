<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertJsonStructure(['token', 'user']);
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
