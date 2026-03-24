<?php

namespace App\Services\Usuario;

use App\Http\Resources\Usuario\UsuarioLoginResource;
use App\Mail\PasswordChangedMail;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuarioSenhaPrimeiroLoginService
{
    public function __construct(
        private EmailService $emailService
    ) {}

    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function alterar(Request $request, User $usuario): array
    {
        if (! $usuario->primeiro_login) {
            return [
                'ok'   => false,
                'http' => 403,
                'body' => [
                    'success' => false,
                    'message' => 'Esta ação é válida apenas no primeiro acesso.',
                ],
            ];
        }

        $request->validate([
            'senha' => 'required|string|min:8|confirmed',
        ], [
            'senha.required'  => 'A nova senha é obrigatória',
            'senha.min'       => 'A senha deve ter no mínimo 8 caracteres',
            'senha.confirmed' => 'As senhas não conferem',
        ]);

        $usuario->update([
            'password'       => Hash::make($request->senha),
            'primeiro_login' => false,
        ]);

        $loginUrl = ((int) $usuario->tipo_cadastro === 1)
            ? 'https://app.petgre.com.br/'
            : 'https://painel.petgre.com.br/';
        $this->emailService->sendMailable($usuario->email, new PasswordChangedMail($usuario, $loginUrl));

        $usuario->load(['permissoes', 'empresas', 'enderecos']);

        return [
            'ok'   => true,
            'body' => [
                'success' => true,
                'message' => 'Senha alterada com sucesso',
                'user'    => new UsuarioLoginResource($usuario),
            ],
        ];
    }
}
