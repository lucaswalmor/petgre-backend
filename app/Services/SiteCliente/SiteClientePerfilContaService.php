<?php

namespace App\Services\SiteCliente;

use App\Http\Resources\Usuario\UsuarioResource;
use App\Models\User;
use App\Models\UsuarioCupom;
use App\Models\UsuarioEnderecos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SiteClientePerfilContaService
{
    public function obterPerfil(User $usuario): array
    {
        $usuario->load(['enderecos', 'empresas']);

        return [
            'success' => true,
            'usuario' => new UsuarioResource($usuario),
        ];
    }

    public function listarEnderecos(User $usuario): array
    {
        $enderecos = UsuarioEnderecos::where('usuario_id', $usuario->id)
            ->where('ativo', true)
            ->get();

        return [
            'success'   => true,
            'enderecos' => $enderecos,
        ];
    }

    public function meusCupons(User $usuario): array
    {
        $cuponsSistema = UsuarioCupom::where('usuario_id', $usuario->id)
            ->naoUtilizados()
            ->with('cupom')
            ->get();

        return [
            'success' => true,
            'cupons'  => $cuponsSistema,
        ];
    }

    public function atualizarPerfil(Request $request, User $usuario): array
    {
        $request->validate([
            'nome'     => 'required|string|min:3|max:255',
            'telefone' => [
                'nullable',
                'string',
                'max:20',
                function ($attribute, $value, $fail) use ($usuario) {
                    if (empty($value)) {
                        return;
                    }

                    $exists = User::where('telefone', $value)
                        ->where('tipo_cadastro', 1)
                        ->where('id', '!=', $usuario->id)
                        ->exists();

                    if ($exists) {
                        $fail('Este telefone já está sendo usado por outro cliente.');
                    }
                },
            ],
        ]);

        $usuario->update([
            'nome'     => $request->nome,
            'telefone' => $request->telefone,
        ]);

        return [
            'success' => true,
            'message' => 'Perfil atualizado com sucesso',
            'usuario' => new UsuarioResource($usuario),
        ];
    }

    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function alterarSenha(Request $request, User $usuario): array
    {
        $request->validate([
            'senha_atual'       => 'required|string',
            'senha_nova'        => 'required|string|min:8|different:senha_atual',
            'senha_confirmacao' => 'required|string|same:senha_nova',
        ], [
            'senha_atual.required'       => 'A senha atual é obrigatória',
            'senha_nova.required'        => 'A nova senha é obrigatória',
            'senha_nova.min'             => 'A nova senha deve ter no mínimo 8 caracteres',
            'senha_nova.different'       => 'A nova senha deve ser diferente da senha atual',
            'senha_confirmacao.required' => 'A confirmação da senha é obrigatória',
            'senha_confirmacao.same'     => 'As senhas não conferem',
        ]);

        if (! Hash::check($request->senha_atual, $usuario->password)) {
            return [
                'ok'   => false,
                'http' => 401,
                'body' => [
                    'success' => false,
                    'message' => 'Senha atual incorreta',
                ],
            ];
        }

        $usuario->update([
            'password' => Hash::make($request->senha_nova),
        ]);

        return [
            'ok'   => true,
            'body' => [
                'success' => true,
                'message' => 'Senha alterada com sucesso',
            ],
        ];
    }

    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function excluirConta(User $usuario): array
    {
        if ($usuario->tipo_cadastro !== 1) {
            return [
                'ok'   => false,
                'http' => 403,
                'body' => [
                    'success' => false,
                    'error'   => 'Acesso negado',
                    'message' => 'Esta rota é exclusiva para clientes.',
                ],
            ];
        }

        $usuario->update(['ativo' => false]);

        return [
            'ok'   => true,
            'body' => [
                'success' => true,
                'message' => 'Conta desativada com sucesso. Você pode reativá-la futuramente fazendo login.',
            ],
        ];
    }
}
