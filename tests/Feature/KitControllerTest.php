<?php

namespace Tests\Feature;

use App\Models\Categorias;
use App\Models\Empresa;
use App\Models\Kit;
use App\Models\Produto;
use App\Models\UnidadeMedida;
use App\Models\User;
use App\Models\UsuarioEmpresas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KitControllerTest extends TestCase
{
    use RefreshDatabase;

    private static int $cpfCnpjSufixo = 90;

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
            'razao_social' => 'Empresa Kit LTDA ' . uniqid(),
            'nome_fantasia' => 'Kit Teste',
            'slug' => 'kit-' . uniqid(),
            'email' => 'kit@example.com',
            'telefone' => '34999999999',
            'cpf_cnpj' => '123456780001' . (self::$cpfCnpjSufixo++),
            'nicho_id' => $nichoId,
            'cadastro_completo' => false,
            'ativo' => true,
        ]);
    }

    private function vincularUsuarioEmpresa(User $u, Empresa $e): void
    {
        UsuarioEmpresas::create(['usuario_id' => $u->id, 'empresa_id' => $e->id]);
    }

    private function criarCategoria(): Categorias
    {
        return Categorias::create([
            'nome' => 'Rações',
            'slug' => 'racoes-' . uniqid(),
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

    private function criarProduto(Empresa $empresa): Produto
    {
        $cat = $this->criarCategoria();
        $un = $this->criarUnidadeMedida();
        return Produto::create([
            'empresa_id' => $empresa->id,
            'categoria_id' => $cat->id,
            'unidade_medida_id' => $un->id,
            'tipo' => 'produto',
            'nome' => 'Produto Kit ' . uniqid(),
            'slug' => 'produto-kit-' . uniqid(),
            'preco' => 19.90,
            'estoque' => 50,
            'ativo' => true,
        ]);
    }

    private function headerEmpresa(int $empresaId): array
    {
        return ['x-empresa-id' => (string) $empresaId];
    }

    public function test_index_sem_header_retorna_422(): void
    {
        $empresa = $this->criarEmpresa();
        $user = User::factory()->create(['tipo_cadastro' => 0, 'is_master' => true]);
        $this->vincularUsuarioEmpresa($user, $empresa);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/kits');

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Header x-empresa-id é obrigatório e deve ser um ID válido.']);
    }

    public function test_index_sucesso(): void
    {
        $empresa = $this->criarEmpresa();
        $user = User::factory()->create(['tipo_cadastro' => 0, 'is_master' => true]);
        $this->vincularUsuarioEmpresa($user, $empresa);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/kits', $this->headerEmpresa($empresa->id));

        $response->assertOk();
        $response->assertJsonPath('data.kits', []);
    }

    public function test_store_sucesso(): void
    {
        $empresa = $this->criarEmpresa();
        $produto = $this->criarProduto($empresa);
        $user = User::factory()->create(['tipo_cadastro' => 0, 'is_master' => true]);
        $this->vincularUsuarioEmpresa($user, $empresa);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/kits', [
            'nome' => 'Kit Teste',
            'descricao' => 'Descrição',
            'preco' => 49.90,
            'ativo' => true,
            'itens' => [
                ['produto_id' => $produto->id, 'quantidade' => 2],
            ],
        ], $this->headerEmpresa($empresa->id));

        $response->assertStatus(201)
            ->assertJsonFragment(['success' => true, 'message' => 'Kit criado com sucesso'])
            ->assertJsonStructure(['kit', 'kit.itens']);
        $this->assertDatabaseHas('kits', ['nome' => 'Kit Teste', 'empresa_id' => $empresa->id]);
    }

    public function test_show_sucesso(): void
    {
        $empresa = $this->criarEmpresa();
        $user = User::factory()->create(['tipo_cadastro' => 0, 'is_master' => true]);
        $this->vincularUsuarioEmpresa($user, $empresa);
        $kit = Kit::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Kit Show',
            'preco' => 39.90,
            'ativo' => true,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/kits/' . $kit->id, $this->headerEmpresa($empresa->id));

        $response->assertOk()
            ->assertJsonPath('kit.nome', 'Kit Show');
    }
}
