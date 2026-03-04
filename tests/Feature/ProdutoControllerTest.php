<?php

namespace Tests\Feature;

use App\Models\Categorias;
use App\Models\Empresa;
use App\Models\Produto;
use App\Models\UnidadeMedida;
use App\Models\User;
use App\Models\UsuarioEmpresas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProdutoControllerTest extends TestCase
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

    private function criarCategoria(): Categorias
    {
        $slug = 'categoria-' . uniqid();
        return Categorias::create([
            'nome' => 'Rações',
            'slug' => $slug,
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

    private function criarProduto(int $empresaId, int $categoriaId, int $unidadeMedidaId, array $overrides = []): Produto
    {
        $nome = $overrides['nome'] ?? 'Produto Teste ' . uniqid();
        return Produto::create(array_merge([
            'empresa_id' => $empresaId,
            'categoria_id' => $categoriaId,
            'unidade_medida_id' => $unidadeMedidaId,
            'tipo' => 'produto',
            'nome' => $nome,
            'slug' => \Illuminate\Support\Str::slug($nome),
            'preco' => 29.90,
            'estoque' => 100,
            'destaque' => false,
            'ativo' => true,
        ], $overrides));
    }

    /**
     * Payload válido para POST /api/produtos (campos conforme ProdutoStoreRequest).
     */
    private function payloadStoreProdutoValido(int $empresaId, int $categoriaId, int $unidadeMedidaId, array $overrides = []): array
    {
        $nome = $overrides['nome'] ?? 'Produto Novo ' . uniqid();
        return array_merge([
            'empresa_id' => $empresaId,
            'categoria_id' => $categoriaId,
            'unidade_medida_id' => $unidadeMedidaId,
            'tipo' => 'produto',
            'nome' => $nome,
            'preco' => 39.90,
            'estoque' => 50,
            'destaque' => false,
            'ativo' => true,
        ], $overrides);
    }

    /**
     * GET /api/produtos - lista produtos da empresa do usuário
     */
    public function test_index_retorna_produtos_da_empresa(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $this->criarProduto($empresa->id, $categoria->id, $unidade->id, ['nome' => 'Ração Premium']);

        Sanctum::actingAs($lojista);

        $response = $this->getJson('/api/produtos');

        $response->assertOk()
            ->assertJsonStructure(['produtos', 'paginacao'])
            ->assertJsonPath('paginacao.total', 1);
    }

    /**
     * GET /api/produtos - filtro por busca (q)
     */
    public function test_index_filtro_busca_q(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $this->criarProduto($empresa->id, $categoria->id, $unidade->id, ['nome' => 'Ração Especial Cão']);
        $this->criarProduto($empresa->id, $categoria->id, $unidade->id, ['nome' => 'Outro Produto']);

        Sanctum::actingAs($lojista);

        $response = $this->getJson('/api/produtos?q=Especial');

        $response->assertOk();
        $lista = $response->json('produtos.data') ?? $response->json('produtos');
        $count = is_array($lista) ? count($lista) : 0;
        $this->assertGreaterThanOrEqual(1, $count, 'Busca por "Especial" deve retornar pelo menos 1 produto');
    }

    /**
     * GET /api/produtos/{id} - sucesso
     */
    public function test_show_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $produto = $this->criarProduto($empresa->id, $categoria->id, $unidade->id);

        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/produtos/{$produto->id}");

        $response->assertOk()
            ->assertJsonPath('produto.nome', $produto->nome)
            ->assertJsonPath('produto.id', $produto->id);
    }

    /**
     * GET /api/produtos/{id} - 403 quando produto de outra empresa
     */
    public function test_show_produto_outra_empresa_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $produto = $this->criarProduto($empresaOutra->id, $categoria->id, $unidade->id);

        Sanctum::actingAs($lojista);

        $response = $this->getJson("/api/produtos/{$produto->id}");

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }

    /**
     * POST /api/produtos - criação com sucesso
     */
    public function test_store_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $payload = $this->payloadStoreProdutoValido($empresa->id, $categoria->id, $unidade->id, ['nome' => 'Novo Produto Store']);

        Sanctum::actingAs($lojista);

        $response = $this->postJson('/api/produtos', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Produto criado com sucesso'])
            ->assertJsonStructure(['produto']);
        $this->assertDatabaseHas('produtos', ['nome' => 'Novo Produto Store', 'empresa_id' => $empresa->id]);
    }

    /**
     * POST /api/produtos - 422 quando dados inválidos
     */
    public function test_store_dados_invalidos_retorna_422(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        Sanctum::actingAs($lojista);

        $response = $this->postJson('/api/produtos', [
            'empresa_id' => $empresa->id,
            'categoria_id' => $categoria->id,
            'unidade_medida_id' => $unidade->id,
            'tipo' => 'invalido',
            'nome' => 'Ab',
            'preco' => -1,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false])
            ->assertJsonStructure(['errors']);
        $errors = $response->json('errors');
        $this->assertTrue(
            isset($errors['tipo']) || isset($errors['nome']) || isset($errors['preco']),
            'Deve haver erro em tipo, nome ou preço'
        );
    }

    /**
     * POST /api/produtos - 403 quando empresa não pertence ao usuário
     */
    public function test_store_empresa_nao_pertence_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $payload = $this->payloadStoreProdutoValido($empresaOutra->id, $categoria->id, $unidade->id);
        Sanctum::actingAs($lojista);

        $response = $this->postJson('/api/produtos', $payload);

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }

    /**
     * POST /api/produtos - 422 quando nome duplicado na mesma empresa
     */
    public function test_store_nome_duplicado_na_empresa_retorna_422(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $this->criarProduto($empresa->id, $categoria->id, $unidade->id, ['nome' => 'Produto Unico']);
        $payload = $this->payloadStoreProdutoValido($empresa->id, $categoria->id, $unidade->id, ['nome' => 'Produto Unico']);
        Sanctum::actingAs($lojista);

        $response = $this->postJson('/api/produtos', $payload);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false])
            ->assertJsonPath('errors.nome.0', 'Já existe um produto com este nome nesta empresa.');
    }

    /**
     * PUT /api/produtos/{id} - atualização com sucesso
     */
    public function test_update_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $produto = $this->criarProduto($empresa->id, $categoria->id, $unidade->id);
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/produtos/{$produto->id}", [
            'nome' => 'Produto Atualizado',
            'preco' => 49.90,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Produto atualizado com sucesso'])
            ->assertJsonPath('produto.nome', 'Produto Atualizado');
        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'nome' => 'Produto Atualizado']);
    }

    /**
     * PUT /api/produtos/{id} - 422 quando dados inválidos
     */
    public function test_update_dados_invalidos_retorna_422(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $produto = $this->criarProduto($empresa->id, $categoria->id, $unidade->id);
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/produtos/{$produto->id}", [
            'nome' => 'Ab',
            'tipo' => 'invalido',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false])
            ->assertJsonStructure(['errors']);
    }

    /**
     * PUT /api/produtos/{id} - 403 quando produto de outra empresa
     */
    public function test_update_produto_outra_empresa_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $produto = $this->criarProduto($empresaOutra->id, $categoria->id, $unidade->id);
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/produtos/{$produto->id}", ['nome' => 'Tentativa']);

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }

    /**
     * DELETE /api/produtos/{id} - sucesso
     */
    public function test_destroy_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $produto = $this->criarProduto($empresa->id, $categoria->id, $unidade->id);
        Sanctum::actingAs($lojista);

        $response = $this->deleteJson("/api/produtos/{$produto->id}");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Produto deletado com sucesso']);
        $this->assertSoftDeleted('produtos', ['id' => $produto->id]);
    }

    /**
     * DELETE /api/produtos/{id} - 403 quando produto de outra empresa
     */
    public function test_destroy_produto_outra_empresa_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $produto = $this->criarProduto($empresaOutra->id, $categoria->id, $unidade->id);
        Sanctum::actingAs($lojista);

        $response = $this->deleteJson("/api/produtos/{$produto->id}");

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }

    /**
     * PUT /api/produtos/{id}/toggle-destaque - sucesso
     */
    public function test_toggle_destaque_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $produto = $this->criarProduto($empresa->id, $categoria->id, $unidade->id, ['destaque' => false]);
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/produtos/{$produto->id}/toggle-destaque");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Status de destaque alterado com sucesso'])
            ->assertJsonPath('produto.destaque', true);
    }

    /**
     * PUT /api/produtos/{id}/toggle-destaque - 403 quando produto de outra empresa
     */
    public function test_toggle_destaque_produto_outra_empresa_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $produto = $this->criarProduto($empresaOutra->id, $categoria->id, $unidade->id);
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/produtos/{$produto->id}/toggle-destaque");

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }

    /**
     * PUT /api/produtos/{id}/toggle-ativo - sucesso
     */
    public function test_toggle_ativo_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $produto = $this->criarProduto($empresa->id, $categoria->id, $unidade->id, ['ativo' => true]);
        Sanctum::actingAs($lojista);

        $response = $this->putJson("/api/produtos/{$produto->id}/toggle-ativo");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Status do produto alterado com sucesso'])
            ->assertJsonPath('produto.ativo', false);
    }

    /**
     * GET /api/produtos/search/buscar - sucesso
     */
    public function test_search_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $this->criarProduto($empresa->id, $categoria->id, $unidade->id, ['nome' => 'Ração Cão Adulto', 'ativo' => true]);
        Sanctum::actingAs($lojista);

        $response = $this->getJson('/api/produtos/search/buscar?q=Racao');

        $response->assertOk()
            ->assertJsonStructure(['produtos']);
    }

    /**
     * DELETE /api/produtos/{id} - 400 quando produto está em pedidos
     */
    public function test_destroy_produto_em_pedidos_retorna_400(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $produto = $this->criarProduto($empresa->id, $categoria->id, $unidade->id);

        $statusId = DB::table('status_pedidos')->insertGetId([
            'nome' => 'Pendente',
            'slug' => 'pendente',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $pagamentoId = DB::table('formas_pagamentos')->insertGetId([
            'nome' => 'Dinheiro',
            'slug' => 'dinheiro',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $pedidoId = DB::table('pedidos')->insertGetId([
            'usuario_id' => $lojista->id,
            'empresa_id' => $empresa->id,
            'status_pedido_id' => $statusId,
            'pagamento_id' => $pagamentoId,
            'subtotal' => 29.90,
            'desconto' => 0,
            'frete' => 0,
            'total' => 29.90,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('pedido_items')->insert([
            'pedido_id' => $pedidoId,
            'produto_id' => $produto->id,
            'quantidade' => 1,
            'preco_unitario' => 29.90,
            'desconto' => 0,
            'preco_total' => 29.90,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($lojista);

        $response = $this->deleteJson("/api/produtos/{$produto->id}");

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'O produto está sendo usado em pedidos existentes.']);
    }

    /**
     * POST /api/produtos/{id}/upload-image - sucesso
     */
    public function test_upload_image_sucesso(): void
    {
        Storage::fake('r2');
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $produto = $this->criarProduto($empresa->id, $categoria->id, $unidade->id);
        Sanctum::actingAs($lojista);

        $file = UploadedFile::fake()->image('produto.jpg', 100, 100);
        $response = $this->post("/api/produtos/{$produto->id}/upload-image", [
            'imagem' => $file,
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonFragment(['success' => true, 'message' => 'Imagem do produto atualizada com sucesso'])
            ->assertJsonStructure(['produto']);
    }

    /**
     * POST /api/produtos/{id}/upload-image - 403 quando produto de outra empresa
     */
    public function test_upload_image_produto_outra_empresa_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $produto = $this->criarProduto($empresaOutra->id, $categoria->id, $unidade->id);
        Sanctum::actingAs($lojista);

        $file = UploadedFile::fake()->image('produto.jpg', 100, 100);
        $response = $this->post("/api/produtos/{$produto->id}/upload-image", [
            'imagem' => $file,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(403)
            ->assertJsonFragment(['error' => 'Acesso negado']);
    }

    /**
     * POST /api/produtos/{id}/upload-image - 422 quando não envia imagem
     */
    public function test_upload_image_sem_arquivo_retorna_422(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $produto = $this->criarProduto($empresa->id, $categoria->id, $unidade->id);
        Sanctum::actingAs($lojista);

        $response = $this->postJson("/api/produtos/{$produto->id}/upload-image", []);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    /**
     * POST /api/produtos/{id}/upload-image - 422 quando arquivo não é imagem
     */
    public function test_upload_image_arquivo_nao_imagem_retorna_422(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $produto = $this->criarProduto($empresa->id, $categoria->id, $unidade->id);
        Sanctum::actingAs($lojista);

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $response = $this->post("/api/produtos/{$produto->id}/upload-image", [
            'imagem' => $file,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    /**
     * POST /api/produtos/{id}/duplicar - sucesso
     */
    public function test_duplicar_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $produto = $this->criarProduto($empresa->id, $categoria->id, $unidade->id, ['nome' => 'Original']);
        Sanctum::actingAs($lojista);

        $response = $this->postJson("/api/produtos/{$produto->id}/duplicar");

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true, 'message' => 'Produto duplicado com sucesso'])
            ->assertJsonStructure(['produto']);
        $this->assertDatabaseHas('produtos', ['empresa_id' => $empresa->id, 'nome' => 'Original - Cópia']);
    }

    /**
     * POST /api/produtos/{id}/duplicar - 403 quando produto de outra empresa
     */
    public function test_duplicar_produto_outra_empresa_retorna_403(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $produto = $this->criarProduto($empresaOutra->id, $categoria->id, $unidade->id);
        Sanctum::actingAs($lojista);

        $response = $this->postJson("/api/produtos/{$produto->id}/duplicar");

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Você não tem permissão para duplicar este produto.']);
    }

    /**
     * POST /api/produtos/lote - sucesso
     */
    public function test_store_lote_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        Sanctum::actingAs($lojista);

        $payload = [
            'produtos' => [
                [
                    'empresa_id' => $empresa->id,
                    'categoria_id' => $categoria->id,
                    'unidade_medida_id' => $unidade->id,
                    'tipo' => 'produto',
                    'nome' => 'Produto Lote 1',
                    'preco' => 19.90,
                    'estoque' => 10,
                ],
                [
                    'empresa_id' => $empresa->id,
                    'categoria_id' => $categoria->id,
                    'unidade_medida_id' => $unidade->id,
                    'tipo' => 'produto',
                    'nome' => 'Produto Lote 2',
                    'preco' => 29.90,
                ],
            ],
        ];

        $response = $this->postJson('/api/produtos/lote', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true, 'message' => 'Produtos cadastrados com sucesso.'])
            ->assertJsonStructure(['produtos']);
        $this->assertDatabaseHas('produtos', ['nome' => 'Produto Lote 1', 'empresa_id' => $empresa->id]);
        $this->assertDatabaseHas('produtos', ['nome' => 'Produto Lote 2', 'empresa_id' => $empresa->id]);
    }

    /**
     * POST /api/produtos/lote - 422 quando dados inválidos
     */
    public function test_store_lote_dados_invalidos_retorna_422(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        Sanctum::actingAs($lojista);

        $response = $this->postJson('/api/produtos/lote', [
            'produtos' => [
                [
                    'empresa_id' => $empresa->id,
                    'categoria_id' => 99999,
                    'unidade_medida_id' => $unidade->id,
                    'nome' => 'Ab',
                    'preco' => -1,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false])
            ->assertJsonStructure(['errors']);
    }

    /**
     * DELETE /api/produtos/lote - sucesso
     */
    public function test_destroy_lote_sucesso(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $p1 = $this->criarProduto($empresa->id, $categoria->id, $unidade->id, ['nome' => 'Para Deletar 1']);
        $p2 = $this->criarProduto($empresa->id, $categoria->id, $unidade->id, ['nome' => 'Para Deletar 2']);
        Sanctum::actingAs($lojista);

        $response = $this->deleteJson('/api/produtos/lote', ['ids' => [$p1->id, $p2->id]]);

        $response->assertOk()
            ->assertJsonFragment(['message' => '2 produto(s) deletado(s) com sucesso']);
        $this->assertSoftDeleted('produtos', ['id' => $p1->id]);
        $this->assertSoftDeleted('produtos', ['id' => $p2->id]);
    }

    /**
     * DELETE /api/produtos/lote - 400 quando ids vazios
     */
    public function test_destroy_lote_ids_vazios_retorna_400(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        Sanctum::actingAs($lojista);

        $response = $this->deleteJson('/api/produtos/lote', ['ids' => []]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Nenhum ID de produto foi fornecido.']);
    }

    /**
     * DELETE /api/produtos/lote - 403 quando algum produto de outra empresa
     */
    public function test_destroy_lote_produto_outra_empresa_retorna_403(): void
    {
        [$lojista, $empresa] = $this->criarLojistaComEmpresa(true);
        $empresaOutra = $this->criarEmpresa();
        $categoria = $this->criarCategoria();
        $unidade = $this->criarUnidadeMedida();
        $p1 = $this->criarProduto($empresa->id, $categoria->id, $unidade->id);
        $p2 = $this->criarProduto($empresaOutra->id, $categoria->id, $unidade->id);
        Sanctum::actingAs($lojista);

        $response = $this->deleteJson('/api/produtos/lote', ['ids' => [$p1->id, $p2->id]]);

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Você não tem permissão para deletar alguns dos produtos selecionados.']);
    }

    /**
     * GET /api/produtos/categorias - sucesso
     */
    public function test_listar_categorias_sucesso(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $this->criarCategoria();
        Sanctum::actingAs($lojista);

        $response = $this->getJson('/api/produtos/categorias');

        $response->assertOk()
            ->assertJsonFragment(['success' => true])
            ->assertJsonStructure(['categorias']);
    }

    /**
     * GET /api/produtos/unidades-medidas - sucesso
     */
    public function test_listar_unidades_medidas_sucesso(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        $this->criarUnidadeMedida();
        Sanctum::actingAs($lojista);

        $response = $this->getJson('/api/produtos/unidades-medidas');

        $response->assertOk()
            ->assertJsonFragment(['success' => true])
            ->assertJsonStructure(['unidades']);
    }

    /**
     * GET /api/produtos/importar/terceiros/lista - sucesso
     */
    public function test_listar_terceiros_sucesso(): void
    {
        [$lojista] = $this->criarLojistaComEmpresa(true);
        Sanctum::actingAs($lojista);

        $response = $this->getJson('/api/produtos/importar/terceiros/lista');

        $response->assertOk()
            ->assertJsonFragment(['success' => true])
            ->assertJsonStructure(['planilhas_terceiros']);
    }
}
