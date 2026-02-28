<?php

namespace Database\Seeders;

use App\Models\EmpresaFatura;
use App\Models\Pedido;
use App\Models\PedidoEndereco;
use App\Models\PedidoHistoricoStatus;
use App\Models\PedidoItems;
use App\Models\StatusPedidos;
use App\Models\UsuarioFaturamentoPedidos;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CreatePedidosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Cria 29 pedidos para testar o limite de cobrança automática
     */
    public function run(): void
    {
        $timestamp = now();

        // Dados fixos conforme solicitado
        $usuarioId = 3; // ID do cliente
        $empresaId = 1; // ID da empresa
        $produtoId = 1; // ID do produto

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

        $this->command->info('Criando 29 pedidos para teste de cobrança automática...');

        for ($i = 1; $i <= 29; $i++) {
            DB::beginTransaction();
            try {
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
                    'observacoes' => "Pedido de teste #{$i}",
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
                    'observacoes' => 'Pedido criado via seeder',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                // Atualizar contagem de pedidos para faturamento
                $this->atualizarContagemPedidos($empresaId);

                DB::commit();

                if ($i <= 5 || $i >= 25) { // Mostrar progresso apenas no início e fim
                    $this->command->info("Pedido #{$i} criado (ID: {$pedidoId})");
                } elseif ($i == 15) {
                    $this->command->info("... criando pedido #{$i} ...");
                }

            } catch (\Exception $e) {
                DB::rollBack();
                $this->command->error("Erro ao criar pedido #{$i}: {$e->getMessage()}");
                continue;
            }
        }

        $this->command->info('✅ CreatePedidosSeeder executado: 29 pedidos criados!');
        $this->command->info('📊 Verifique se a cobrança automática foi disparada (30º pedido).');
    }

    /**
     * Atualiza a contagem de pedidos para o sistema de faturamento
     */
    private function atualizarContagemPedidos(int $empresaId): void
    {
        // Buscar o master da empresa
        $master = DB::table('usuarios_empresas')
            ->join('usuarios', 'usuarios_empresas.usuario_id', '=', 'usuarios.id')
            ->where('usuarios_empresas.empresa_id', $empresaId)
            ->where('usuarios.is_master', true)
            ->select('usuarios.id')
            ->first();

        if (!$master) {
            return; // Não há master para esta empresa
        }

        $mesAtual = now()->format('Y-m');

        // Buscar ou criar registro de contagem
        $registro = UsuarioFaturamentoPedidos::firstOrCreate(
            ['usuario_id' => $master->id, 'mes_referencia' => $mesAtual],
            ['total_pedidos' => 0, 'assinatura_disparada' => false]
        );

        // Incrementar contagem
        $registro->increment('total_pedidos');

        // Log para debug
        if ($registro->total_pedidos >= QTD_PEDIDOS_COBRAR) {
            $this->command->warn("🚨 LIMITE ATINGIDO: {$registro->total_pedidos} pedidos - Assinatura deveria ser disparada!");
        }
    }
}