<?php

namespace App\Services\Usuario;

use App\Helpers\VerificaEmpresa;
use App\Http\Requests\Usuarios\UsuarioUpdateRequest;
use App\Http\Resources\Usuario\UsuarioResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UsuarioAtualizacaoPainelService
{
    public function __construct(
        private UsuarioPermissoesService $permissoesService
    ) {}

    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function atualizar(UsuarioUpdateRequest $request, string $id): array
    {
        $usuario = User::findOrFail($id);

        if (! VerificaEmpresa::verificaUsuariosMesmaEmpresa((int) $id)) {
            return [
                'ok'   => false,
                'http' => 403,
                'body' => [
                    'error'   => 'Acesso negado',
                    'message' => 'Você não tem permissão para editar este usuário.',
                ],
            ];
        }

        $updateData = $request->only(['nome', 'email', 'telefone', 'ativo']);

        if ($request->has('password') && $request->password) {
            $updateData['password'] = Hash::make($request->password);
        }

        $usuario->update($updateData);

        if ($request->has('permissoes') && is_array($request->permissoes)) {
            $this->permissoesService->sincronizar($usuario, $request->permissoes);
        }

        $usuario->load(['permissoes', 'enderecos', 'empresas']);

        return [
            'ok'   => true,
            'body' => [
                'message' => 'Usuário atualizado com sucesso',
                'usuario' => new UsuarioResource($usuario),
            ],
        ];
    }
}
