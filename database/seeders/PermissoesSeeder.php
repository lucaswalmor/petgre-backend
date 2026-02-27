<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissoesSeeder extends Seeder
{
    /**
     * Sincroniza permissões com o array abaixo: insere novas, atualiza nome das existentes,
     * e remove da tabela as que foram deletadas do array (fonte da verdade é o seeder).
     */
    public function run(): void
    {
        $timestamp = now();

        $permissoes = [
            // Administrador (chamados / suporte)
            ['nome' => 'Administrador',         'slug' => 'administrador'],

            // Sistema
            ['nome' => 'Acesso Total',           'slug' => 'sistema.acesso_total'],

            // Dashboard
            ['nome' => 'Ver Dashboard',          'slug' => 'dashboard.index'],

            // Produtos
            ['nome' => 'Listar Produtos',        'slug' => 'produtos.index'],
            ['nome' => 'Criar Produtos',         'slug' => 'produtos.store'],
            ['nome' => 'Visualizar Produto',     'slug' => 'produtos.show'],
            ['nome' => 'Editar Produtos',        'slug' => 'produtos.update'],
            ['nome' => 'Deletar Produtos',       'slug' => 'produtos.destroy'],
            ['nome' => 'Upload Imagem Produto',  'slug' => 'produtos.upload_image'],

            // Pedidos
            ['nome' => 'Listar Pedidos',         'slug' => 'pedidos.index'],
            ['nome' => 'Visualizar Pedido',      'slug' => 'pedidos.show'],
            ['nome' => 'Editar Pedidos',         'slug' => 'pedidos.update'],
            ['nome' => 'Deletar Pedidos',        'slug' => 'pedidos.destroy'],
            ['nome' => 'Cancelar Pedidos',       'slug' => 'pedidos.cancelar'],

            // Painel de Pedidos
            ['nome' => 'Acessar Painel de Pedidos', 'slug' => 'painel.pedidos'],
            ['nome' => 'Mover Pedidos no Painel',   'slug' => 'painel.mover_pedido'],

            // Cupons
            ['nome' => 'Listar Cupons',          'slug' => 'cupons.index'],
            ['nome' => 'Criar Cupons',           'slug' => 'cupons.store'],
            ['nome' => 'Visualizar Cupom',       'slug' => 'cupons.show'],
            ['nome' => 'Editar Cupons',          'slug' => 'cupons.update'],
            ['nome' => 'Deletar Cupons',         'slug' => 'cupons.destroy'],

            // Avaliações
            ['nome' => 'Listar Avaliações',      'slug' => 'avaliacoes.index'],
            ['nome' => 'Visualizar Avaliação',   'slug' => 'avaliacoes.show'],

            // Empresa
            ['nome' => 'Visualizar Empresa',     'slug' => 'empresas.show'],
            ['nome' => 'Editar Empresa',         'slug' => 'empresas.update'],
            ['nome' => 'Upload Imagem Empresa',  'slug' => 'empresas.upload_image'],
            ['nome' => 'Criar Filial',            'slug' => 'empresas.criar_filial'],

            // Usuários / Funcionários
            ['nome' => 'Listar Funcionários',    'slug' => 'usuarios.index'],
            ['nome' => 'Criar Funcionários',     'slug' => 'usuarios.store'],
            ['nome' => 'Visualizar Funcionário', 'slug' => 'usuarios.show'],
            ['nome' => 'Editar Funcionários',    'slug' => 'usuarios.update'],

            // Pausas agendadas
            ['nome' => 'Listar Pausas Agendadas',   'slug' => 'pausas_agendadas.index'],
            ['nome' => 'Criar Pausas Agendadas',   'slug' => 'pausas_agendadas.store'],
            ['nome' => 'Editar Pausas Agendadas',  'slug' => 'pausas_agendadas.update'],
            ['nome' => 'Deletar Pausas Agendadas', 'slug' => 'pausas_agendadas.destroy'],

            // Tickets (lojista - chamados)
            ['nome' => 'Listar Chamados',        'slug' => 'tickets.index'],
            ['nome' => 'Abrir Chamado',          'slug' => 'tickets.store'],
            ['nome' => 'Visualizar Chamado',     'slug' => 'tickets.show'],
            ['nome' => 'Responder Chamado',      'slug' => 'tickets.messages'],
        ];

        $slugsDoSeeder = array_column($permissoes, 'slug');

        foreach ($permissoes as $permissao) {
            $existe = DB::table('permissoes')->where('slug', $permissao['slug'])->exists();
            if ($existe) {
                DB::table('permissoes')->where('slug', $permissao['slug'])->update([
                    'nome' => $permissao['nome'],
                    'ativo' => true,
                    'updated_at' => $timestamp,
                ]);
            } else {
                DB::table('permissoes')->insert([
                    'nome' => $permissao['nome'],
                    'slug' => $permissao['slug'],
                    'ativo' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
                $this->command->info("Permissão criada: {$permissao['nome']}");
            }
        }

        $removidas = DB::table('permissoes')->whereNotIn('slug', $slugsDoSeeder)->get();
        if ($removidas->isNotEmpty()) {
            foreach ($removidas as $r) {
                DB::table('usuarios_permissoes')->where('permissao_id', $r->id)->delete();
                DB::table('permissoes')->where('id', $r->id)->delete();
                $this->command->warn("Permissão removida (não está mais no seeder): {$r->nome} ({$r->slug})");
            }
        }
    }
}
