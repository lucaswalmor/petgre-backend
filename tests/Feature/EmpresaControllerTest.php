<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\EmpresaEndereco;
use App\Models\Permissao;
use App\Models\User;
use App\Models\UsuarioEmpresas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmpresaControllerTest extends TestCase
{
    use RefreshDatabase;

    private static int $cpfCnpjSufixo = 10;

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
            'cpf_cnpj' => '123456780001' . (self::$cpfCnpjSufixo++),
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

    private function criarLojistaComEmpresa(bool $master = true): array
    {
        $empresa = $this->criarEmpresa();
        $usuario = User::factory()->create([
            'is_master' => $master,
            'tipo_cadastro' => 0,
        ]);
        $this->vincularUsuarioEmpresa($usuario, $empresa);
        return [$usuario, $empresa];
    }

    private function criarPermissao(): Permissao
    {
        return Permissao::unguarded(function () {
            return Permissao::create([
                'nome' => 'Dashboard',
                'slug' => 'dashboard.index',
                'ativo' => true,
            ]);
        });
    }

    /**
     * Payload válido para POST /api/empresa (campos conforme Postman e EmpresaStoreRequest).
     */
    private function payloadStoreEmpresaValido(int $nichoId, int $permissaoId, string $emailAdmin = null): array
    {
        $emailAdmin = $emailAdmin ?? 'admin' . uniqid() . '@example.com';
        $sufixo = substr(uniqid(), -4);
        return [
            'tipo_pessoa' => 0,
            'razao_social' => 'Empresa Teste LTDA ' . $sufixo,
            'nome_fantasia' => 'Empresa Teste',
            'email' => 'empresa' . $sufixo . '@example.com',
            'telefone' => '(34) 99999-9999',
            'cpf_cnpj' => '12.345.678/0001-90',
            'nicho_id' => $nichoId,
            'ativo' => true,
            'endereco' => [
                'logradouro' => 'Rua das Flores',
                'numero' => '123',
                'complemento' => 'Sala 2',
                'bairro' => 'Centro',
                'cidade' => 'Uberlândia',
                'estado' => 'MG',
                'cep' => '38400-000',
                'ponto_referencia' => null,
                'observacoes' => null,
            ],
            'usuario_admin' => [
                'nome' => 'Admin Teste',
                'email' => $emailAdmin,
                'password' => 'senhaSegura123',
                'password_confirmation' => 'senhaSegura123',
                'permissoes' => [$permissaoId],
                'telefone' => '(34) 99202-1394',
            ],
        ];
    }

    /**
     * POST /api/empresa - criação com sucesso (campos conforme Postman).
     */
    public function test_store_empresa_sucesso(): void
    {
        $nichoId = $this->criarNichoEmpresa();
        $permissao = $this->criarPermissao();
        $payload = $this->payloadStoreEmpresaValido($nichoId, $permissao->id);

        $response = $this->postJson('/api/empresa', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true, 'message' => 'Empresa criada com sucesso'])
            ->assertJsonStructure(['empresa', 'usuario']);
        $this->assertDatabaseHas('empresas', ['razao_social' => $payload['razao_social']]);
        $this->assertDatabaseHas('usuarios', ['email' => $payload['usuario_admin']['email'], 'tipo_cadastro' => 0]);
    }

    /**
     * POST /api/empresa - 422 quando dados inválidos (campos obrigatórios faltando, formato errado).
     */
    public function test_store_empresa_dados_invalidos_retorna_422(): void
    {
        $nichoId = $this->criarNichoEmpresa();
        $permissao = $this->criarPermissao();

        $response = $this->postJson('/api/empresa', [
            'tipo_pessoa' => 0,
            'razao_social' => 'Teste',
            'email' => 'email-invalido',
            'telefone' => '',
            'cpf_cnpj' => '123', // formato CNPJ inválido
            'nicho_id' => $nichoId,
            'endereco' => [
                'logradouro' => '',
                'numero' => '',
            ],
            'usuario_admin' => [
                'nome' => '',
                'email' => 'admin-invalido',
                'password' => '123', // min 8
                'password_confirmation' => 'diferente',
                'permissoes' => [$permissao->id],
                'telefone' => '',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false, 'message' => 'Dados inválidos. Verifique os erros abaixo.'])
            ->assertJsonStructure(['errors']);
        $errors = $response->json('errors');
        $this->assertTrue(
            isset($errors['email']) || isset($errors['cpf_cnpj']) || isset($errors['usuario_admin.password']) || isset($errors['endereco.logradouro']),
            'Deve haver erro em pelo menos um campo obrigatório ou formato'
        );
    }

    /**
     * POST /api/empresa - 422 quando razao_social ou cpf_cnpj já existem.
     */
    public function test_store_empresa_razao_social_ou_cpf_cnpj_duplicado_retorna_422(): void
    {
        $nichoId = $this->criarNichoEmpresa();
        $permissao = $this->criarPermissao();
        $payload = $this->payloadStoreEmpresaValido($nichoId, $permissao->id);
        $this->postJson('/api/empresa', $payload);

        $payload['usuario_admin']['email'] = 'outro' . uniqid() . '@example.com';
        $response = $this->postJson('/api/empresa', $payload);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false])
            ->assertJsonStructure(['errors']);
        $errors = $response->json('errors');
        $this->assertTrue(isset($errors['razao_social']) || isset($errors['cpf_cnpj']));
    }

    /**
     * GET /api/empresa/{id} - sucesso quando empresa pertence ao usuário
     */
    public function test_show_empresa_pertence_ao_usuario_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/empresa/{$empresa->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('empresa.id', $empresa->id);
    }

    /**
     * GET /api/empresa/{id}?basic=true - retorna dados básicos
     */
    public function test_show_com_basic_true_retorna_dados_basicos(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/empresa/{$empresa->id}?basic=true");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('empresa.id', $empresa->id)
            ->assertJsonPath('empresa.razao_social', $empresa->razao_social)
            ->assertJsonPath('empresa.nome_fantasia', $empresa->nome_fantasia);
    }

    /**
     * GET /api/empresa/{id} - 403 quando empresa não pertence ao usuário
     */
    public function test_show_empresa_nao_pertence_ao_usuario_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa();
        $empresaOutra = $this->criarEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/empresa/{$empresaOutra->id}");

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }

    /**
     * GET /api/empresa/{id} - 403 quando ID não pertence ao usuário (não revela se existe ou não)
     */
    public function test_show_empresa_id_inexistente_ou_alheio_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->getJson('/api/empresa/99999');

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }

    /**
     * PUT /api/empresa/{id} - atualização com sucesso
     */
    public function test_update_empresa_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/empresa/{$empresa->id}", [
            'nome_fantasia' => 'Novo Nome Fantasia',
            'telefone' => '34988887777',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'success' => true,
                'message' => 'Empresa atualizada com sucesso',
            ]);
        $empresa->refresh();
        $this->assertEquals('Novo Nome Fantasia', $empresa->nome_fantasia);
        $this->assertEquals('34988887777', $empresa->telefone);
    }

    /**
     * PUT /api/empresa/{id} - 422 quando dados inválidos (ex: email inválido)
     */
    public function test_update_dados_invalidos_retorna_422(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/empresa/{$empresa->id}", [
            'email' => 'email-invalido',
            'ativo' => 'nao-e-boolean',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
        $errors = $response->json('errors');
        $this->assertTrue(isset($errors['email']) || isset($errors['ativo']));
    }

    /**
     * PUT /api/empresa/{id} - 403 quando empresa não pertence ao usuário
     */
    public function test_update_empresa_nao_pertence_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa();
        $empresaOutra = $this->criarEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/empresa/{$empresaOutra->id}", [
            'nome_fantasia' => 'Tentativa',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }

    /**
     * POST /api/empresa/{id}/upload-image - 403 quando empresa não pertence ao usuário
     */
    public function test_upload_image_empresa_nao_pertence_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa();
        $empresaOutra = $this->criarEmpresa();
        Sanctum::actingAs($lojista);

        $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
        $response = $this->postJson("/api/empresa/{$empresaOutra->id}/upload-image?tipo=logo", [
            'logo' => $file,
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }

    /**
     * POST /api/empresa/{id}/upload-image?tipo=logo - 422 quando tipo=logo mas não envia arquivo
     */
    public function test_upload_image_tipo_logo_sem_arquivo_retorna_422(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->postJson("/api/empresa/{$empresa->id}/upload-image?tipo=logo", []);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false])
            ->assertJsonStructure(['errors']);
    }

    /**
     * POST /api/empresa/{id}/upload-image?tipo=logo - 422 quando envia arquivo que não é imagem
     */
    public function test_upload_image_arquivo_nao_imagem_retorna_422(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa();
        Sanctum::actingAs($lojista);

        $file = UploadedFile::fake()->create('documento.txt', 100, 'text/plain');
        $response = $this->post("/api/empresa/{$empresa->id}/upload-image?tipo=logo", [
            'logo' => $file,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false])
            ->assertJsonStructure(['errors']);
    }

    /**
     * POST /api/empresa/{id}/upload-image - 400 quando nenhuma imagem enviada
     */
    public function test_upload_image_nenhuma_imagem_retorna_400(): void
    {
        Storage::fake('r2');
        [$lojista, $empresa] = $this->criarLojistaComEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->postJson("/api/empresa/{$empresa->id}/upload-image");

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Nenhuma imagem foi enviada']);
    }

    /**
     * POST /api/empresa/{id}/upload-image?tipo=logo - sucesso
     */
    public function test_upload_image_logo_sucesso(): void
    {
        Storage::fake('r2');
        [$lojista, $empresa] = $this->criarLojistaComEmpresa();
        Sanctum::actingAs($lojista);

        $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
        $response = $this->post("/api/empresa/{$empresa->id}/upload-image?tipo=logo", [
            'logo' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'success' => true,
                'message' => 'Imagem(ns) atualizada(s) com sucesso',
            ]);
        $empresa->refresh();
        $this->assertNotNull($empresa->path_logo);
    }

    /**
     * POST /api/empresa/{id}/upload-image?tipo=banner - sucesso
     */
    public function test_upload_image_banner_sucesso(): void
    {
        Storage::fake('r2');
        [$lojista, $empresa] = $this->criarLojistaComEmpresa();
        Sanctum::actingAs($lojista);

        $file = UploadedFile::fake()->image('banner.jpg', 200, 100);
        $response = $this->post("/api/empresa/{$empresa->id}/upload-image?tipo=banner", [
            'banner' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'success' => true,
                'message' => 'Imagem(ns) atualizada(s) com sucesso',
            ]);
        $empresa->refresh();
        $this->assertNotNull($empresa->path_banner);
    }

    /**
     * GET /api/empresa/{id}/status - sucesso
     */
    public function test_status_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/empresa/{$empresa->id}/status");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['empresa_aberta', 'fechado_ate', 'fechada_manual']);
    }

    /**
     * GET /api/empresa/{id}/status - 403 quando empresa não pertence
     */
    public function test_status_empresa_nao_pertence_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa();
        $empresaOutra = $this->criarEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/empresa/{$empresaOutra->id}/status");

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Acesso negado.']);
    }

    /**
     * PUT /api/empresa/{id}/status-manual - sucesso
     */
    public function test_status_manual_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/empresa/{$empresa->id}/status-manual", [
            'fechada_manual' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('fechada_manual', true);
        $empresa->refresh();
        $this->assertTrue((bool) $empresa->fechada_manual);
    }

    /**
     * PUT /api/empresa/{id}/status-manual - 422 quando fechada_manual ausente ou inválido
     */
    public function test_status_manual_fechada_manual_obrigatorio_retorna_422(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/empresa/{$empresa->id}/status-manual", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fechada_manual']);
    }

    /**
     * PUT /api/empresa/{id}/status-manual - 403 quando empresa não pertence
     */
    public function test_status_manual_empresa_nao_pertence_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa();
        $empresaOutra = $this->criarEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/empresa/{$empresaOutra->id}/status-manual", [
            'fechada_manual' => true,
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Acesso negado.']);
    }

    /**
     * GET /api/empresa/{id}/verificar-cadastro - sucesso
     */
    public function test_verificar_cadastro_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/empresa/{$empresa->id}/verificar-cadastro");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['cadastro_completo', 'percentual', 'itens_pendentes', 'empresa_id', 'empresa_nome']);
    }

    /**
     * GET /api/empresa/{id}/verificar-cadastro - 403 quando empresa não pertence
     */
    public function test_verificar_cadastro_empresa_nao_pertence_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa();
        $empresaOutra = $this->criarEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/empresa/{$empresaOutra->id}/verificar-cadastro");

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }

    /**
     * GET /api/empresa/{empresaId}/bairros-disponiveis - sucesso quando empresa tem endereço
     */
    public function test_bairros_disponiveis_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa();
        EmpresaEndereco::create([
            'empresa_id' => $empresa->id,
            'logradouro' => 'Rua Teste',
            'numero' => '100',
            'bairro' => 'Centro',
            'cidade' => 'Uberlândia',
            'estado' => 'MG',
            'cep' => '38400-000',
        ]);
        DB::table('bairros')->insert([
            'nome' => 'Centro',
            'slug' => 'centro-uberlandia-' . uniqid(),
            'cidade' => 'Uberlândia',
            'estado' => 'MG',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/empresa/{$empresa->id}/bairros-disponiveis");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['bairros']);
    }

    /**
     * GET /api/empresa/{empresaId}/bairros-disponiveis - 400 quando empresa sem endereço
     */
    public function test_bairros_disponiveis_empresa_sem_endereco_retorna_400(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/empresa/{$empresa->id}/bairros-disponiveis");

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Empresa não possui endereço cadastrado']);
    }

    /**
     * GET /api/empresa/{empresaId}/bairros-disponiveis - 403 quando empresa não pertence
     */
    public function test_bairros_disponiveis_empresa_nao_pertence_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa();
        $empresaOutra = $this->criarEmpresa();
        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/empresa/{$empresaOutra->id}/bairros-disponiveis");

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }
}
