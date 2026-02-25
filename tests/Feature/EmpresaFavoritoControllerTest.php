<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\EmpresaFavorito;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmpresaFavoritoControllerTest extends TestCase
{
    use RefreshDatabase;

    private static int $cpfCnpjSufixo = 40;

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

    private function criarEmpresa(bool $cadastroCompleto = true): Empresa
    {
        $nichoId = $this->criarNichoEmpresa();
        return Empresa::create([
            'razao_social' => 'Empresa Favorito LTDA ' . uniqid(),
            'nome_fantasia' => 'Favorito Teste',
            'slug' => 'favorito-' . uniqid(),
            'email' => 'fav@example.com',
            'telefone' => '34999999999',
            'cpf_cnpj' => '123456780001' . (self::$cpfCnpjSufixo++),
            'nicho_id' => $nichoId,
            'cadastro_completo' => $cadastroCompleto,
            'ativo' => true,
        ]);
    }

    /**
     * POST /api/favoritos/toggle/{empresaId} - adicionar aos favoritos
     */
    public function test_toggle_adiciona_favorito(): void
    {
        $user = User::factory()->create(['tipo_cadastro' => 1]);
        $empresa = $this->criarEmpresa();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/favoritos/toggle/{$empresa->id}");

        $response->assertOk()
            ->assertJsonFragment(['success' => true, 'favoritado' => true, 'message' => 'Empresa adicionada aos favoritos']);
        $this->assertDatabaseHas('empresa_favoritos', ['usuario_id' => $user->id, 'empresa_id' => $empresa->id]);
    }

    /**
     * POST /api/favoritos/toggle/{empresaId} - remover dos favoritos
     */
    public function test_toggle_remove_favorito(): void
    {
        $user = User::factory()->create(['tipo_cadastro' => 1]);
        $empresa = $this->criarEmpresa();
        EmpresaFavorito::create(['usuario_id' => $user->id, 'empresa_id' => $empresa->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/favoritos/toggle/{$empresa->id}");

        $response->assertOk()
            ->assertJsonFragment(['success' => true, 'favoritado' => false, 'message' => 'Empresa removida dos favoritos']);
    }

    /**
     * GET /api/favoritos - listar favoritos
     */
    public function test_listar_favoritos_sucesso(): void
    {
        $user = User::factory()->create(['tipo_cadastro' => 1]);
        $empresa = $this->criarEmpresa(true);
        EmpresaFavorito::create(['usuario_id' => $user->id, 'empresa_id' => $empresa->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/favoritos');

        $response->assertOk()
            ->assertJsonFragment(['success' => true])
            ->assertJsonStructure(['empresas', 'paginacao']);
    }
}
