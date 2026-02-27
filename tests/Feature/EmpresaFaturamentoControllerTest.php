<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\EmpresaFaturamento;
use App\Models\User;
use App\Models\UsuarioEmpresas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmpresaFaturamentoControllerTest extends TestCase
{
    use RefreshDatabase;

    private function criarNichoId(): int
    {
        return DB::table('nichos_empresa')->insertGetId([
            'nome' => 'Petshop',
            'slug' => 'petshop-' . uniqid(),
            'imagem' => null,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function criarEmpresa(): Empresa
    {
        $nichoId = $this->criarNichoId();
        return Empresa::create([
            'razao_social' => 'Empresa LTDA ' . uniqid(),
            'nome_fantasia' => 'Empresa Teste',
            'slug' => 'empresa-' . uniqid(),
            'email' => 'empresa@example.com',
            'telefone' => '34999999999',
            'cpf_cnpj' => '12.345.678/0001-' . str_pad((string) random_int(10, 99), 2, '0'),
            'nicho_id' => $nichoId,
            'cadastro_completo' => false,
            'ativo' => true,
        ]);
    }

    private function criarMasterComEmpresa(): array
    {
        $empresa = $this->criarEmpresa();
        $usuario = User::factory()->create(['is_master' => true, 'tipo_cadastro' => 0]);
        UsuarioEmpresas::create(['usuario_id' => $usuario->id, 'empresa_id' => $empresa->id]);
        return [$usuario, $empresa];
    }

    private function payloadStore(): array
    {
        return [
            'nome_titular' => 'João Silva',
            'cpf_cnpj' => '123.456.789-00',
            'email' => 'joao@example.com',
            'telefone' => '(34) 99999-9999',
            'chave_pix' => 'joao@example.com',
            'tipo_chave_pix' => 'email',
        ];
    }

    public function test_show_sem_registro_retorna_null(): void
    {
        [$master, $empresa] = $this->criarMasterComEmpresa();
        Sanctum::actingAs($master);
        $response = $this->getJson('/api/faturamento', ['x-empresa-id' => $empresa->id]);
        $response->assertOk()
            ->assertJsonPath('faturamento', null);
    }

    public function test_store_faturamento_com_sucesso(): void
    {
        [$master, $empresa] = $this->criarMasterComEmpresa();
        Sanctum::actingAs($master);
        $payload = $this->payloadStore();
        $response = $this->postJson('/api/faturamento', $payload, ['x-empresa-id' => $empresa->id]);
        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('faturamento.nome_titular', 'João Silva')
            ->assertJsonPath('faturamento.cpf_cnpj', '123.456.789-00')
            ->assertJsonPath('faturamento.email', 'joao@example.com');
        $this->assertDatabaseHas('empresa_faturamento', ['usuario_id' => $master->id, 'email' => 'joao@example.com']);
    }

    public function test_update_cpf_cnpj_ignorado(): void
    {
        [$master, $empresa] = $this->criarMasterComEmpresa();
        EmpresaFaturamento::forceCreate([
            'usuario_id' => $master->id,
            'nome_titular' => 'João',
            'cpf_cnpj' => 'original-cpf',
            'email' => 'joao@example.com',
            'telefone' => '34999999999',
        ]);
        Sanctum::actingAs($master);
        $response = $this->putJson('/api/faturamento', [
            'email' => 'novo@example.com',
            'telefone' => '34888888888',
            'cpf_cnpj' => 'tentativa-alterar',
            'nome_titular' => 'Tentativa',
        ], ['x-empresa-id' => $empresa->id]);
        $response->assertOk();
        $f = EmpresaFaturamento::where('usuario_id', $master->id)->first();
        $this->assertSame('original-cpf', $f->cpf_cnpj);
        $this->assertSame('João', $f->nome_titular);
        $this->assertSame('novo@example.com', $f->email);
    }

    public function test_update_email_telefone_pix_sucesso(): void
    {
        [$master, $empresa] = $this->criarMasterComEmpresa();
        EmpresaFaturamento::forceCreate([
            'usuario_id' => $master->id,
            'nome_titular' => 'João',
            'cpf_cnpj' => '123',
            'email' => 'antigo@example.com',
            'telefone' => '34999999999',
        ]);
        Sanctum::actingAs($master);
        $response = $this->putJson('/api/faturamento', [
            'email' => 'novo@example.com',
            'telefone' => '(34) 88888-8888',
            'chave_pix' => 'chave-pix-nova',
            'tipo_chave_pix' => 'aleatoria',
        ], ['x-empresa-id' => $empresa->id]);
        $response->assertOk()
            ->assertJsonPath('faturamento.email', 'novo@example.com')
            ->assertJsonPath('faturamento.chave_pix', 'chave-pix-nova')
            ->assertJsonPath('faturamento.tipo_chave_pix', 'aleatoria');
        $f = EmpresaFaturamento::where('usuario_id', $master->id)->first();
        $this->assertSame('novo@example.com', $f->email);
        $this->assertSame('chave-pix-nova', $f->chave_pix);
    }

    public function test_resumo_retorna_estrutura(): void
    {
        [$master, $empresa] = $this->criarMasterComEmpresa();
        Sanctum::actingAs($master);
        $response = $this->getJson('/api/faturamento/resumo', ['x-empresa-id' => $empresa->id]);
        $response->assertOk()
            ->assertJsonPath('plano_status', 'gratuito')
            ->assertJsonPath('limite_gratuito', 30)
            ->assertJsonPath('valor_plano', 39.90)
            ->assertJsonStructure(['faturas', 'pedidos_mes_atual', 'proxima_cobranca']);
    }
}
