<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Produto;
use App\Models\User;
use App\Models\UsuarioEmpresas;
use App\Models\Categorias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsuarioLogControllerTest extends TestCase
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
            'cpf_cnpj' => '123456780001' . rand(70, 99),
            'nicho_id' => $nichoId,
            'cadastro_completo' => false,
            'ativo' => true,
        ]);
    }

    private function criarProduto(int $empresaId): Produto
    {
        $cat = Categorias::create(['nome' => 'Cat', 'slug' => 'cat-' . uniqid(), 'ativo' => true]);
        $umId = DB::table('unidades_medidas')->insertGetId([
            'nome' => 'UN',
            'sigla' => 'UN',
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return Produto::create([
            'empresa_id' => $empresaId,
            'categoria_id' => $cat->id,
            'unidade_medida_id' => $umId,
            'tipo' => 'produto',
            'nome' => 'Prod ' . uniqid(),
            'slug' => 'prod-' . uniqid(),
            'preco' => 10,
            'estoque' => 100,
            'ativo' => true,
        ]);
    }

    /**
     * POST /api/logs/adicionar-produto-carrinho - sucesso
     */
    public function test_salvar_log_adicionar_carrinho_sucesso(): void
    {
        $empresa = $this->criarEmpresa();
        $produto = $this->criarProduto($empresa->id);
        $user = User::factory()->create(['tipo_cadastro' => 1]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/logs/adicionar-produto-carrinho', [
            'empresa_id' => $empresa->id,
            'produto_id' => $produto->id,
            'quantidade' => 2,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['success' => true]);
    }

    /**
     * POST /api/logs/remover-produto-carrinho - sucesso
     */
    public function test_salvar_log_remover_carrinho_sucesso(): void
    {
        $empresa = $this->criarEmpresa();
        $produto = $this->criarProduto($empresa->id);
        $user = User::factory()->create(['tipo_cadastro' => 1]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/logs/remover-produto-carrinho', [
            'empresa_id' => $empresa->id,
            'produto_id' => $produto->id,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['success' => true]);
    }

    /**
     * POST /api/logs/trocar-loja - sucesso
     */
    public function test_salvar_log_trocar_loja_sucesso(): void
    {
        $empresaA = $this->criarEmpresa();
        $empresaB = $this->criarEmpresa();
        $user = User::factory()->create(['tipo_cadastro' => 1]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/logs/trocar-loja', [
            'empresa_anterior_id' => $empresaA->id,
            'empresa_nova_id' => $empresaB->id,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['success' => true]);
    }
}
