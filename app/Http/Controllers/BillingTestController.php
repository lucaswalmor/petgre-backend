<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UsuarioFaturamentoPedidos;
use App\Models\EmpresaFaturamento;
use App\Services\FaturamentoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingTestController extends Controller
{
    public function __construct(
        private FaturamentoService $faturamentoService
    ) {}

    /**
     * Simular que um usuário atingiu 30 pedidos e disparar assinatura
     */
    public function simulateBilling(Request $request)
    {
        $request->validate([
            'usuario_id' => 'required|exists:usuarios,id',
            'forcar_disparo' => 'boolean'
        ]);

        $usuarioId = $request->usuario_id;
        $forcarDisparo = $request->boolean('forcar_disparo', false);

        try {
            DB::beginTransaction();

            // Buscar ou criar registro de faturamento de pedidos
            $registro = UsuarioFaturamentoPedidos::firstOrCreate(
                ['usuario_id' => $usuarioId, 'mes_referencia' => now()->format('Y-m')],
                ['total_pedidos' => 0, 'assinatura_disparada' => false]
            );

            // Se não for para forçar, definir exatamente 30 pedidos
            if (!$forcarDisparo) {
                $registro->update([
                    'total_pedidos' => QTD_PEDIDOS_COBRAR,
                    'assinatura_disparada' => false
                ]);
            }

            // Buscar dados do usuário
            $usuario = User::find($usuarioId);
            $faturamento = EmpresaFaturamento::where('usuario_id', $usuarioId)->first();

            DB::commit();

            // Tentar disparar assinatura
            $resultado = [
                'usuario' => [
                    'id' => $usuario->id,
                    'nome' => $usuario->nome,
                    'email' => $usuario->email,
                ],
                'registro_pedidos' => [
                    'mes_referencia' => $registro->mes_referencia,
                    'total_pedidos' => $registro->total_pedidos,
                    'assinatura_disparada' => $registro->assinatura_disparada,
                ],
                'faturamento' => $faturamento ? [
                    'nome_titular' => $faturamento->nome_titular,
                    'cpf_cnpj' => $faturamento->cpf_cnpj,
                    'email' => $faturamento->email,
                    'asaas_customer_id' => $faturamento->asaas_customer_id,
                    'asaas_subscription_id' => $faturamento->asaas_subscription_id,
                    'assinatura_ativa' => $faturamento->assinatura_ativa,
                    'valor_atual' => $faturamento->valor_atual,
                ] : null,
                'constante_limite' => QTD_PEDIDOS_COBRAR,
            ];

            // Se deve disparar ou se atingiu o limite, tentar disparar
            if ($forcarDisparo || $registro->total_pedidos >= QTD_PEDIDOS_COBRAR) {
                try {
                    $this->faturamentoService->dispararAssinatura($usuarioId);

                    // Recarregar dados após tentativa
                    $registro->refresh();
                    $faturamento = EmpresaFaturamento::where('usuario_id', $usuarioId)->first();

                    $resultado['disparo_tentado'] = true;
                    $resultado['disparo_sucesso'] = !$registro->assinatura_disparada; // Se ainda não foi disparada, houve erro
                    $resultado['registro_pedidos'] = [
                        'mes_referencia' => $registro->mes_referencia,
                        'total_pedidos' => $registro->total_pedidos,
                        'assinatura_disparada' => $registro->assinatura_disparada,
                    ];
                    $resultado['faturamento'] = $faturamento ? [
                        'nome_titular' => $faturamento->nome_titular,
                        'cpf_cnpj' => $faturamento->cpf_cnpj,
                        'email' => $faturamento->email,
                        'asaas_customer_id' => $faturamento->asaas_customer_id,
                        'asaas_subscription_id' => $faturamento->asaas_subscription_id,
                        'assinatura_ativa' => $faturamento->assinatura_ativa,
                        'valor_atual' => $faturamento->valor_atual,
                    ] : null;
                } catch (\Exception $e) {
                    $resultado['disparo_tentado'] = true;
                    $resultado['disparo_erro'] = $e->getMessage();
                }
            } else {
                $resultado['disparo_tentado'] = false;
                $resultado['pedidos_faltando'] = QTD_PEDIDOS_COBRAR - $registro->total_pedidos;
            }

            return response()->json([
                'success' => true,
                'message' => 'Simulação de cobrança executada',
                'data' => $resultado
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erro na simulação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar status atual do faturamento de um usuário
     */
    public function checkBillingStatus(Request $request)
    {
        $request->validate([
            'usuario_id' => 'required|exists:usuarios,id'
        ]);

        $usuarioId = $request->usuario_id;
        $mesAtual = now()->format('Y-m');

        $usuario = User::find($usuarioId);
        $registro = UsuarioFaturamentoPedidos::where('usuario_id', $usuarioId)
            ->where('mes_referencia', $mesAtual)
            ->first();

        $faturamento = EmpresaFaturamento::where('usuario_id', $usuarioId)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'usuario' => [
                    'id' => $usuario->id,
                    'nome' => $usuario->nome,
                    'email' => $usuario->email,
                ],
                'registro_atual' => $registro ? [
                    'mes_referencia' => $registro->mes_referencia,
                    'total_pedidos' => $registro->total_pedidos,
                    'assinatura_disparada' => $registro->assinatura_disparada,
                ] : null,
                'faturamento' => $faturamento ? [
                    'nome_titular' => $faturamento->nome_titular,
                    'cpf_cnpj' => $faturamento->cpf_cnpj,
                    'email' => $faturamento->email,
                    'telefone' => $faturamento->telefone,
                    'asaas_customer_id' => $faturamento->asaas_customer_id,
                    'asaas_subscription_id' => $faturamento->asaas_subscription_id,
                    'assinatura_ativa' => $faturamento->assinatura_ativa,
                    'valor_atual' => $faturamento->valor_atual,
                    'data_ativacao' => $faturamento->data_ativacao,
                ] : null,
                'constante_limite' => QTD_PEDIDOS_COBRAR,
                'limite_atingido' => $registro ? $registro->total_pedidos >= QTD_PEDIDOS_COBRAR : false,
            ]
        ]);
    }

    /**
     * Resetar contadores de pedidos para teste
     */
    public function resetBillingCounters(Request $request)
    {
        $request->validate([
            'usuario_id' => 'required|exists:usuarios,id',
            'mes_referencia' => 'nullable|string|regex:/^\d{4}-\d{2}$/'
        ]);

        $usuarioId = $request->usuario_id;
        $mesReferencia = $request->mes_referencia ?: now()->format('Y-m');

        try {
            $registro = UsuarioFaturamentoPedidos::where('usuario_id', $usuarioId)
                ->where('mes_referencia', $mesReferencia)
                ->first();

            if ($registro) {
                $registro->update([
                    'total_pedidos' => 0,
                    'assinatura_disparada' => false
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Contadores resetados para teste',
                'data' => [
                    'usuario_id' => $usuarioId,
                    'mes_referencia' => $mesReferencia,
                    'total_pedidos' => 0,
                    'assinatura_disparada' => false
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao resetar contadores: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar usuários masters para teste
     */
    public function listMasters()
    {
        $masters = User::where('is_master', true)
            ->with(['usuarioEmpresas.empresa'])
            ->select('id', 'nome', 'email', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'nome' => $user->nome,
                    'email' => $user->email,
                    'empresas_count' => $user->usuarioEmpresas->count(),
                    'empresas' => $user->usuarioEmpresas->map(function ($ue) {
                        return [
                            'id' => $ue->empresa->id,
                            'nome_fantasia' => $ue->empresa->nome_fantasia,
                            'ativo' => $ue->empresa->ativo
                        ];
                    }),
                    'created_at' => $user->created_at->format('Y-m-d H:i:s')
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'masters' => $masters,
                'total' => $masters->count()
            ]
        ]);
    }

    /**
     * Testar configuração do Asaas
     */
    public function testAsaasConfig()
    {
        $asaasService = app(\App\Services\AsaasService::class);

        return response()->json([
            'success' => true,
            'data' => [
                'asaas_configurado' => $asaasService->isConfigured(),
                'base_url' => config('services.asaas.base_url'),
                'api_key_exists' => !empty(config('services.asaas.api_key')),
                'webhook_token_exists' => !empty(config('services.asaas.webhook_token'))
            ]
        ]);
    }
}