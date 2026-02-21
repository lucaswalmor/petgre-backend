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

        if ($user->isMaster()) {
            $filtrados = $itens;
        } else {
            $permissionSlugs = $user->permissoes->pluck('slug')->toArray();
            $filtrados = $itens->filter(function ($item) use ($permissionSlugs) {
                if ($item->permission_slug === null) {
                    return true;
                }
                return in_array($item->permission_slug, $permissionSlugs, true);
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
