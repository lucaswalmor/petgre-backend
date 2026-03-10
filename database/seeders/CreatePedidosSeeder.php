<?php

namespace Database\Seeders;

use App\Models\StatusPedidos;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CreatePedidosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Cria 16 pedidos para o mês 02/2026 para testar a cobrança condicional mensal
     */
    public function run(): void
    {
        // Dados fixos conforme solicitado
        $usuarioId = 3; // ID do cliente
        $empresaId = 1; // ID da empresa
        $produtoId = 1; // ID do produto

        // Data base: fevereiro/2026 (mês 02)
        $dataBase = Carbon::create(2026, 2, 15, 14, 30, 0);

        // Buscar status "pendente"
        $statusPendente = StatusPedidos::where('slug', 'pendente')->first();
        if (!$statusPendente) {
            $this->command->error('Status "pendente" não encontrado!');
            return;
        }

        // Buscar produto para obter preço
        $produto = DB::table('produtos')->find($produtoId);
        if (!$produto) {
            $this->command->error('Produto não encontrado!');
            return;
        }

        $precoProduto = (float) $produto->preco;
        $quantidade = 1; // 1 unidade por pedido
        $subtotal = $precoProduto * $quantidade;

        $this->command->info('🧪 Criando 16 pedidos para Fevereiro/2026 (mês 02)...');
        $this->command->info('📅 Data base: ' . $dataBase->format('d/m/Y'));
        $this->command->info('');
        $this->command->info('💡 Com 16 pedidos, a cobrança será gerada no dia 01/03/2026.');
        $this->command->info('   Execute: php artisan faturamento:gerar-cobrancas-mensais --mes=2026-02');
        $this->command->info('');

        for ($i = 1; $i <= 16; $i++) {
            DB::beginTransaction();
            try {
                // Criar timestamp variando ao longo do mês de fevereiro
                $timestamp = $dataBase->copy()->addDays(rand(0, 27))->addHours(rand(8, 22));

                // Criar pedido
                $pedidoId = DB::table('pedidos')->insertGetId([
                    'usuario_id' => $usuarioId,
                    'empresa_id' => $empresaId,
                    'status_pedido_id' => $statusPendente->id,
                    'pagamento_id' => 1, // Assumindo PIX como forma de pagamento
                    'subtotal' => $subtotal,
                    'desconto' => 0,
                    'frete' => 0,
                    'total' => $subtotal,
                    'observacoes' => "Pedido de teste #{$i} - Fevereiro/2026",
                    'foi_entrega' => false, // Retirada na loja
                    'ativo' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                // Criar item do pedido
                DB::table('pedido_items')->insert([
                    'pedido_id' => $pedidoId,
                    'produto_id' => $produtoId,
                    'quantidade' => $quantidade,
                    'preco_unitario' => $precoProduto,
                    'preco_total' => $subtotal,
                    'observacoes' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                // Criar histórico inicial
                DB::table('pedido_historico_status')->insert([
                    'pedido_id' => $pedidoId,
                    'status_pedido_id' => $statusPendente->id,
                    'observacoes' => 'Pedido criado via seeder para teste de cobrança condicional',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                DB::commit();

                $this->command->info("✅ Pedido #{$i} criado (ID: {$pedidoId}) - {$timestamp->format('d/m/Y H:i')}");

            } catch (\Exception $e) {
                DB::rollBack();
                $this->command->error("❌ Erro ao criar pedido #{$i}: {$e->getMessage()}");
                continue;
            }
        }

        $this->command->info('');
        $this->command->info('✅ CreatePedidosSeeder executado: 16 pedidos criados para Fevereiro/2026!');
        $this->command->info('');
        $this->command->info('📋 PRÓXIMOS PASSOS PARA TESTAR:');
        $this->command->info('   1. Verifique os pedidos: SELECT * FROM pedidos WHERE MONTH(created_at) = 2;');
        $this->command->info('   2. Teste a geração de cobrança (dry-run):');
        $this->command->info('      php artisan faturamento:gerar-cobrancas-mensais --mes=2026-02 --dry-run');
        $this->command->info('   3. Gere a cobrança real:');
        $this->command->info('      php artisan faturamento:gerar-cobrancas-mensais --mes=2026-02');
        $this->command->info('');
        $this->command->info('💰 Com 16 pedidos, a empresa ATINGIU o limite para cobrança (limite gratuito: 15).');
        $this->command->info('   Valor será calculado conforme: base + (filiais × base × 0.5)');
    }
}