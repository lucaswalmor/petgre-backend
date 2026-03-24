<?php

namespace App\Services\Usuario;

use App\Helpers\VerificaEmpresa;
use App\Models\User;

class UsuarioRemocaoPainelService
{
    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function remover(string $id, User $usuarioAutenticado): array
    {
        $usuario = User::findOrFail($id);

        if ($usuarioAutenticado->id === (int) $id) {
            return [
                'ok'   => false,
                'http' => 403,
                'body' => [
                    'error' => 'Não é possível deletar seu próprio usuário.',
                ],
            ];
        }

        if ($usuario->isMaster()) {
            return [
                'ok'   => false,
                'http' => 403,
                'body' => [
                    'error' => 'Não é possível deletar um usuário master.',
                ],
            ];
        }

        if (! VerificaEmpresa::verificaUsuariosMesmaEmpresa((int) $id)) {
            return [
                'ok'   => false,
                'http' => 403,
                'body' => [
                    'error'   => 'Acesso negado',
                    'message' => 'Você não tem permissão para deletar este usuário.',
                ],
            ];
        }

        if ($usuario->tipo_cadastro === 1) {
            $usuario->update(['ativo' => false]);

            return [
                'ok'   => true,
                'body' => [
                    'message' => 'Conta desativada com sucesso. Você pode reativá-la futuramente fazendo login.',
                ],
            ];
        }

        $usuario->delete();

        return [
            'ok'   => true,
            'body' => [
                'message' => 'Usuário deletado com sucesso',
            ],
        ];
    }
}
