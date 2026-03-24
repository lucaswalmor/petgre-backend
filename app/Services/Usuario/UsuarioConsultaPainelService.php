<?php

namespace App\Services\Usuario;

use App\Http\Resources\Usuario\UsuarioResource;
use App\Models\User;

class UsuarioConsultaPainelService
{
    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function obter(string $id, User $usuarioAutenticado): array
    {
        $usuarioAutenticado->load('empresas');
        $usuario = User::with(['permissoes', 'enderecos', 'empresas'])->findOrFail($id);

        $empresasUsuarioAutenticado = $usuarioAutenticado->empresas->pluck('id');
        $empresasUsuarioBuscado = $usuario->empresas->pluck('id');

        $temEmpresaComum = $empresasUsuarioAutenticado->intersect($empresasUsuarioBuscado)->isNotEmpty();

        if (! $temEmpresaComum) {
            return [
                'ok'   => false,
                'http' => 403,
                'body' => [
                    'error'   => 'Você não tem permissão para visualizar este usuário.',
                    'message' => 'O usuário não pertence à mesma empresa que você.',
                ],
            ];
        }

        if ($empresasUsuarioAutenticado->isEmpty() && $empresasUsuarioBuscado->isEmpty()) {
            return [
                'ok'   => false,
                'http' => 403,
                'body' => [
                    'error'   => 'Você não tem permissão para visualizar este usuário.',
                    'message' => 'Clientes não podem visualizar outros clientes.',
                ],
            ];
        }

        return [
            'ok'   => true,
            'body' => [
                'usuario' => new UsuarioResource($usuario),
            ],
        ];
    }
}
