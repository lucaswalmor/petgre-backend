<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SistemaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timestamp = now();

        // -----------------------------
        // Categorias
        // -----------------------------
        $categorias = [
            [
                'nome' => 'Rações',
                'imagem' => 'https://i.ibb.co/Pz1vbP09/racao.png'
            ],
            [
                'nome' => 'Brinquedos',
                'imagem' => 'https://i.ibb.co/MknKJ6sL/brinquedos.png'
            ],
            [
                'nome' => 'Acessórios',
                'imagem' => 'https://i.ibb.co/fVBWwHr5/acessorios.png'
            ],
            [
                'nome' => 'Higiene e Limpeza',
                'imagem' => 'https://i.ibb.co/FkSnj4CL/higienelimpeza.png'
            ],
            [
                'nome' => 'Medicamentos',
                'imagem' => 'https://i.ibb.co/TMThkqz5/Medicamentos.png'
            ],
            [
                'nome' => 'Petiscos',
                'imagem' => 'https://i.ibb.co/9mv4RBSr/petisco.png'
            ],
            [
                'nome' => 'Serviços',
                'imagem' => 'https://i.ibb.co/27kVvXzY/servicos.png'
            ],
            [
                'nome' => 'Outros',
                'imagem' => null
            ],
        ];

        foreach ($categorias as $categoria) {
            DB::table('categorias')->insert([
                'nome' => $categoria['nome'],
                'slug' => strtolower(str_replace(' ', '-', $categoria['nome'])),
                'imagem' => $categoria['imagem'],
                'ativo' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        // -----------------------------
        // Status de pedidos
        // -----------------------------
        $statusPedidos = [
            'pendente',
            'confirmado',
            'em_preparacao',
            'em_entrega',
            'entregue',
            'cancelado',
        ];

        foreach ($statusPedidos as $status) {
            DB::table('status_pedidos')->insert([
                'nome' => ucfirst(str_replace('_', ' ', $status)),
                'slug' => $status,
                'ativo' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        // -----------------------------
        // Nicho da empresa
        // -----------------------------
        $nichos = [
            [
                'nome' => 'Petshop',
                'imagem' => 'https://i.ibb.co/fzbzY9WQ/petshop.png'
            ],
            [
                'nome' => 'Agropecuária',
                'imagem' => 'https://i.ibb.co/GG6QS07/agropecu-ria.png'
            ],
            [
                'nome' => 'Banho e Tosa',
                'imagem' => 'https://i.ibb.co/R4jrzbRf/banho-e-tosa.png'
            ],
            [
                'nome' => 'Veterinária',
                'imagem' => 'https://i.ibb.co/nsbkjDHt/veterin-ria.png'
            ],
            [
                'nome' => 'Caça e Pesca',
                'imagem' => 'https://i.ibb.co/p605fLGn/cacaepesca.png'
            ],
        ];

        foreach ($nichos as $nicho) {
            DB::table('nichos_empresa')->insert([
                'nome' => $nicho['nome'],
                'slug' => strtolower(str_replace(' ', '-', $nicho['nome'])),
                'imagem' => $nicho['imagem'],
                'ativo' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        // -----------------------------
        // Formas de pagamento
        // -----------------------------
        $formasPagamentos = [
            'Dinheiro',
            'Cartão de Crédito',
            'Cartão de Débito',
            'PIX',
            'Transferência Bancária',
        ];

        foreach ($formasPagamentos as $fp) {
            DB::table('formas_pagamentos')->insert([
                'nome' => $fp,
                'slug' => strtolower(str_replace(' ', '-', $fp)),
                'ativo' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        // -----------------------------
        // Unidades de medida
        // -----------------------------
        $unidades = [
            ['nome' => 'Unidade', 'sigla' => 'Un'],
            ['nome' => 'Pacote', 'sigla' => 'Pct'],
            ['nome' => 'Quilo', 'sigla' => 'KG'],
            ['nome' => 'Litro', 'sigla' => 'L'],
            ['nome' => 'Grama', 'sigla' => 'g'],
        ];

        foreach ($unidades as $u) {
            DB::table('unidades_medidas')->insert([
                'nome' => $u['nome'],
                'sigla' => $u['sigla'],
                'ativo' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        // -----------------------------
        // Permissões (executa seeder dedicada)
        // -----------------------------
        $this->call(PermissoesSeeder::class);

        // -----------------------------
        // Bairros de Uberlândia-MG
        // -----------------------------
        $this->call(UberlandiaBairrosSeeder::class);

        // -----------------------------
        // Menu do sidebar (painel lojista)
        // -----------------------------
        $this->call(SidebarMenuSeeder::class);

        // -----------------------------
        // Planos (assinatura faturamento)
        // -----------------------------
        $this->call(PlanosSeeder::class);

        // -----------------------------
        // FAQs (perguntas frequentes)
        // -----------------------------
        $this->call(FaqSeeder::class);

        // -----------------------------
        // Planilhas terceiros (importação de produtos)
        // -----------------------------
        $this->call(PlanilhaTerceirosSeeder::class);

        $this->command->info('✓ Seeder inicial do sistema executado com sucesso!');
    }
}
