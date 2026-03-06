<?php

namespace Database\Seeders;

use App\Models\SidebarMenu;
use Illuminate\Database\Seeder;

class SidebarMenuSeeder extends Seeder
{
    /**
     * Sincroniza itens do menu com o array: insere novos, atualiza existentes,
     * e remove da tabela os que foram deletados do array (fonte da verdade é o seeder).
     */
    public function run(): void
    {
        $itens = [
            ['chave' => 'dashboard', 'label' => 'Dashboard', 'path' => '/dashboard', 'icon' => 'pi pi-home', 'permission_slug' => null, 'parent_id' => null, 'ordem' => 1],
            ['chave' => 'painel.pedidos', 'label' => 'Painel de Pedidos', 'path' => '/dashboard/painel-pedidos', 'icon' => 'pi pi-th-large', 'permission_slug' => 'painel.pedidos', 'parent_id' => null, 'ordem' => 2],
            ['chave' => 'pedidos', 'label' => 'Pedidos', 'path' => '/dashboard/pedidos', 'icon' => 'pi pi-shopping-cart', 'permission_slug' => 'pedidos.index', 'parent_id' => null, 'ordem' => 3],
            ['chave' => 'avaliacoes', 'label' => 'Avaliações', 'path' => '/dashboard/avaliacoes', 'icon' => 'pi pi-star', 'permission_slug' => 'avaliacoes.index', 'parent_id' => null, 'ordem' => 4],
            ['chave' => 'tickets', 'label' => 'Meus Chamados', 'path' => '/dashboard/tickets', 'icon' => 'pi pi-ticket', 'permission_slug' => 'tickets.index', 'parent_id' => null, 'ordem' => 5],
            ['chave' => 'chamados', 'label' => 'Chamados', 'path' => '/dashboard/chamados', 'icon' => 'pi pi-headphones', 'permission_slug' => 'administrador', 'parent_id' => null, 'ordem' => 6],
            ['chave' => 'cadastros', 'label' => 'Cadastros', 'path' => null, 'icon' => 'pi pi-folder', 'permission_slug' => null, 'parent_id' => null, 'ordem' => 7],
            ['chave' => 'configuracoes', 'label' => 'Configurações', 'path' => null, 'icon' => 'pi pi-cog', 'permission_slug' => null, 'parent_id' => null, 'ordem' => 8],
        ];

        foreach ($itens as $item) {
            $this->sincronizarItem($item);
        }

        $cadastrosId = SidebarMenu::where('chave', 'cadastros')->value('id');
        $configuracoesId = SidebarMenu::where('chave', 'configuracoes')->value('id');

        $subItens = [
            ['chave' => 'cadastros.produtos', 'label' => 'Produtos', 'path' => '/dashboard/produtos', 'icon' => 'pi pi-shopping-bag', 'permission_slug' => 'produtos.index', 'parent_id' => $cadastrosId, 'ordem' => 1],
            ['chave' => 'cadastros.kits', 'label' => 'KITs', 'path' => '/dashboard/kits', 'icon' => 'pi pi-box', 'permission_slug' => 'kits.index', 'parent_id' => $cadastrosId, 'ordem' => 2],
            ['chave' => 'cadastros.cupons', 'label' => 'Cupons', 'path' => '/dashboard/cupons', 'icon' => 'pi pi-tag', 'permission_slug' => 'cupons.index', 'parent_id' => $cadastrosId, 'ordem' => 3],
            ['chave' => 'config.empresa', 'label' => 'Empresa', 'path' => '/dashboard/empresa', 'icon' => 'pi pi-building', 'permission_slug' => 'empresas.show', 'parent_id' => $configuracoesId, 'ordem' => 1],
            ['chave' => 'config.usuarios', 'label' => 'Usuários', 'path' => '/dashboard/users', 'icon' => 'pi pi-users', 'permission_slug' => 'usuarios.index', 'parent_id' => $configuracoesId, 'ordem' => 2],
            ['chave' => 'config.pausas-agendadas', 'label' => 'Pausas Agendadas', 'path' => '/dashboard/pausas-agendadas', 'icon' => 'pi pi-pause', 'permission_slug' => 'pausas_agendadas.index', 'parent_id' => $configuracoesId, 'ordem' => 3],
            ['chave' => 'config.dados-conta', 'label' => 'Dados da Conta', 'path' => '/dashboard/configuracao', 'icon' => 'pi pi-user', 'permission_slug' => null, 'parent_id' => $configuracoesId, 'ordem' => 4],
            ['chave' => 'config.faturamento', 'label' => 'Faturamento', 'path' => '/dashboard/faturamento', 'icon' => 'pi pi-credit-card', 'permission_slug' => null, 'parent_id' => $configuracoesId, 'ordem' => 5],
            ['chave' => 'config.whatsapp', 'label' => 'WhatsApp', 'path' => '/dashboard/whatsapp', 'icon' => 'pi pi-whatsapp', 'permission_slug' => null, 'parent_id' => $configuracoesId, 'ordem' => 6],
        ];

        foreach ($subItens as $item) {
            $this->sincronizarItem($item);
        }

        $chavesDoSeeder = array_merge(array_column($itens, 'chave'), array_column($subItens, 'chave'));
        $this->removerItensForaDoSeeder($chavesDoSeeder);
    }

    private function sincronizarItem(array $item): void
    {
        $existe = SidebarMenu::where('chave', $item['chave'])->first();
        if ($existe) {
            $existe->update($item);
        } else {
            SidebarMenu::create($item);
            $this->command->info("Menu criado: {$item['label']}");
        }
    }

    private function removerItensForaDoSeeder(array $chavesDoSeeder): void
    {
        $toDelete = SidebarMenu::whereNotIn('chave', $chavesDoSeeder)->get();
        while ($toDelete->isNotEmpty()) {
            $leaves = $toDelete->filter(fn ($row) => $toDelete->where('parent_id', $row->id)->isEmpty());
            $idsRemovidos = $leaves->pluck('id')->all();
            foreach ($leaves as $row) {
                $this->command->warn("Menu removido (não está mais no seeder): {$row->label} ({$row->chave})");
                $row->delete();
            }
            $toDelete = $toDelete->filter(fn ($row) => !in_array($row->id, $idsRemovidos));
        }
    }
}
