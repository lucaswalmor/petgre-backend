<?php

namespace App\Services\Empresa;

use App\Helpers\VerificaEmpresa;
use App\Models\Empresa;
use Illuminate\Http\Request;

class EmpresaConsultaService
{
    public function __construct(
        private EmpresaCadastroProgressoService $cadastroProgresso
    ) {}

    /**
     * @return array{ok: true, modo: 'basic', body: array<string, mixed>}|array{ok: true, modo: 'full', empresa: Empresa, additionalData: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function obterDetalhe(Request $request, string $id): array
    {
        try {
            if (! VerificaEmpresa::verificaEmpresaPertenceAoUsuario((int) $id)) {
                return [
                    'ok'   => false,
                    'http' => 403,
                    'body' => [
                        'success' => false,
                        'error'   => 'Acesso negado',
                        'message' => 'Você não tem permissão para acessar esta empresa.',
                    ],
                ];
            }

            $basic = filter_var($request->query('basic', false), FILTER_VALIDATE_BOOLEAN);

            if ($basic) {
                $empresa = Empresa::findOrFail($id);

                return [
                    'ok'   => true,
                    'modo' => 'basic',
                    'body' => [
                        'success' => true,
                        'empresa' => [
                            'id'             => $empresa->id,
                            'razao_social'   => $empresa->razao_social,
                            'nome_fantasia'  => $empresa->nome_fantasia,
                            'slug'           => $empresa->slug,
                            'email'          => $empresa->email,
                            'telefone'       => $empresa->telefone,
                            'cnpj'           => $empresa->cnpj,
                            'ativo'          => $empresa->ativo,
                            'created_at'     => $empresa->created_at,
                            'updated_at'     => $empresa->updated_at,
                        ],
                    ],
                ];
            }

            $empresa = Empresa::with([
                'nicho',
                'endereco',
                'configuracoes',
                'horarios',
                'formasPagamentos.formaPagamento',
                'bairrosEntregas.bairro',
                'usuarios.usuario.permissoes',
                'filiais',
            ])->findOrFail($id);

            $additionalData = [];

            if (! $empresa->cadastro_completo) {
                $progresso = $this->cadastroProgresso->calcularProgressoCadastro($empresa);

                $additionalData['cadastro'] = [
                    'progresso_porcentagem' => $progresso['porcentagem'],
                    'itens_completos'       => $progresso['itens_completos'],
                    'total_itens'           => $progresso['total_itens'],
                    'itens_pendentes'       => $progresso['itens_pendentes'],
                    'completo'              => $progresso['completo'],
                ];

                if ($progresso['completo']) {
                    $this->cadastroProgresso->verificarCadastroCompleto($empresa);
                    $empresa->refresh();
                }
            }

            return [
                'ok'             => true,
                'modo'           => 'full',
                'empresa'        => $empresa,
                'additionalData' => $additionalData,
            ];
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return [
                'ok'   => false,
                'http' => 404,
                'body' => [
                    'success' => false,
                    'message' => 'Empresa não encontrada',
                ],
            ];
        } catch (\Exception $e) {
            return [
                'ok'   => false,
                'http' => 500,
                'body' => [
                    'success' => false,
                    'error'   => 'Erro interno do servidor',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }
}
