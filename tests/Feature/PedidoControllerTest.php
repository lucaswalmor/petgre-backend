<?php

namespace Tests\Feature;

use App\Models\Categorias;
use App\Models\Empresa;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\StatusPedidos;
use App\Models\UnidadeMedida;
use App\Models\User;
use App\Models\UsuarioEmpresas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PedidoControllerTest extends TestCase
{
    use RefreshDatabase;

    private static int $cpfCnpjSufixo = 30;

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

    private function criarCliente(): User
    {
        return User::factory()->create([
            'tipo_cadastro' => 1,
        ]);
    }

    private function criarStatusPendente(): int
    {
        $id = DB::table('status_pedidos')->insertGetId([
            'nome' => 'Pendente',
            'slug' => 'pendente',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return (int) $id;
    }

    private function criarFormaPagamento(): int
    {
        $id = DB::table('formas_pagamentos')->insertGetId([
            'nome' => 'PIX',
            'slug' => 'pix',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return (int) $id;
    }

    private function criarCategoria(): Categorias
    {
        return Categorias::create([
            'nome' => 'Rações',
            'slug' => 'racao-' . uniqid(),
            'ativo' => true,
        ]);
    }

    private function criarUnidadeMedida(): UnidadeMedida
    {
        $id = DB::table('unidades_medidas')->insertGetId([
            'nome' => 'Unidade',
            'sigla' => 'UN',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return UnidadeMedida::findOrFail($id);
    }

    private function criarProduto(int $empresaId, float $preco = 29.90, int $estoque = 100): Produto
    {
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $nome = 'Produto Pedido ' . uniqid();
        return Produto::create([
            'empresa_id' => $empresaId,
            'categoria_id' => $categoria->id,
            'unidade_medida_id' => $unidade->id,
            'tipo' => 'produto',
            'nome' => $nome,
            'slug' => \Illuminate\Support\Str::slug($nome),
            'preco' => $preco,
            'estoque' => $estoque,
            'destaque' => false,
            'ativo' => true,
        ]);
    }

    private function criarPedido(int $usuarioId, int $empresaId, int $statusId, int $pagamentoId, bool $pendente = true): Pedido
    {
        $pedido = Pedido::create([
            'usuario_id' => $usuarioId,
            'empresa_id' => $empresaId,
            'status_pedido_id' => $statusId,
            'pagamento_id' => $pagamentoId,
            'subtotal' => 59.90,
            'desconto' => 0,
            'frete' => 0,
            'total' => 59.90,
            'ativo' => true,
            'foi_entrega' => false,
        ]);
        DB::table('pedido_historico_status')->insert([
            'pedido_id' => $pedido->id,
            'status_pedido_id' => $statusId,
            'observacoes' => 'Pedido criado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $pedido;
    }

    /**
     * GET /api/pedidos/estatisticas - sucesso
     */
    public function test_estatisticas_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        Sanctum::actingAs($lojista);

        $response = $this->getJson('/api/pedidos/estatisticas');

        $response->assertOk()
            ->assertJsonFragment(['success' => true])
            ->assertJsonStructure(['estatisticas' => ['pedidos_hoje', 'faturamento_mes', 'pedidos_pendentes', 'avaliacao_media']]);
    }

    /**
     * GET /api/pedidos - lista pedidos da empresa
     */
    public function test_index_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $statusId = $this->criarStatusPendente();
        $pagamentoId = $this->criarFormaPagamento();
        $cliente = $this->criarCliente();
        $pedido = $this->criarPedido($cliente->id, $empresa->id, $statusId, $pagamentoId);
        Sanctum::actingAs($lojista);

        $response = $this->getJson('/api/pedidos');

        $response->assertOk();
        $data = $response->json();
        $this->assertTrue(isset($data['data']) || isset($data['pedido']) || isset($data['pedidos']));
    }

    /**
     * GET /api/pedidos - filtro por empresa_id
     */
    public function test_index_filtro_empresa_id(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $statusId = $this->criarStatusPendente();
        $pagamentoId = $this->criarFormaPagamento();
        $cliente = $this->criarCliente();
        $this->criarPedido($cliente->id, $empresa->id, $statusId, $pagamentoId);
        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/pedidos?empresa_id={$empresa->id}");

        $response->assertOk();
    }

    /**
     * POST /api/pedidos - criação com sucesso (retirada, sem endereço)
     */
    public function test_store_sucesso(): void
    {
        $this->criarStatusPendente();
        $pagamentoId = $this->criarFormaPagamento();
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $produto = $this->criarProduto($empresa->id, 39.90, 10);
        $cliente = $this->criarCliente();
        Sanctum::actingAs($cliente);

        $payload = [
            'empresa_id' => $empresa->id,
            'pagamento_id' => $pagamentoId,
            'subtotal' => 39.90,
            'desconto' => 0,
            'frete' => 0,
            'total' => 39.90,
            'observacoes' => null,
            'foi_entrega' => false,
            'itens' => [
                [
                    'produto_id' => $produto->id,
                    'quantidade' => 1,
                    'preco_unitario' => 39.90,
                    'subtotal' => 39.90,
                    'observacoes' => null,
                ],
            ],
        ];

        $response = $this->postJson('/api/pedidos', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true, 'message' => 'Pedido criado com sucesso'])
            ->assertJsonStructure(['pedido']);
        $this->assertDatabaseHas('pedidos', ['empresa_id' => $empresa->id, 'usuario_id' => $cliente->id]);
        $pedidoId = $response->json('pedido.id');
        $this->assertDatabaseHas('pedido_items', ['pedido_id' => $pedidoId, 'produto_id' => $produto->id]);
    }

    /**
     * POST /api/pedidos - 422 quando dados inválidos (itens vazios, campos obrigatórios faltando)
     */
    public function test_store_dados_invalidos_retorna_422(): void
    {
        $pagamentoId = $this->criarFormaPagamento();
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $cliente = $this->criarCliente();
        Sanctum::actingAs($cliente);

        $response = $this->postJson('/api/pedidos', [
            'empresa_id' => $empresa->id,
            'pagamento_id' => $pagamentoId,
            'subtotal' => 0,
            'total' => -1,
            'itens' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false])
            ->assertJsonStructure(['errors']);
    }

    /**
     * GET /api/pedidos/{id} - sucesso quando cliente vê próprio pedido
     */
    public function test_show_sucesso_cliente_ve_proprio_pedido(): void
    {
        $this->criarStatusPendente();
        $pagamentoId = $this->criarFormaPagamento();
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $cliente = $this->criarCliente();
        $pedido = $this->criarPedido($cliente->id, $empresa->id, StatusPedidos::where('slug', 'pendente')->first()->id, $pagamentoId);
        Sanctum::actingAs($cliente);

        $response = $this->getJson("/api/pedidos/{$pedido->id}");

        $response->assertOk()
            ->assertJsonFragment(['success' => true])
            ->assertJsonPath('pedido.id', $pedido->id);
    }

    /**
     * GET /api/pedidos/{id} - sucesso quando lojista vê pedido da sua empresa
     */
    public function test_show_sucesso_lojista_ve_pedido_da_empresa(): void
    {
        $statusId = $this->criarStatusPendente();
        $pagamentoId = $this->criarFormaPagamento();
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $cliente = $this->criarCliente();
        $pedido = $this->criarPedido($cliente->id, $empresa->id, $statusId, $pagamentoId);
        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/pedidos/{$pedido->id}");

        $response->assertOk()
            ->assertJsonFragment(['success' => true])
            ->assertJsonPath('pedido.id', $pedido->id);
    }

    /**
     * GET /api/pedidos/{id} - 403 quando usuário não é dono nem da empresa
     */
    public function test_show_403_quando_acesso_negado(): void
    {
        $statusId = $this->criarStatusPendente();
        $pagamentoId = $this->criarFormaPagamento();
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();
        $cliente = $this->criarCliente();
        $pedido = $this->criarPedido($cliente->id, $empresaOutra->id, $statusId, $pagamentoId);
        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/pedidos/{$pedido->id}");

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }

    /**
     * PUT /api/pedidos/{id} - sucesso (empresa altera status)
     */
    public function test_update_sucesso(): void
    {
        $statusPendenteId = $this->criarStatusPendente();
        $statusConfirmadoId = DB::table('status_pedidos')->insertGetId([
            'nome' => 'Confirmado',
            'slug' => 'confirmado',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $pagamentoId = $this->criarFormaPagamento();
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $cliente = $this->criarCliente();
        $pedido = $this->criarPedido($cliente->id, $empresa->id, $statusPendenteId, $pagamentoId);
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/pedidos/{$pedido->id}", [
            'status_pedido_id' => $statusConfirmadoId,
            'status_observacoes' => 'Confirmado pelo lojista',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['success' => true, 'message' => 'Pedido atualizado com sucesso'])
            ->assertJsonPath('pedido.status_pedido_id', (int) $statusConfirmadoId);
    }

    /**
     * PUT /api/pedidos/{id} - 403 quando pedido de outra empresa
     */
    public function test_update_pedido_outra_empresa_retorna_403(): void
    {
        $statusId = $this->criarStatusPendente();
        $pagamentoId = $this->criarFormaPagamento();
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();
        $cliente = $this->criarCliente();
        $pedido = $this->criarPedido($cliente->id, $empresaOutra->id, $statusId, $pagamentoId);
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/pedidos/{$pedido->id}", [
            'status_pedido_id' => $statusId,
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }

    /**
     * DELETE /api/pedidos/{id} - sucesso (apenas pendente)
     */
    public function test_destroy_sucesso(): void
    {
        $statusId = $this->criarStatusPendente();
        $pagamentoId = $this->criarFormaPagamento();
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $cliente = $this->criarCliente();
        $pedido = $this->criarPedido($cliente->id, $empresa->id, $statusId, $pagamentoId);
        Sanctum::actingAs($lojista);

        $response = $this->deleteJson("/api/pedidos/{$pedido->id}");

        $response->assertOk()
            ->assertJsonFragment(['success' => true, 'message' => 'Pedido excluído com sucesso']);
        $this->assertDatabaseMissing('pedidos', ['id' => $pedido->id]);
    }

    /**
     * DELETE /api/pedidos/{id} - 403 quando pedido de outra empresa
     */
    public function test_destroy_pedido_outra_empresa_retorna_403(): void
    {
        $statusId = $this->criarStatusPendente();
        $pagamentoId = $this->criarFormaPagamento();
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();
        $cliente = $this->criarCliente();
        $pedido = $this->criarPedido($cliente->id, $empresaOutra->id, $statusId, $pagamentoId);
        Sanctum::actingAs($lojista);

        $response = $this->deleteJson("/api/pedidos/{$pedido->id}");

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }

    /**
     * DELETE /api/pedidos/{id} - 400 quando pedido não está pendente
     */
    public function test_destroy_pedido_nao_pendente_retorna_400(): void
    {
        $statusPendenteId = $this->criarStatusPendente();
        $statusConfirmadoId = DB::table('status_pedidos')->insertGetId([
            'nome' => 'Confirmado',
            'slug' => 'confirmado',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $pagamentoId = $this->criarFormaPagamento();
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $cliente = $this->criarCliente();
        $pedido = $this->criarPedido($cliente->id, $empresa->id, $statusPendenteId, $pagamentoId);
        $pedido->update(['status_pedido_id' => $statusConfirmadoId]);
        Sanctum::actingAs($lojista);

        $response = $this->deleteJson("/api/pedidos/{$pedido->id}");

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Apenas pedidos pendentes podem ser excluídos.']);
    }

    /**
     * POST /api/pedidos/validar-cupom - 422 quando dados faltando
     */
    public function test_validar_cupom_dados_invalidos_retorna_422(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        Sanctum::actingAs($lojista);

        $response = $this->postJson('/api/pedidos/validar-cupom', [
            'cupom_codigo' => '',
            'empresa_id' => $empresa->id,
        ]);

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $this->assertTrue(isset($errors['cupom_codigo']) || isset($errors['valor_compra']));
    }

    /**
     * POST /api/pedidos/validar-cupom - 404 quando cupom não encontrado
     */
    public function test_validar_cupom_nao_encontrado_retorna_404(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        Sanctum::actingAs($lojista);

        $response = $this->postJson('/api/pedidos/validar-cupom', [
            'cupom_codigo' => 'NAOEXISTE123',
            'empresa_id' => $empresa->id,
            'valor_compra' => 100.00,
        ]);

        $response->assertStatus(404)
            ->assertJsonFragment(['error' => 'Cupom não encontrado']);
    }
}
