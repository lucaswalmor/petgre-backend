<?php

namespace App\Services\Usuario;

use App\Helpers\VerificaEmpresa;
use App\Http\Requests\Usuarios\UsuarioStoreRequest;
use App\Http\Resources\Usuario\UsuarioResource;
use App\Mail\NovoClienteMail;
use App\Mail\NovoFuncionarioMail;
use App\Models\Empresa;
use App\Models\User;
use App\Models\UsuarioEmpresas;
use App\Models\UsuarioEnderecos;
use App\Services\EmailService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UsuarioCadastroService
{
    public function __construct(
        private UsuarioPermissoesService $permissoesService,
        private EmailService $emailService,
    ) {}

    /**
     * @return array{ok: true, http: int, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function criar(UsuarioStoreRequest $request): array
    {
        if ($request->has('empresa_id') && $request->empresa_id && Auth::check() && ! VerificaEmpresa::verificaEmpresaPertenceAoUsuario((int) $request->empresa_id)) {
            return [
                'ok'   => false,
                'http' => 403,
                'body' => [
                    'error'   => 'Acesso negado',
                    'message' => 'Você não tem permissão para criar funcionários nesta empresa.',
                ],
            ];
        }

        DB::beginTransaction();
        try {
            $isFuncionario = $request->has('empresa_id') && $request->empresa_id && $request->has('permissoes') && is_array($request->permissoes);
            $tipoCadastro = $isFuncionario ? 0 : 1;

            $senha = $isFuncionario ? Str::random(12) : $request->password;

            $usuario = User::create([
                'nome'           => $request->nome,
                'email'          => $request->email,
                'password'       => Hash::make($senha),
                'telefone'       => $request->telefone,
                'ativo'          => true,
                'is_master'      => false,
                'tipo_cadastro'  => $tipoCadastro,
                'primeiro_login' => $isFuncionario,
            ]);

            if ($request->has('empresa_id') && $request->empresa_id) {
                UsuarioEmpresas::create([
                    'usuario_id' => $usuario->id,
                    'empresa_id' => $request->empresa_id,
                ]);
            }

            if ($request->has('permissoes') && is_array($request->permissoes)) {
                $this->permissoesService->sincronizarFuncionarioComDashboard($usuario, $request->permissoes);
            } elseif ($isFuncionario) {
                $this->permissoesService->garantirApenasDashboard($usuario);
            }

            if ($isFuncionario) {
                $empresa = Empresa::with('endereco')->find($request->empresa_id);
                if ($empresa && $empresa->endereco) {
                    UsuarioEnderecos::create([
                        'usuario_id'       => $usuario->id,
                        'cep'              => $empresa->endereco->cep,
                        'rua'              => $empresa->endereco->logradouro,
                        'numero'           => $empresa->endereco->numero,
                        'complemento'      => $empresa->endereco->complemento,
                        'bairro'           => $empresa->endereco->bairro,
                        'cidade'           => $empresa->endereco->cidade,
                        'estado'           => $empresa->endereco->estado,
                        'ponto_referencia' => $empresa->endereco->ponto_referencia,
                        'observacoes'      => $empresa->endereco->observacoes,
                        'ativo'            => true,
                        'endereco_padrao'  => true,
                    ]);
                }
            } elseif ($request->has('endereco')) {
                $enderecoData = $request->endereco;
                UsuarioEnderecos::create([
                    'usuario_id'       => $usuario->id,
                    'cep'              => $enderecoData['cep'] ?? null,
                    'rua'              => $enderecoData['rua'],
                    'numero'           => $enderecoData['numero'],
                    'complemento'      => $enderecoData['complemento'] ?? null,
                    'bairro'           => $enderecoData['bairro'] ?? null,
                    'cidade'           => $enderecoData['cidade'] ?? null,
                    'estado'           => $enderecoData['estado'] ?? null,
                    'ponto_referencia' => $enderecoData['ponto_referencia'] ?? null,
                    'observacoes'      => $enderecoData['observacoes'] ?? null,
                    'ativo'            => true,
                    'endereco_padrao'  => true,
                ]);
            }

            DB::commit();

            try {
                if ($isFuncionario) {
                    $empresa = Empresa::find($request->empresa_id);
                    $this->emailService->sendMailable($usuario->email, new NovoFuncionarioMail($usuario, $empresa, $senha));
                } else {
                    $this->emailService->sendMailable($usuario->email, new NovoClienteMail($usuario));
                }
            } catch (\Exception $emailException) {
                Log::error('Erro ao enviar email de boas-vindas: ' . $emailException->getMessage());
            }

            $usuario->load(['permissoes', 'enderecos', 'empresas']);

            return [
                'ok'   => true,
                'http' => 201,
                'body' => [
                    'message' => 'Usuário criado com sucesso',
                    'usuario' => new UsuarioResource($usuario),
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'ok'   => false,
                'http' => 500,
                'body' => [
                    'error'   => 'Erro ao criar usuário',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }
}
