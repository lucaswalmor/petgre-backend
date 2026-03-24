<?php

namespace App\Services\Usuario;

use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetMail;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UsuarioRecuperacaoSenhaService
{
    public function __construct(
        private EmailService $emailService
    ) {}

    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function enviarCodigo(Request $request): array
    {
        $request->validate([
            'email' => 'required|email|exists:usuarios,email',
        ]);

        $usuario = User::where('email', $request->email)->first();

        $token = PasswordReset::generateUniqueToken($request->email);

        PasswordReset::updateOrCreate(
            ['email' => $request->email],
            [
                'token'      => $token,
                'expires_at' => now()->addMinutes(15),
                'used_at'    => null,
            ]
        );

        try {
            $this->emailService->sendMailable($usuario->email, new PasswordResetMail($usuario, $token));

            return [
                'ok'   => true,
                'body' => [
                    'message' => 'Código de recuperação enviado para seu email',
                ],
            ];
        } catch (\Exception $e) {
            return [
                'ok'   => false,
                'http' => 500,
                'body' => [
                    'error'   => 'Erro ao enviar email',
                    'message' => 'Não foi possível enviar o código de recuperação. Tente novamente.',
                ],
            ];
        }
    }

    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function verificarCodigo(Request $request): array
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string|size:6',
        ]);

        $reset = PasswordReset::where('email', $request->email)
            ->where('token', $request->token)
            ->valid()
            ->first();

        if (! $reset) {
            return [
                'ok'   => false,
                'http' => 400,
                'body' => [
                    'error'   => 'Código inválido ou expirado',
                    'message' => 'Verifique o código ou solicite um novo.',
                ],
            ];
        }

        return [
            'ok'   => true,
            'body' => [
                'message' => 'Código verificado com sucesso',
                'valid'   => true,
            ],
        ];
    }

    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function alterarSenhaComToken(Request $request): array
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string|size:6',
            'senha' => 'required|string|min:8|confirmed',
        ]);

        $reset = PasswordReset::where('email', $request->email)
            ->where('token', $request->token)
            ->valid()
            ->first();

        if (! $reset) {
            return [
                'ok'   => false,
                'http' => 400,
                'body' => [
                    'error'   => 'Código inválido ou expirado',
                    'message' => 'Solicite um novo código de recuperação.',
                ],
            ];
        }

        $usuario = User::where('email', $request->email)->first();

        if (! $usuario) {
            return [
                'ok'   => false,
                'http' => 404,
                'body' => [
                    'error' => 'Usuário não encontrado',
                ],
            ];
        }

        $usuario->update([
            'password' => Hash::make($request->senha),
        ]);

        $reset->markAsUsed();

        try {
            $loginUrl = ((int) $usuario->tipo_cadastro === 1)
                ? 'https://app.petgre.com.br/'
                : 'https://painel.petgre.com.br/';
            $this->emailService->sendMailable($usuario->email, new PasswordChangedMail($usuario, $loginUrl));
        } catch (\Exception $e) {
            Log::error('Erro ao enviar email de confirmação de senha: ' . $e->getMessage());
        }

        return [
            'ok'   => true,
            'body' => [
                'message' => 'Senha alterada com sucesso',
            ],
        ];
    }
}
