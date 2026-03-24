<?php

namespace App\Services\Empresa;

use App\Helpers\VerificaEmpresa;
use App\Models\Bairro;
use App\Models\Empresa;
use App\Models\EmpresaFaturamento;
use App\Models\User;

class EmpresaVerificacaoCadastroService
{
    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function resumoVerificacaoCadastro(string $id): array
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

            $empresa = Empresa::with(['endereco', 'configuracoes', 'formasPagamentos', 'horarios', 'bairrosEntregas'])->findOrFail($id);

            $itens = [];
            $itens[] = ['chave' => 'endereco', 'label' => 'Endereço da empresa', 'ok' => (bool) $empresa->endereco];
            $itens[] = ['chave' => 'configuracoes', 'label' => 'Configurações da empresa', 'ok' => (bool) $empresa->configuracoes];
            $itens[] = ['chave' => 'whatsapp_pedidos', 'label' => 'WhatsApp para pedidos', 'ok' => $empresa->configuracoes ? ! empty($empresa->configuracoes->whatsapp_pedidos) : false];
            $itens[] = ['chave' => 'formas_pagamento', 'label' => 'Pelo menos uma forma de pagamento', 'ok' => ! $empresa->formasPagamentos->isEmpty()];
            $itens[] = ['chave' => 'horarios', 'label' => 'Horários de funcionamento', 'ok' => ! $empresa->horarios->isEmpty()];
            $itens[] = ['chave' => 'bairros_entrega', 'label' => 'Pelo menos um bairro de entrega', 'ok' => ! $empresa->bairrosEntregas->isEmpty()];

            if ($empresa->is_matriz) {
                $master = User::where('is_master', true)
                    ->whereHas('usuarioEmpresas', function ($q) use ($empresa) {
                        $q->where('empresa_id', $empresa->id);
                    })
                    ->first();

                $faturamentoOk = false;
                if ($master) {
                    $faturamento = EmpresaFaturamento::where('usuario_id', $master->id)->first();
                    $faturamentoOk = $faturamento && ! empty($faturamento->nome_titular) && ! empty($faturamento->cpf_cnpj);
                }
                $itens[] = ['chave' => 'dados_faturamento', 'label' => 'Dados de faturamento', 'ok' => $faturamentoOk];
            }

            $total = count($itens);
            $ok = collect($itens)->where('ok', true)->count();
            $percentual = $total > 0 ? (int) round(($ok / $total) * 100) : 0;
            $itensPendentes = collect($itens)->where('ok', false)->pluck('label')->values()->all();
            $cadastroCompleto = $percentual === 100;

            return [
                'ok'   => true,
                'body' => [
                    'success'           => true,
                    'cadastro_completo' => $cadastroCompleto,
                    'percentual'        => $percentual,
                    'itens_pendentes'   => $itensPendentes,
                    'empresa_id'        => $empresa->id,
                    'empresa_nome'      => $empresa->nome_fantasia ?? $empresa->razao_social,
                ],
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
                    'message' => 'Erro interno do servidor',
                    'error'   => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function bairrosDisponiveisParaEntrega(string $empresaId): array
    {
        try {
            if (! VerificaEmpresa::verificaEmpresaPertenceAoUsuario((int) $empresaId)) {
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

            $empresa = Empresa::with('endereco')->findOrFail($empresaId);

            if (! $empresa->endereco) {
                return [
                    'ok'   => false,
                    'http' => 400,
                    'body' => [
                        'success' => false,
                        'message' => 'Empresa não possui endereço cadastrado',
                    ],
                ];
            }

            $bairros = Bairro::where('cidade', $empresa->endereco->cidade)
                ->where('estado', $empresa->endereco->estado)
                ->orderBy('nome')
                ->get(['id', 'nome']);

            return [
                'ok'   => true,
                'body' => [
                    'success' => true,
                    'bairros' => $bairros,
                ],
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
