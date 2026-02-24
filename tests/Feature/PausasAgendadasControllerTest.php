<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\EmpresaPausaAgendada;
use App\Models\User;
use App\Models\UsuarioEmpresas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PausasAgendadasControllerTest extends TestCase
{
    use RefreshDatabase;

    private static int $cpfCnpjSufixo = 20;

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

    private function criarPausaAgendada(int $empresaId, array $overrides = []): EmpresaPausaAgendada
    {
        $inicio = $overrides['data_inicio'] ?? now()->addDays(1);
        $fim = $overrides['data_fim'] ?? now()->addDays(1)->addHours(2);
        return EmpresaPausaAgendada::create(array_merge([
            'empresa_id' => $empresaId,
            'data_inicio' => $inicio,
            'data_fim' => $fim,
            'motivo' => 'Recesso',
            'recorrente' => false,
        ], $overrides));
    }

    /**
     * Payload válido para POST /api/pausas-agendadas (PausaAgendadaStoreRequest).
     */
    private function payloadStorePausaValido(int $empresaId, array $overrides = []): array
    {
        $inicio = $overrides['data_inicio'] ?? now()->addDays(1)->format('Y-m-d H:i:s');
        $fim = $overrides['data_fim'] ?? now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s');
        return array_merge([
            'empresa_id' => $empresaId,
            'data_inicio' => $inicio,
            'data_fim' => $fim,
            'motivo' => 'Férias',
            'recorrente' => false,
        ], $overrides);
    }

    /**
     * GET /api/pausas-agendadas - sucesso com empresa_id
     */
    public function test_index_sucesso_com_empresa_id(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $this->criarPausaAgendada($empresa->id);
        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/pausas-agendadas?empresa_id={$empresa->id}");

        $response->assertOk()
            ->assertJsonFragment(['success' => true])
            ->assertJsonStructure(['pausas']);
        $this->assertCount(1, $response->json('pausas'));
    }

    /**
     * GET /api/pausas-agendadas - sem empresa_id usa primeira empresa do usuário
     */
    public function test_index_sem_empresa_id_usa_primeira_empresa(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $this->criarPausaAgendada($empresa->id);
        Sanctum::actingAs($lojista);

        $response = $this->getJson('/api/pausas-agendadas');

        $response->assertOk()
            ->assertJsonFragment(['success' => true])
            ->assertJsonStructure(['pausas']);
        $this->assertCount(1, $response->json('pausas'));
    }

    /**
     * GET /api/pausas-agendadas - empresa_id não do usuário faz fallback para primeira empresa
     */
    public function test_index_empresa_invalida_faz_fallback(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();
        $this->criarPausaAgendada($empresa->id);
        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/pausas-agendadas?empresa_id={$empresaOutra->id}");

        $response->assertOk()
            ->assertJsonFragment(['success' => true]);
        $this->assertCount(1, $response->json('pausas'));
    }

    /**
     * GET /api/pausas-agendadas - usuário sem empresa retorna lista vazia
     */
    public function test_index_usuario_sem_empresa_retorna_vazio(): void
    {
        $usuario = User::factory()->create(['is_master' => true, 'tipo_cadastro' => 0]);
        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/pausas-agendadas');

        $response->assertOk()
            ->assertJsonFragment(['success' => true, 'pausas' => []]);
    }

    /**
     * POST /api/pausas-agendadas - criação com sucesso
     */
    public function test_store_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $payload = $this->payloadStorePausaValido($empresa->id);
        Sanctum::actingAs($lojista);

        $response = $this->postJson('/api/pausas-agendadas', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true])
            ->assertJsonStructure(['pausa']);
        $this->assertDatabaseHas('empresa_pausas_agendadas', [
            'empresa_id' => $empresa->id,
            'motivo' => 'Férias',
        ]);
    }

    /**
     * POST /api/pausas-agendadas - 422 quando dados inválidos (datas faltando, data_fim antes de data_inicio)
     */
    public function test_store_dados_invalidos_retorna_422(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        Sanctum::actingAs($lojista);

        $response = $this->postJson('/api/pausas-agendadas', [
            'empresa_id' => $empresa->id,
            'data_inicio' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'data_fim' => now()->addDays(1)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false])
            ->assertJsonStructure(['errors']);
        $this->assertArrayHasKey('data_fim', $response->json('errors'));
    }

    /**
     * POST /api/pausas-agendadas - 422 quando empresa_id não pertence ao usuário
     */
    public function test_store_empresa_nao_autorizada_retorna_422(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();
        $payload = $this->payloadStorePausaValido($empresaOutra->id);
        Sanctum::actingAs($lojista);

        $response = $this->postJson('/api/pausas-agendadas', $payload);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false])
            ->assertJsonPath('errors.empresa_id.0', 'Empresa não autorizada.');
    }

    /**
     * PUT /api/pausas-agendadas/{id} - atualização com sucesso
     */
    public function test_update_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $pausa = $this->criarPausaAgendada($empresa->id);
        $novoInicio = now()->addDays(3)->format('Y-m-d H:i:s');
        $novoFim = now()->addDays(3)->addHours(3)->format('Y-m-d H:i:s');
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/pausas-agendadas/{$pausa->id}", [
            'data_inicio' => $novoInicio,
            'data_fim' => $novoFim,
            'motivo' => 'Recesso atualizado',
            'recorrente' => true,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['success' => true])
            ->assertJsonPath('pausa.motivo', 'Recesso atualizado');
        $pausa->refresh();
        $this->assertEquals('Recesso atualizado', $pausa->motivo);
        $this->assertTrue($pausa->recorrente);
    }

    /**
     * PUT /api/pausas-agendadas/{id} - 422 quando dados inválidos
     */
    public function test_update_dados_invalidos_retorna_422(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $pausa = $this->criarPausaAgendada($empresa->id);
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/pausas-agendadas/{$pausa->id}", [
            'data_inicio' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'data_fim' => now()->addDays(4)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false])
            ->assertJsonStructure(['errors']);
    }

    /**
     * PUT /api/pausas-agendadas/{id} - 403 quando pausa de outra empresa
     */
    public function test_update_pausa_outra_empresa_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();
        $pausa = $this->criarPausaAgendada($empresaOutra->id);
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/pausas-agendadas/{$pausa->id}", [
            'data_inicio' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'data_fim' => now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s'),
            'motivo' => 'Tentativa',
            'recorrente' => false,
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }

    /**
     * DELETE /api/pausas-agendadas/{id} - sucesso
     */
    public function test_destroy_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $pausa = $this->criarPausaAgendada($empresa->id);
        Sanctum::actingAs($lojista);

        $response = $this->deleteJson("/api/pausas-agendadas/{$pausa->id}");

        $response->assertOk()
            ->assertJsonFragment(['success' => true]);
        $this->assertDatabaseMissing('empresa_pausas_agendadas', ['id' => $pausa->id]);
    }

    /**
     * DELETE /api/pausas-agendadas/{id} - 403 quando pausa de outra empresa
     */
    public function test_destroy_pausa_outra_empresa_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();
        $pausa = $this->criarPausaAgendada($empresaOutra->id);
        Sanctum::actingAs($lojista);

        $response = $this->deleteJson("/api/pausas-agendadas/{$pausa->id}");

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }
}
