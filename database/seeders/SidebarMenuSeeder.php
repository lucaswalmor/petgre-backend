<?php

namespace Database\Seeders;

use App\Models\SidebarMenu;
use Illuminate\Database\Seeder;

class SidebarMenuSeeder extends Seeder
{
    /**
     * Cria itens do menu do sidebar. Itens com chave já existente são ignorados.
     */
    public function run(): void
    {
        $itens = [
            ['chave' => 'dashboard', 'label' => 'Dashboard', 'path' => '/dashboard', 'icon' => 'pi pi-home', 'permission_slug' => null, 'parent_id' => null, 'ordem' => 1],
            ['chave' => 'painel.pedidos', 'label' => 'Painel de Pedidos', 'path' => '/dashboard/painel-pedidos', 'icon' => 'pi pi-th-large', 'permission_slug' => 'painel.pedidos', 'parent_id' => null, 'ordem' => 2],
            ['chave' => 'pedidos', 'label' => 'Pedidos', 'path' => '/dashboard/pedidos', 'icon' => 'pi pi-shopping-cart', 'permission_slug' => 'pedidos.index', 'parent_id' => null, 'ordem' => 3],
            ['chave' => 'avaliacoes', 'label' => 'Avaliações', 'path' => '/dashboard/avaliacoes', 'icon' => 'pi pi-star', 'permission_slug' => 'avaliacoes.index', 'parent_id' => null, 'ordem' => 4],
            ['chave' => 'cadastros', 'label' => 'Cadastros', 'path' => null, 'icon' => 'pi pi-folder', 'permission_slug' => null, 'parent_id' => null, 'ordem' => 5],
            ['chave' => 'configuracoes', 'label' => 'Configurações', 'path' => null, 'icon' => 'pi pi-cog', 'permission_slug' => null, 'parent_id' => null, 'ordem' => 6],
        ];

        foreach ($itens as $item) {
            $this->criarSeNaoExistir($item);
        }

        $cadastrosId = SidebarMenu::where('chave', 'cadastros')->value('id');
        $configuracoesId = SidebarMenu::where('chave', 'configuracoes')->value('id');

        $subItens = [
            ['chave' => 'cadastros.produtos', 'label' => 'Produtos', 'path' => '/dashboard/produtos', 'icon' => 'pi pi-shopping-bag', 'permission_slug' => 'produtos.index', 'parent_id' => $cadastrosId, 'ordem' => 1],
            ['chave' => 'cadastros.cupons', 'label' => 'Cupons', 'path' => '/dashboard/cupons', 'icon' => 'pi pi-tag', 'permission_slug' => 'cupons.index', 'parent_id' => $cadastrosId, 'ordem' => 2],
            ['chave' => 'config.empresa', 'label' => 'Empresa', 'path' => '/dashboard/empresa', 'icon' => 'pi pi-building', 'permission_slug' => 'empresas.show', 'parent_id' => $configuracoesId, 'ordem' => 1],
            ['chave' => 'config.usuarios', 'label' => 'Usuários', 'path' => '/dashboard/users', 'icon' => 'pi pi-users', 'permission_slug' => 'usuarios.index', 'parent_id' => $configuracoesId, 'ordem' => 2],
            ['chave' => 'config.dados-conta', 'label' => 'Dados da Conta', 'path' => '/dashboard/configuracao', 'icon' => 'pi pi-user', 'permission_slug' => null, 'parent_id' => $configuracoesId, 'ordem' => 3],
        ];

        foreach ($subItens as $item) {
            $this->criarSeNaoExistir($item);
        }
    }

    private function criarSeNaoExistir(array $item): void
    {
        if (SidebarMenu::where('chave', $item['chave'])->exists()) {
            return;
        }
        SidebarMenu::create($item);
        $this->command->info("Menu criado: {$item['label']}");
    }
}
