<?php

namespace App\Services\Pedido;

use App\Models\EmpresaCupom;
use App\Models\SistemaCupom;
use App\Models\User;
use Illuminate\Http\Request;

class PedidoCupomValidacaoService
{
    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function validar(Request $request, User $usuario): array
    {
        $request->validate([
            'cupom_codigo' => 'required|string',
            'empresa_id'   => 'required|exists:empresas,id',
            'valor_compra' => 'required|numeric|min:0',
        ]);

        $codigo = $request->cupom_codigo;
        $empresaId = $request->empresa_id;
        $valorCompra = $request->valor_compra;

        $cupomEmpresa = EmpresaCupom::where('codigo', $codigo)
            ->where('empresa_id', $empresaId)
            ->first();

        if ($cupomEmpresa) {
            if (! $cupomEmpresa->isValido()) {
                return [
                    'ok'   => false,
                    'http' => 400,
                    'body' => [
                        'success' => false,
                        'error'   => 'Cupom inválido',
                        'message' => 'Este cupom não está mais válido.',
                    ],
                ];
            }

            if ($cupomEmpresa->usuarioJaUsou($usuario->id)) {
                return [
                    'ok'   => false,
                    'http' => 400,
                    'body' => [
                        'success' => false,
                        'error'   => 'Cupom já utilizado',
                        'message' => 'Você já utilizou este cupom anteriormente.',
                    ],
                ];
            }

            if ($cupomEmpresa->valor_minimo && $valorCompra < $cupomEmpresa->valor_minimo) {
                return [
                    'ok'   => false,
                    'http' => 400,
                    'body' => [
                        'success' => false,
                        'error'   => 'Valor insuficiente',
                        'message' => 'O valor mínimo para usar este cupom é R$ ' . number_format($cupomEmpresa->valor_minimo, 2, ',', '.'),
                    ],
                ];
            }

            $valorDesconto = $cupomEmpresa->calcularDesconto($valorCompra);

            return [
                'ok'   => true,
                'body' => [
                    'success' => true,
                    'cupom'   => [
                        'id'           => $cupomEmpresa->id,
                        'codigo'       => $cupomEmpresa->codigo,
                        'tipo'         => $cupomEmpresa->tipo,
                        'valor'        => $cupomEmpresa->valor,
                        'valor_minimo' => $cupomEmpresa->valor_minimo,
                        'tipo_cupom'   => 'empresa',
                        'empresa_id'   => $cupomEmpresa->empresa_id,
                    ],
                    'desconto' => [
                        'valor'           => $valorDesconto,
                        'valor_formatado' => 'R$ ' . number_format($valorDesconto, 2, ',', '.'),
                    ],
                    'total_com_desconto' => $valorCompra - $valorDesconto,
                    'total_formatado'    => 'R$ ' . number_format($valorCompra - $valorDesconto, 2, ',', '.'),
                ],
            ];
        }

        $cupomSistema = SistemaCupom::where('codigo', $codigo)->first();

        if ($cupomSistema) {
            if (! $cupomSistema->isValido()) {
                return [
                    'ok'   => false,
                    'http' => 400,
                    'body' => [
                        'success' => false,
                        'error'   => 'Cupom inválido',
                        'message' => 'Este cupom não está mais válido.',
                    ],
                ];
            }

            if ($cupomSistema->usuarioJaUsou($usuario->id)) {
                return [
                    'ok'   => false,
                    'http' => 400,
                    'body' => [
                        'success' => false,
                        'error'   => 'Cupom já utilizado',
                        'message' => 'Você já utilizou este cupom anteriormente.',
                    ],
                ];
            }

            if (! $cupomSistema->usuarioTemCupom($usuario->id)) {
                return [
                    'ok'   => false,
                    'http' => 400,
                    'body' => [
                        'success' => false,
                        'error'   => 'Cupom não disponível',
                        'message' => 'Este cupom não está disponível para você.',
                    ],
                ];
            }

            $valorDesconto = $cupomSistema->calcularDesconto($valorCompra);

            return [
                'ok'   => true,
                'body' => [
                    'success' => true,
                    'cupom'   => [
                        'id'         => $cupomSistema->id,
                        'codigo'     => $cupomSistema->codigo,
                        'tipo'       => $cupomSistema->tipo,
                        'valor'      => $cupomSistema->valor,
                        'tipo_cupom' => 'sistema',
                    ],
                    'desconto' => [
                        'valor'           => $valorDesconto,
                        'valor_formatado' => 'R$ ' . number_format($valorDesconto, 2, ',', '.'),
                    ],
                    'total_com_desconto' => $valorCompra - $valorDesconto,
                    'total_formatado'    => 'R$ ' . number_format($valorCompra - $valorDesconto, 2, ',', '.'),
                ],
            ];
        }

        return [
            'ok'   => false,
            'http' => 404,
            'body' => [
                'success' => false,
                'error'   => 'Cupom não encontrado',
                'message' => 'O cupom informado não existe ou não é válido para esta empresa.',
            ],
        ];
    }
}
