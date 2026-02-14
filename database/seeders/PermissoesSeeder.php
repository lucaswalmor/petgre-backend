<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissoesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timestamp = now();

        // Array de permissões - adicionar novas aqui conforme necessário
        $permissoes = [
            // Sistema
            ['nome' => 'Acesso Total', 'slug' => 'sistema.acesso_total'],

            // Usuários
            ['nome' => 'Listar Usuários', 'slug' => 'usuarios.index'],
            ['nome' => 'Criar Usuários', 'slug' => 'usuarios.store'],
            ['nome' => 'Visualizar Usuário', 'slug' => 'usuarios.show'],
            ['nome' => 'Editar Usuários', 'slug' => 'usuarios.update'],
            ['nome' => 'Deletar Usuários', 'slug' => 'usuarios.destroy'],

            // Empresas
            ['nome' => 'Visualizar Empresa', 'slug' => 'empresas.show'],
            ['nome' => 'Editar Empresas', 'slug' => 'empresas.update'],
            ['nome' => 'Upload de Imagem', 'slug' => 'empresas.upload_image'],

            // Produtos
            ['nome' => 'Listar Produtos', 'slug' => 'produtos.index'],
            ['nome' => 'Criar Produtos', 'slug' => 'produtos.store'],
            ['nome' => 'Visualizar Produto', 'slug' => 'produtos.show'],
            ['nome' => 'Editar Produtos', 'slug' => 'produtos.update'],
            ['nome' => 'Deletar Produtos', 'slug' => 'produtos.destroy'],
            ['nome' => 'Upload Imagem Produto', 'slug' => 'produtos.upload_image'],

            // Avaliações
            ['nome' => 'Listar Avaliações', 'slug' => 'avaliacoes.index'],
            ['nome' => 'Criar Avaliações', 'slug' => 'avaliacoes.store'],
            ['nome' => 'Visualizar Avaliação', 'slug' => 'avaliacoes.show'],
            ['nome' => 'Avaliações por Empresa', 'slug' => 'avaliacoes.empresa'],

            // Pedidos
            ['nome' => 'Listar Pedidos', 'slug' => 'pedidos.index'],
            ['nome' => 'Criar Pedidos', 'slug' => 'pedidos.store'],
            ['nome' => 'Visualizar Pedido', 'slug' => 'pedidos.show'],
            ['nome' => 'Editar Pedidos', 'slug' => 'pedidos.update'],
            ['nome' => 'Deletar Pedidos', 'slug' => 'pedidos.destroy'],
        ];

        foreach ($permissoes as $permissao) {
            // Verifica se a permissão já existe
            $existe = DB::table('permissoes')->where('slug', $permissao['slug'])->exists();

            if (!$existe) {
                DB::table('permissoes')->insert([
                    'nome' => $permissao['nome'],
                    'slug' => $permissao['slug'],
                    'ativo' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                $this->command->info("Permissão criada: {$permissao['nome']}");
            } else {
                $this->command->info("Permissão já existe: {$permissao['nome']}");
            }
        }
    }
}