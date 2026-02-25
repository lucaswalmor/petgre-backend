<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UsuarioEnderecos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsuarioEnderecosControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * GET /api/enderecos - lista endereços do usuário
     */
    public function test_index_sucesso(): void
    {
        $user = User::factory()->create(['tipo_cadastro' => 1]);
        UsuarioEnderecos::create([
            'usuario_id' => $user->id,
            'rua' => 'Rua A',
            'numero' => '100',
            'ativo' => true,
            'endereco_padrao' => true,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/enderecos');

        $response->assertOk()
            ->assertJsonFragment(['success' => true])
            ->assertJsonStructure(['enderecos']);
    }

    /**
     * POST /api/enderecos - criar endereço com sucesso
     */
    public function test_store_sucesso(): void
    {
        $user = User::factory()->create(['tipo_cadastro' => 1]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/enderecos', [
            'rua' => 'Rua Nova',
            'numero' => '200',
            'bairro' => 'Centro',
            'cidade' => 'Uberlândia',
            'estado' => 'MG',
            'endereco_padrao' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true, 'message' => 'Endereço cadastrado com sucesso'])
            ->assertJsonStructure(['endereco']);
        $this->assertDatabaseHas('usuarios_enderecos', ['usuario_id' => $user->id, 'rua' => 'Rua Nova']);
    }

    /**
     * POST /api/enderecos - 422 quando rua/numero faltando
     */
    public function test_store_dados_invalidos_retorna_422(): void
    {
        $user = User::factory()->create(['tipo_cadastro' => 1]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/enderecos', [
            'rua' => '',
            'numero' => '',
        ]);

        $response->assertStatus(422);
    }

    /**
     * PUT /api/enderecos/{id} - atualizar endereço
     */
    public function test_update_sucesso(): void
    {
        $user = User::factory()->create(['tipo_cadastro' => 1]);
        $endereco = UsuarioEnderecos::create([
            'usuario_id' => $user->id,
            'rua' => 'Rua Antiga',
            'numero' => '1',
            'ativo' => true,
            'endereco_padrao' => false,
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/enderecos/{$endereco->id}", [
            'rua' => 'Rua Atualizada',
            'numero' => '2',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Endereço atualizado com sucesso']);
        $endereco->refresh();
        $this->assertEquals('Rua Atualizada', $endereco->rua);
    }

    /**
     * PUT /api/enderecos/{id}/padrao - definir como padrão
     */
    public function test_set_padrao_sucesso(): void
    {
        $user = User::factory()->create(['tipo_cadastro' => 1]);
        $endereco = UsuarioEnderecos::create([
            'usuario_id' => $user->id,
            'rua' => 'Rua X',
            'numero' => '10',
            'ativo' => true,
            'endereco_padrao' => false,
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/enderecos/{$endereco->id}/padrao");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Endereço padrão definido com sucesso']);
        $endereco->refresh();
        $this->assertTrue((bool) $endereco->endereco_padrao);
    }

    /**
     * DELETE /api/enderecos/{id} - desativa endereço
     */
    public function test_destroy_sucesso(): void
    {
        $user = User::factory()->create(['tipo_cadastro' => 1]);
        $endereco = UsuarioEnderecos::create([
            'usuario_id' => $user->id,
            'rua' => 'Rua Y',
            'numero' => '20',
            'ativo' => true,
            'endereco_padrao' => false,
        ]);
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/enderecos/{$endereco->id}");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Endereço removido com sucesso']);
        $endereco->refresh();
        $this->assertFalse((bool) $endereco->ativo);
    }
}
