<?php

namespace Tests\Feature;

use App\Helpers\VerificaEmpresa;
use App\Models\Empresa;
use App\Models\PasswordReset;
use App\Models\Permissao;
use App\Models\User;
use App\Models\UsuarioEmpresas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsuarioControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helpers básicos de criação
     */
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
            'razao_social' => 'Empresa Pet LTDA ' . uniqid(),
            'nome_fantasia' => 'Pet Teste',
            'slug' => 'pet-teste-' . uniqid(),
            'email' => 'empresa@example.com',
            'telefone' => '34999999999',
            'cpf_cnpj' => '123456780001' . rand(10, 99),
            'nicho_id' => $nichoId,
            'cadastro_completo' => false,
            'ativo' => true,
        ]);
    }

    private function vincularUsuarioEmpresa(User $usuario, Empresa $empresa): void
    {
        UsuarioEmpresas::create([
            'usuario_id' => $usuario->id,
            'empresa_id' => $empresa->id,
        ]);
    }

    private function criarPermissaoDashboard(): Permissao
    {
        return Permissao::unguarded(function () {
            return Permissao::create([
                'nome' => 'Dashboard',
                'slug' => 'dashboard.index',
                'ativo' => true,
            ]);
        });
    }

    private function criarLojistaComEmpresa(bool $master = false): array
    {
        $empresa = $this->criarEmpresa();

        $usuario = User::factory()->create([
            'is_master' => $master,
            'tipo_cadastro' => 0, // lojista
        ]);

        $this->vincularUsuarioEmpresa($usuario, $empresa);

        return [$usuario, $empresa];
    }

    /**
     * Payload válido para POST /api/usuarios (cliente – campos conforme Postman).
     */
    private function payloadClienteValido(?string $email = null): array
    {
        $email = $email ?? 'cliente' . uniqid() . '@example.com';
        return [
            'nome' => 'Cliente Teste',
            'email' => $email,
            'password' => 'senhaSegura123',
            'telefone' => '34999999999',
            'endereco' => [
                'cep' => '38400-000',
                'rua' => 'Rua Teste',
                'numero' => '123',
                'bairro' => 'Centro',
                'cidade' => 'Uberlândia',
                'estado' => 'MG',
            ],
        ];
    }

    /**
     * Payload válido para POST /api/usuarios/criar-funcionario (campos conforme Postman).
     */
    private function payloadFuncionarioValido(int $empresaId, array $permissaoIds, ?string $email = null): array
    {
        $email = $email ?? 'funcionario' . uniqid() . '@example.com';
        return [
            'nome' => 'Funcionario Teste',
            'email' => $email,
            'telefone' => '34999999999',
            'empresa_id' => $empresaId,
            'permissoes' => $permissaoIds,
        ];
    }

    /**
     * POST /api/usuarios (cliente público) - sucesso
     */
    public function test_store_cliente_publico_sucesso(): void
    {
        $payload = $this->payloadClienteValido('cliente@example.com');

        $response = $this->postJson('/api/usuarios', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'message' => 'Usuário criado com sucesso',
            ])
            ->assertJsonPath('usuario.nome', 'Cliente Teste')
            ->assertJsonPath('usuario.email', 'cliente@example.com');

        $this->assertDatabaseHas('usuarios', [
            'email' => 'cliente@example.com',
            'tipo_cadastro' => 1,
            'primeiro_login' => false,
        ]);

        $usuario = User::where('email', 'cliente@example.com')->first();
        $this->assertNotNull($usuario);
        $this->assertCount(1, $usuario->enderecos);
        $this->assertEquals('Rua Teste', $usuario->enderecos->first()->rua);
    }

    /**
     * POST /api/usuarios (cliente público) - dados inválidos
     */
    public function test_store_cliente_publico_dados_invalidos_retorna_422(): void
    {
        $response = $this->postJson('/api/usuarios', [
            // nome ausente
            'email' => 'invalido',
            'password' => '123', // muito curta
            'telefone' => '',
            // endereço incompleto
            'endereco' => [
                'rua' => '',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'success' => false,
                'message' => 'Dados inválidos. Verifique os erros abaixo.',
            ])
            ->assertJsonStructure([
                'errors' => ['nome', 'email', 'password', 'telefone', 'endereco.cep', 'endereco.rua', 'endereco.numero', 'endereco.bairro', 'endereco.cidade', 'endereco.estado'],
            ]);
    }

    /**
     * POST /api/usuarios (cliente público) - email duplicado mesmo tipo_cadastro
     */
    public function test_store_cliente_email_duplicado_retorna_422(): void
    {
        User::factory()->create([
            'email' => 'cliente@example.com',
            'tipo_cadastro' => 1,
        ]);

        $payload = $this->payloadClienteValido('cliente@example.com');
        $payload['nome'] = 'Outro Cliente';

        $response = $this->postJson('/api/usuarios', $payload);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'success' => false,
            ])
            ->assertJsonPath('errors.email.0', 'Este email já está sendo usado por outro usuário.');
    }

    /**
     * POST /api/usuarios (funcionário) - sucesso com permissões e endereço da empresa
     */
    public function test_store_funcionario_autenticado_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $this->criarPermissaoDashboard();

        // endereço da empresa
        DB::table('empresa_endereco')->insert([
            'empresa_id' => $empresa->id,
            'cep' => '38400-000',
            'logradouro' => 'Rua Empresa',
            'numero' => '100',
            'complemento' => null,
            'bairro' => 'Centro',
            'cidade' => 'Uberlândia',
            'estado' => 'MG',
            'ponto_referencia' => null,
            'observacoes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissao = Permissao::first();
        Sanctum::actingAs($lojista);

        $payload = $this->payloadFuncionarioValido($empresa->id, [$permissao->id], 'funcionario@example.com');

        $response = $this->postJson('/api/usuarios', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'message' => 'Usuário criado com sucesso',
            ])
            ->assertJsonPath('usuario.nome', 'Funcionario Teste')
            ->assertJsonPath('usuario.email', 'funcionario@example.com');

        $this->assertDatabaseHas('usuarios', [
            'email' => 'funcionario@example.com',
            'tipo_cadastro' => 0,
            'primeiro_login' => true,
        ]);

        $usuario = User::where('email', 'funcionario@example.com')->first();
        $this->assertNotNull($usuario);
        $this->assertCount(1, $usuario->enderecos);
        $this->assertEquals('Rua Empresa', $usuario->enderecos->first()->rua);
        $this->assertTrue($usuario->permissoes->contains('id', $permissao->id));
    }

    /**
     * POST /api/usuarios (funcionário) - faltando empresa_id/permissoes com token
     */
    public function test_store_funcionario_autenticado_sem_empresa_ou_permissoes_retorna_422(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        Sanctum::actingAs($lojista);

        $response = $this->postJson('/api/usuarios/criar-funcionario', [
            'nome' => 'Funcionario Teste',
            'email' => 'funcionario@example.com',
            'telefone' => '34999999999',
            // sem empresa_id e permissoes (obrigatórios para criar funcionário)
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false])
            ->assertJsonStructure(['errors']);
    }

    /**
     * POST /api/usuarios (funcionário) - empresa não pertence ao lojista autenticado
     */
    public function test_store_funcionario_autenticado_empresa_nao_pertence_ao_usuario_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();
        $this->criarPermissaoDashboard();
        $permissao = Permissao::first();
        Sanctum::actingAs($lojista);

        $payload = $this->payloadFuncionarioValido($empresaOutra->id, [$permissao->id], 'funcionario@example.com');
        $response = $this->postJson('/api/usuarios/criar-funcionario', $payload);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'error' => 'Acesso negado',
            ]);
    }

    /**
     * POST /api/usuarios/criar-funcionario - 422 quando dados inválidos (email, nome, permissoes inexistentes).
     */
    public function test_store_funcionario_dados_invalidos_retorna_422(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $this->criarPermissaoDashboard();
        $permissao = Permissao::first();
        Sanctum::actingAs($lojista);

        $response = $this->postJson('/api/usuarios/criar-funcionario', [
            'nome' => '',
            'email' => 'email-invalido',
            'telefone' => '',
            'empresa_id' => $empresa->id,
            'permissoes' => [99999], // id de permissão inexistente
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'success' => false,
                'message' => 'Dados inválidos. Verifique os erros abaixo.',
            ])
            ->assertJsonStructure(['errors']);
        $errors = $response->json('errors');
        $this->assertTrue(
            isset($errors['nome']) || isset($errors['email']) || isset($errors['telefone']) || isset($errors['permissoes.0']),
            'Deve haver erro em pelo menos um campo obrigatório ou formato'
        );
    }

    /**
     * GET /api/usuarios - retorna apenas usuários das empresas do lojista
     */
    public function test_index_retorna_apenas_usuarios_das_empresas_do_lojista(): void
    {
        [$lojista, $empresaA] = $this->criarLojistaComEmpresa(true);
        $empresaB = $this->criarEmpresa();

        $usuarioEmpresaA = User::factory()->create(['tipo_cadastro' => 0]);
        $this->vincularUsuarioEmpresa($usuarioEmpresaA, $empresaA);

        $usuarioEmpresaB = User::factory()->create(['tipo_cadastro' => 0]);
        $this->vincularUsuarioEmpresa($usuarioEmpresaB, $empresaB);

        Sanctum::actingAs($lojista);

        $response = $this->getJson('/api/usuarios');

        $response->assertOk()
            ->assertJsonStructure([
                'usuarios',
                'paginacao',
            ]);

        $usuariosData = $response->json('usuarios.data') ?? $response->json('usuarios');
        $idsRetornados = collect($usuariosData)->pluck('id');
        $this->assertTrue($idsRetornados->contains($usuarioEmpresaA->id), 'Lista deve conter usuário da empresa A');
        $this->assertFalse($idsRetornados->contains($usuarioEmpresaB->id), 'Lista não deve conter usuário da empresa B');
    }

    /**
     * GET /api/usuarios/{id} - sucesso quando mesma empresa
     */
    public function test_show_usuario_mesma_empresa_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);

        $usuario = User::factory()->create(['tipo_cadastro' => 0]);
        $this->vincularUsuarioEmpresa($usuario, $empresa);

        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/usuarios/{$usuario->id}");

        $response->assertOk()
            ->assertJsonPath('usuario.id', $usuario->id);
    }

    /**
     * GET /api/usuarios/{id} - 403 quando usuários de empresas diferentes
     */
    public function test_show_usuario_de_outra_empresa_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();

        $usuarioOutraEmpresa = User::factory()->create(['tipo_cadastro' => 0]);
        $this->vincularUsuarioEmpresa($usuarioOutraEmpresa, $empresaOutra);

        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/usuarios/{$usuarioOutraEmpresa->id}");

        $response->assertStatus(403)
            ->assertJsonFragment([
                'error' => 'Você não tem permissão para visualizar este usuário.',
            ]);
    }

    /**
     * GET /api/usuarios/{id} - clientes não podem ver outros clientes
     */
    public function test_show_clientes_nao_podem_visualizar_outros_clientes(): void
    {
        $cliente1 = User::factory()->create(['tipo_cadastro' => 1]);
        $cliente2 = User::factory()->create(['tipo_cadastro' => 1]);

        Sanctum::actingAs($cliente1);

        $response = $this->getJson("/api/usuarios/{$cliente2->id}");

        $response->assertStatus(403)
            ->assertJsonFragment([
                'error' => 'Você não tem permissão para executar esta ação',
            ]);
    }

    /**
     * PUT /api/usuarios/{id} - atualização básica com sucesso
     */
    public function test_update_usuario_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);

        $usuario = User::factory()->create([
            'nome' => 'Antigo Nome',
            'email' => 'antigo@example.com',
            'telefone' => '11111111111',
            'tipo_cadastro' => 0,
        ]);
        $this->vincularUsuarioEmpresa($usuario, $empresa);

        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/usuarios/{$usuario->id}", [
            'nome' => 'Novo Nome',
            'email' => 'novo@example.com',
            'telefone' => '22222222222',
            'ativo' => false,
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'message' => 'Usuário atualizado com sucesso',
            ])
            ->assertJsonPath('usuario.nome', 'Novo Nome')
            ->assertJsonPath('usuario.email', 'novo@example.com');

        $this->assertDatabaseHas('usuarios', [
            'id' => $usuario->id,
            'nome' => 'Novo Nome',
            'email' => 'novo@example.com',
            'telefone' => '22222222222',
            'ativo' => false,
        ]);
    }

    /**
     * PUT /api/usuarios/{id} - não pode atualizar usuário de outra empresa
     */
    public function test_update_usuario_de_outra_empresa_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();

        $usuarioOutraEmpresa = User::factory()->create(['tipo_cadastro' => 0]);
        $this->vincularUsuarioEmpresa($usuarioOutraEmpresa, $empresaOutra);

        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/usuarios/{$usuarioOutraEmpresa->id}", [
            'nome' => 'Novo Nome',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'error' => 'Acesso negado',
            ]);
    }

    /**
     * PUT /api/usuarios/{id} - tentar alterar permissões de usuário master
     */
    public function test_update_nao_pode_alterar_permissoes_de_usuario_master(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);

        $usuarioMaster = User::factory()->create([
            'is_master' => true,
            'tipo_cadastro' => 0,
        ]);
        $this->vincularUsuarioEmpresa($usuarioMaster, $empresa);

        $permissao = $this->criarPermissaoDashboard();

        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/usuarios/{$usuarioMaster->id}", [
            'permissoes' => [$permissao->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Dados inválidos. Verifique os erros abaixo.',
            ])
            ->assertJsonPath('errors.permissoes.0', 'Não é possível alterar as permissões de um usuário master.');
    }

    /**
     * PUT /api/usuarios/{id} - 422 quando dados inválidos (nome vazio, email inválido, senha curta, permissoes inexistentes).
     */
    public function test_update_usuario_dados_invalidos_retorna_422(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $usuario = User::factory()->create(['tipo_cadastro' => 0]);
        $this->vincularUsuarioEmpresa($usuario, $empresa);
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/usuarios/{$usuario->id}", [
            'nome' => '',
            'email' => 'email-invalido',
            'password' => '123',
            'telefone' => '',
            'permissoes' => [99999],
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'success' => false,
                'message' => 'Dados inválidos. Verifique os erros abaixo.',
            ])
            ->assertJsonStructure(['errors']);
        $errors = $response->json('errors');
        $this->assertTrue(
            isset($errors['nome']) || isset($errors['email']) || isset($errors['password']) || isset($errors['telefone']) || isset($errors['permissoes.0']),
            'Deve haver erro em pelo menos um campo enviado com valor inválido'
        );
    }

    /**
     * PUT /api/usuarios/{id} - 422 quando email já usado por outro usuário do mesmo tipo_cadastro.
     */
    public function test_update_email_duplicado_retorna_422(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $outro = User::factory()->create([
            'email' => 'outro@example.com',
            'tipo_cadastro' => 0,
        ]);
        $this->vincularUsuarioEmpresa($outro, $empresa);

        $usuario = User::factory()->create([
            'email' => 'usuario@example.com',
            'tipo_cadastro' => 0,
        ]);
        $this->vincularUsuarioEmpresa($usuario, $empresa);
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/usuarios/{$usuario->id}", [
            'email' => 'outro@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false])
            ->assertJsonPath('errors.email.0', 'Este email já está sendo usado por outro usuário.');
    }

    /**
     * DELETE /api/usuarios/{id} - não pode deletar o próprio usuário
     */
    public function test_destroy_nao_pode_deletar_o_proprio_usuario(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        Sanctum::actingAs($lojista);

        $response = $this->deleteJson("/api/usuarios/{$lojista->id}");

        $response->assertStatus(403)
            ->assertJsonFragment([
                'error' => 'Não é possível deletar seu próprio usuário.',
            ]);
    }

    /**
     * DELETE /api/usuarios/{id} - não pode deletar usuário master
     */
    public function test_destroy_nao_pode_deletar_usuario_master(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);

        $usuarioMaster = User::factory()->create([
            'is_master' => true,
            'tipo_cadastro' => 0,
        ]);
        $this->vincularUsuarioEmpresa($usuarioMaster, $empresa);

        Sanctum::actingAs($lojista);

        $response = $this->deleteJson("/api/usuarios/{$usuarioMaster->id}");

        $response->assertStatus(403)
            ->assertJsonFragment([
                'error' => 'Não é possível deletar um usuário master.',
            ]);
    }

    /**
     * DELETE /api/usuarios/{id} - não pode deletar usuário de outra empresa
     */
    public function test_destroy_usuario_de_outra_empresa_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();

        $usuarioOutraEmpresa = User::factory()->create(['tipo_cadastro' => 0]);
        $this->vincularUsuarioEmpresa($usuarioOutraEmpresa, $empresaOutra);

        Sanctum::actingAs($lojista);

        $response = $this->deleteJson("/api/usuarios/{$usuarioOutraEmpresa->id}");

        $response->assertStatus(403)
            ->assertJsonFragment([
                'error' => 'Acesso negado',
            ]);
    }

    /**
     * DELETE /api/usuarios/{id} - soft delete com sucesso
     */
    public function test_destroy_usuario_sucesso_soft_delete(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);

        $usuario = User::factory()->create(['tipo_cadastro' => 0]);
        $this->vincularUsuarioEmpresa($usuario, $empresa);

        Sanctum::actingAs($lojista);

        $response = $this->deleteJson("/api/usuarios/{$usuario->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'message' => 'Usuário deletado com sucesso',
            ]);

        $this->assertDatabaseMissing('usuarios', ['id' => $usuario->id]);
    }

    /**
     * POST /api/change-password/send-code - sucesso
     */
    public function test_enviar_codigo_recuperacao_sucesso(): void
    {
        $usuario = User::factory()->create([
            'email' => 'cliente@example.com',
            'tipo_cadastro' => 1,
        ]);

        $response = $this->postJson('/api/change-password/send-code', [
            'email' => $usuario->email,
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'message' => 'Código de recuperação enviado para seu email',
            ]);

        $this->assertDatabaseHas('password_resets', [
            'email' => $usuario->email,
        ]);

        $reset = PasswordReset::where('email', $usuario->email)->first();
        $this->assertNotNull($reset);
        $this->assertEquals(6, strlen($reset->token));
        $this->assertTrue($reset->expires_at->isFuture());
        $this->assertNull($reset->used_at);
    }

    /**
     * POST /api/change-password/send-code - email inexistente
     */
    public function test_enviar_codigo_recuperacao_email_inexistente_retorna_422(): void
    {
        $response = $this->postJson('/api/change-password/send-code', [
            'email' => 'nao-existe@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => ['email'],
            ]);
    }

    /**
     * POST /api/change-password/verify-code - token inválido ou expirado
     */
    public function test_verificar_codigo_recuperacao_token_invalido_ou_expirado(): void
    {
        $usuario = User::factory()->create([
            'email' => 'cliente@example.com',
            'tipo_cadastro' => 1,
        ]);

        // token expirado
        PasswordReset::create([
            'email' => $usuario->email,
            'token' => '123456',
            'expires_at' => now()->subMinute(),
            'used_at' => null,
        ]);

        $response = $this->postJson('/api/change-password/verify-code', [
            'email' => $usuario->email,
            'token' => '123456',
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'error' => 'Código inválido ou expirado',
            ]);
    }

    /**
     * POST /api/change-password/verify-code - sucesso
     */
    public function test_verificar_codigo_recuperacao_sucesso(): void
    {
        $usuario = User::factory()->create([
            'email' => 'cliente@example.com',
            'tipo_cadastro' => 1,
        ]);

        PasswordReset::create([
            'email' => $usuario->email,
            'token' => '654321',
            'expires_at' => now()->addMinutes(10),
            'used_at' => null,
        ]);

        $response = $this->postJson('/api/change-password/verify-code', [
            'email' => $usuario->email,
            'token' => '654321',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'message' => 'Código verificado com sucesso',
            ])
            ->assertJsonPath('valid', true);
    }

    /**
     * POST /api/change-password - token inválido
     */
    public function test_alterar_senha_publico_token_invalido_retorna_400(): void
    {
        $usuario = User::factory()->create([
            'email' => 'cliente@example.com',
            'tipo_cadastro' => 1,
        ]);

        $response = $this->postJson('/api/change-password', [
            'email' => $usuario->email,
            'token' => '000000',
            'senha' => 'novaSenha123',
            'senha_confirmation' => 'novaSenha123',
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'error' => 'Código inválido ou expirado',
            ]);
    }

    /**
     * POST /api/change-password - usuário não encontrado
     */
    public function test_alterar_senha_publico_usuario_nao_encontrado_retorna_404(): void
    {
        PasswordReset::create([
            'email' => 'desconhecido@example.com',
            'token' => '123456',
            'expires_at' => now()->addMinutes(10),
            'used_at' => null,
        ]);

        $response = $this->postJson('/api/change-password', [
            'email' => 'desconhecido@example.com',
            'token' => '123456',
            'senha' => 'novaSenha123',
            'senha_confirmation' => 'novaSenha123',
        ]);

        $response->assertStatus(404)
            ->assertJsonFragment([
                'error' => 'Usuário não encontrado',
            ]);
    }

    /**
     * POST /api/change-password - sucesso
     */
    public function test_alterar_senha_publico_sucesso(): void
    {
        $usuario = User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => Hash::make('senhaAntiga'),
            'tipo_cadastro' => 1,
        ]);

        PasswordReset::create([
            'email' => $usuario->email,
            'token' => '123456',
            'expires_at' => now()->addMinutes(10),
            'used_at' => null,
        ]);

        $response = $this->postJson('/api/change-password', [
            'email' => $usuario->email,
            'token' => '123456',
            'senha' => 'novaSenha123',
            'senha_confirmation' => 'novaSenha123',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'message' => 'Senha alterada com sucesso',
            ]);

        $usuario->refresh();
        $this->assertTrue(Hash::check('novaSenha123', $usuario->password));

        $reset = PasswordReset::where('email', $usuario->email)->where('token', '123456')->first();
        $this->assertNotNull($reset->used_at);
    }

    /**
     * PUT /api/usuarios/alterar-senha-primeiro-login - sucesso
     */
    public function test_alterar_senha_primeiro_login_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);

        $funcionario = User::factory()->create([
            'primeiro_login' => true,
            'tipo_cadastro' => 0,
        ]);
        $this->vincularUsuarioEmpresa($funcionario, $empresa);

        Sanctum::actingAs($funcionario);

        $response = $this->putJson('/api/usuarios/alterar-senha-primeiro-login', [
            'senha' => 'novaSenha123',
            'senha_confirmation' => 'novaSenha123',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'success' => true,
                'message' => 'Senha alterada com sucesso',
            ]);

        $funcionario->refresh();
        $this->assertFalse($funcionario->primeiro_login);
        $this->assertTrue(Hash::check('novaSenha123', $funcionario->password));
    }

    /**
     * PUT /api/usuarios/alterar-senha-primeiro-login - não é primeiro login
     */
    public function test_alterar_senha_primeiro_login_quando_nao_primeiro_login_retorna_403(): void
    {
        $usuario = User::factory()->create([
            'primeiro_login' => false,
            'tipo_cadastro' => 0,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->putJson('/api/usuarios/alterar-senha-primeiro-login', [
            'senha' => 'novaSenha123',
            'senha_confirmation' => 'novaSenha123',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Esta ação é válida apenas no primeiro acesso.',
            ]);
    }

    /**
     * PUT /api/usuarios/alterar-senha-primeiro-login - validação da senha
     */
    public function test_alterar_senha_primeiro_login_validacao_retorna_422(): void
    {
        $usuario = User::factory()->create([
            'primeiro_login' => true,
            'tipo_cadastro' => 0,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->putJson('/api/usuarios/alterar-senha-primeiro-login', [
            'senha' => 'curta',
            'senha_confirmation' => 'diferente',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => ['senha'],
            ]);
    }
}

