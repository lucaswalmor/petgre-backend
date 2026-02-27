<?php

namespace App\Services;

use App\Models\SidebarMenu;
use App\Models\User;
use Illuminate\Support\Collection;

class SidebarMenuService
{
    /**
     * Retorna a árvore de menu do sidebar filtrada pelas permissões do usuário (apenas lojista).
     */
    public static function menuParaUsuario(User $user): array
    {
        if (!$user->isLojista()) {
            return [];
        }

        $itens = SidebarMenu::orderBy('ordem')->orderBy('id')->get();
        $permissionSlugs = $user->permissoes ? $user->permissoes->pluck('slug')->toArray() : [];
        $temAcessoTotal = in_array('sistema.acesso_total', $permissionSlugs, true);
        $temAdministrador = in_array('administrador', $permissionSlugs, true);

        if ($user->isMaster() || $temAcessoTotal) {
            // Master ou acesso_total: Chamados só para administrador; Meus Chamados só para quem NÃO é administrador
            $filtrados = $itens->filter(function ($item) use ($temAdministrador) {
                if ($item->chave === 'chamados') {
                    return $temAdministrador;
                }
                if ($item->chave === 'tickets') {
                    return !$temAdministrador;
                }
                return true;
            });
        } else {
            $filtrados = $itens->filter(function ($item) use ($permissionSlugs, $temAdministrador) {
                // Primeiro verifica se o usuário tem a permissão específica do item
                if ($item->permission_slug !== null) {
                    return in_array($item->permission_slug, $permissionSlugs, true);
                }

                // Para itens sem permission_slug, aplica regras especiais
                if ($item->chave === 'chamados') return $temAdministrador;
                if ($item->chave === 'tickets') return !$temAdministrador;
                return true;
            });
        }

        $arvore = self::montarArvore($filtrados, null);
        return self::removerPaisVazios($arvore);
    }

    private static function montarArvore(Collection $itens, ?int $parentId): array
    {
        $result = [];

        foreach ($itens as $item) {
            if ($item->parent_id !== $parentId) {
                continue;
            }
            $filhos = self::montarArvore($itens, (int) $item->id);
            $result[] = [
                'id' => $item->id,
                'chave' => $item->chave,
                'label' => $item->label,
                'path' => $item->path,
                'icon' => $item->icon,
                'children' => $filhos,
            ];
        }

        return $result;
    }

    /** Remove itens que são só pai (sem path) e ficaram sem filhos após o filtro. */
    private static function removerPaisVazios(array $arvore): array
    {
        $result = [];
        foreach ($arvore as $item) {
            $children = self::removerPaisVazios($item['children'] ?? []);
            if ($item['path'] !== null || count($children) > 0) {
                $item['children'] = $children;
                $result[] = $item;
            }
        }
        return $result;
    }
}
