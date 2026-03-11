<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Usuario\UsuarioLoginResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Realizar login do usuário
     */
    public function login(LoginRequest $request)
    {
        try {
            // Validar tipo_login
            if (!$request->has('tipo_login') || !in_array($request->tipo_login, ['lojista', 'cliente'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tipo de login inválido.',
                ], 400);
            }

            // Determinar o tipo_cadastro esperado baseado no tipo_login
            $tipoCadastroEsperado = $request->tipo_login === 'lojista' ? 0 : 1;

            // Buscar usuário pelo email E tipo_cadastro
            $user = User::where('email', $request->email)
                       ->where('tipo_cadastro', $tipoCadastroEsperado)
                       ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciais inválidas. Verifique seu email e senha.',
                ], 401);
            }

            if (!$user->ativo) {
                return response()->json([
                    'success' => false,
                    'conta_inativa' => true,
                    'email' => $user->email,
                    'message' => 'Sua conta está desativada. Deseja reativá-la?',
                ], 403);
            }

            // Carregar dados específicos baseado no tipo_login
            if ($request->tipo_login === 'lojista') {
                // Para lojistas, carregar permissões e empresas
                $user->load(['permissoes', 'empresas', 'enderecos']);
            } else {
                // Para clientes, carregar apenas endereços
                $user->load(['enderecos']);
            }

            // Criar novo token (não revogar tokens anteriores - permite acesso simultâneo)
            $token = $user->createToken('auth_token')->plainTextToken;

            $empresas = $request->tipo_login === 'lojista'
                ? $user->empresas->map(fn ($e) => [
                    'id' => $e->id,
                    'nome_fantasia' => $e->nome_fantasia,
                    'is_matriz' => (bool) $e->is_matriz,
                ])->values()->all()
                : [];

            return response()->json([
                'success' => true,
                'message' => 'Login realizado com sucesso',
                'user' => new UsuarioLoginResource($user),
                'token' => $token,
                'empresas' => $empresas,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Realizar logout do usuário
     */
    public function logout(Request $request)
    {
        try {
            // Revogar o token atual
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logout realizado com sucesso',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao fazer logout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter informações do usuário autenticado
     */
    public function user(Request $request)
    {
        try {
            $user = $request->user();

            // Carregar dados específicos baseado no tipo de usuário
            if ($user->isLojista()) {
                // Para lojistas, carregar permissões e empresas
                $user->load(['permissoes', 'empresas', 'enderecos']);
            } else {
                // Para clientes, carregar apenas endereços
                $user->load(['enderecos']);
            }

            return response()->json([
                'success' => true,
                'user' => new UsuarioLoginResource($user),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter informações do usuário',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reativar conta de cliente inativa
     */
    public function reativarConta(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            // Buscar usuário inativo do tipo cliente (tipo_cadastro = 1)
            $user = User::where('email', $request->email)
                       ->where('tipo_cadastro', 1)
                       ->where('ativo', false)
                       ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conta não encontrada ou já está ativa.',
                ], 404);
            }

            // Verificar senha
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Senha incorreta.',
                ], 401);
            }

            // Reativar conta
            $user->update(['ativo' => true]);

            // Carregar dados do usuário
            $user->load(['enderecos']);

            // Criar token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Conta reativada com sucesso!',
                'user' => new UsuarioLoginResource($user),
                'token' => $token,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao reativar conta',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}