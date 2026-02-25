<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\EmpresaAvaliacao;
use App\Models\Pedido;
use App\Models\StatusPedidos;
use App\Models\User;
use App\Models\UsuarioEmpresas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmpresaAvaliacaoControllerTest extends TestCase
{
    use RefreshDatabase;

    private function criarEmpresa(): Empresa
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
            'razao_social' => 'Empresa LTDA ' . uniqid(),
            'nome_fantasia' => 'Teste',
            'slug' => 'teste-' . uniqid(),
            'email' => 'e@example.com',
            'telefone' => '34999999999',
            'cpf_cnpj' => '123456780001' . rand(60, 99),
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
     * GET /api/avaliacoes - index sucesso (lojista com empresa)
     */
    public function test_index_sucesso(): void
    {
        $empresa = $this->criarEmpresa();
        $lojista = User::factory()->create(['tipo_cadastro' => 0, 'is_master' => true]);
        $this->vincularUsuarioEmpresa($lojista, $empresa);
        Sanctum::actingAs($lojista);

        $response = $this->getJson('/api/avaliacoes');

        $response->assertOk()
            ->assertJsonStructure(['avaliacoes', 'paginacao']);
    }

    /**
     * GET /api/avaliacoes/empresa/{empresaId} - rota pública
     */
    public function test_avaliacoes_por_empresa_sucesso(): void
    {
        $empresa = $this->criarEmpresa();
        $response = $this->getJson("/api/avaliacoes/empresa/{$empresa->id}");

        $response->assertOk()
            ->assertJsonFragment(['empresa_id' => (string) $empresa->id])
            ->assertJsonStructure(['estatisticas', 'avaliacoes', 'paginacao']);
    }

    /**
     * GET /api/avaliacoes/{id} - show 403 quando avaliação de outra empresa
     */
    public function test_show_avaliacao_outra_empresa_retorna_403(): void
    {
        $empresaA = $this->criarEmpresa();
        $empresaB = $this->criarEmpresa();
        $lojista = User::factory()->create(['tipo_cadastro' => 0, 'is_master' => true]);
        $cliente = User::factory()->create(['tipo_cadastro' => 1]);
        $this->vincularUsuarioEmpresa($lojista, $empresaA);
        $statusId = DB::table('status_pedidos')->insertGetId([
            'nome' => 'Entregue',
            'slug' => 'entregue',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $pagamentoId = DB::table('formas_pagamentos')->insertGetId([
            'nome' => 'PIX',
            'slug' => 'pix',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $pedidoB = Pedido::create([
            'usuario_id' => $cliente->id,
            'empresa_id' => $empresaB->id,
            'status_pedido_id' => $statusId,
            'pagamento_id' => $pagamentoId,
            'subtotal' => 50,
            'total' => 50,
            'ativo' => true,
            'foi_entrega' => false,
        ]);
        $avaliacao = EmpresaAvaliacao::create([
            'empresa_id' => $empresaB->id,
            'usuario_id' => $cliente->id,
            'pedido_id' => $pedidoB->id,
            'nota' => 5.0,
        ]);
        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/avaliacoes/{$avaliacao->id}");

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }

    /**
     * POST /api/avaliacoes - 422 quando pedido não está entregue (validação)
     */
    public function test_store_pedido_nao_entregue_retorna_422(): void
    {
        $statusId = DB::table('status_pedidos')->insertGetId([
            'nome' => 'Pendente',
            'slug' => 'pendente',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $pagamentoId = DB::table('formas_pagamentos')->insertGetId([
            'nome' => 'PIX',
            'slug' => 'pix',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cliente = User::factory()->create(['tipo_cadastro' => 1]);
        $empresa = $this->criarEmpresa();
        $pedido = Pedido::create([
            'usuario_id' => $cliente->id,
            'empresa_id' => $empresa->id,
            'status_pedido_id' => $statusId,
            'pagamento_id' => $pagamentoId,
            'subtotal' => 50,
            'total' => 50,
            'ativo' => true,
            'foi_entrega' => false,
        ]);
        Sanctum::actingAs($cliente);

        $response = $this->postJson('/api/avaliacoes', [
            'pedido_id' => $pedido->id,
            'nota' => 5.0,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false])
            ->assertJsonPath('errors.pedido_id.0', 'Pedido deve estar entregue para ser avaliado');
    }
}
