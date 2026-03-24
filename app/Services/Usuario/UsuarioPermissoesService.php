<?php

namespace App\Services\Usuario;

use App\Models\Permissao;
use App\Models\User;

class UsuarioPermissoesService
{
    /**
     * @param  array<int, mixed>  $permissoes
     * @return array<int, int>
     */
    public function extrairIds(array $permissoes): array
    {
        if (count($permissoes) > 0 && is_array($permissoes[0])) {
            return array_map(function ($permissao) {
                return isset($permissao['permissao_id']) ? (int) $permissao['permissao_id'] : (int) $permissao;
            }, $permissoes);
        }

        return array_map('intval', $permissoes);
    }

    /**
     * @param  array<int, mixed>  $permissoes
     */
    public function sincronizarFuncionarioComDashboard(User $usuario, array $permissoes): void
    {
        $permissoesIds = $this->extrairIds($permissoes);
        $dashboardPerm = Permissao::where('slug', 'dashboard.index')->first();
        if ($dashboardPerm && ! in_array($dashboardPerm->id, $permissoesIds, true)) {
            $permissoesIds[] = $dashboardPerm->id;
        }
        $usuario->permissoes()->sync($permissoesIds);
    }

    public function garantirApenasDashboard(User $usuario): void
    {
        $dashboardPerm = Permissao::where('slug', 'dashboard.index')->first();
        if ($dashboardPerm) {
            $usuario->permissoes()->sync([$dashboardPerm->id]);
        }
    }

    /**
     * @param  array<int, mixed>  $permissoes
     */
    public function sincronizar(User $usuario, array $permissoes): void
    {
        $usuario->permissoes()->sync($this->extrairIds($permissoes));
    }
}
