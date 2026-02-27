<?php

namespace Database\Seeders;

use App\Helpers\FormatHelper;
use App\Models\Empresa;
use App\Models\EmpresaEndereco;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FilialSeeder extends Seeder
{
    /**
     * Cria uma filial para uma empresa matriz existente.
     * Define EMPRESA_MATRIZ_ID no .env para usar uma matriz específica; caso contrário usa a primeira matriz.
     */
    public function run(): void
    {
        $matrizId = env('EMPRESA_MATRIZ_ID');
        $matriz = $matrizId
            ? Empresa::where('is_matriz', true)->find((int) $matrizId)
            : Empresa::where('is_matriz', true)->first();

        if (!$matriz) {
            $this->command->warn('Nenhuma empresa matriz encontrada. Crie uma empresa (cadastro normal) antes de rodar este seeder.');
            return;
        }

        $sufixo = '-' . Str::random(6);
        $nomeFantasia = $matriz->nome_fantasia . ' Filial' . $sufixo;
        $razaoSocial = $matriz->razao_social . ' Filial' . $sufixo;
        $slugBase = FormatHelper::formatSlug($nomeFantasia);
        $slug = $slugBase;
        $tentativas = 0;
        while (Empresa::where('slug', $slug)->exists() && $tentativas < 20) {
            $slug = $slugBase . '-' . Str::random(6);
            $tentativas++;
        }

        $cpfCnpj = '98.765.432/0001-' . str_pad((string) random_int(10, 99), 2, '0');
        while (Empresa::where('cpf_cnpj', $cpfCnpj)->exists()) {
            $cpfCnpj = '98.765.432/0001-' . str_pad((string) random_int(10, 99), 2, '0');
        }

        DB::beginTransaction();
        try {
            $filial = Empresa::create([
                'tipo_pessoa' => $matriz->tipo_pessoa ?? 0,
                'razao_social' => $razaoSocial,
                'nome_fantasia' => $nomeFantasia,
                'slug' => $slug,
                'email' => 'filial' . Str::random(6) . '@example.com',
                'telefone' => FormatHelper::formatOnlyNumbers($matriz->telefone ?? '34999999999'),
                'cpf_cnpj' => $cpfCnpj,
                'nicho_id' => $matriz->nicho_id,
                'cadastro_completo' => false,
                'ativo' => true,
                'empresa_matriz_id' => $matriz->id,
                'is_matriz' => false,
            ]);

            EmpresaEndereco::create([
                'empresa_id' => $filial->id,
                'logradouro' => 'Av. Filial',
                'numero' => '100',
                'complemento' => 'Sala 1',
                'bairro' => 'Centro',
                'cidade' => 'Uberlândia',
                'estado' => 'MG',
                'cep' => '38400-000',
                'ponto_referencia' => null,
                'observacoes' => 'Endereço criado pelo FilialSeeder',
            ]);

            $filial->configuracoes()->create([
                'empresa_id' => $filial->id,
                'faz_entrega' => false,
                'faz_retirada' => true,
                'a_combinar' => false,
                'valor_entrega_padrao' => 10.00,
                'valor_entrega_minimo' => 10.00,
            ]);

            $filial->horarios()->create([
                'empresa_id' => $filial->id,
                'dia_semana' => 'segunda',
                'slug' => 'segunda',
                'horario_inicio' => '08:00',
                'horario_fim' => '18:00',
                'padrao' => true,
            ]);

            $usuariosMatriz = DB::table('usuarios_empresas')->where('empresa_id', $matriz->id)->pluck('usuario_id')->unique();
            foreach ($usuariosMatriz as $usuarioId) {
                $existe = DB::table('usuarios_empresas')->where('empresa_id', $filial->id)->where('usuario_id', $usuarioId)->exists();
                if (!$existe) {
                    DB::table('usuarios_empresas')->insert([
                        'usuario_id' => $usuarioId,
                        'empresa_id' => $filial->id,
                    ]);
                }
            }

            DB::commit();
            $this->command->info("Filial criada: ID {$filial->id}, nome_fantasia: {$filial->nome_fantasia}, matriz_id: {$matriz->id}.");
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
