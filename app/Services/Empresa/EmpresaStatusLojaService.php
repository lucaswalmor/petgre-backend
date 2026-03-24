<?php

namespace App\Services\Empresa;

use App\Helpers\VerificaEmpresa;
use App\Models\Empresa;
use Illuminate\Http\Request;

class EmpresaStatusLojaService
{
    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function obterStatusAbertura(string $id): array
    {
        try {
            if (! VerificaEmpresa::verificaEmpresaPertenceAoUsuario((int) $id)) {
                return ['ok' => false, 'http' => 403, 'body' => ['success' => false, 'message' => 'Acesso negado.']];
            }
            $empresa = Empresa::with(['horarios', 'pausasAgendadas'])->findOrFail($id);

            return [
                'ok'   => true,
                'body' => [
                    'success'          => true,
                    'empresa_aberta'   => $empresa->isAberta(),
                    'fechado_ate'      => $empresa->getFechadoAte(),
                    'fechada_manual'   => (bool) $empresa->fechada_manual,
                ],
            ];
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ['ok' => false, 'http' => 404, 'body' => ['success' => false, 'message' => 'Empresa não encontrada']];
        }
    }

    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function definirFechamentoManual(Request $request, string $id): array
    {
        $request->validate(['fechada_manual' => 'required|boolean']);
        try {
            if (! VerificaEmpresa::verificaEmpresaPertenceAoUsuario((int) $id)) {
                return ['ok' => false, 'http' => 403, 'body' => ['success' => false, 'message' => 'Acesso negado.']];
            }
            $empresa = Empresa::findOrFail($id);
            $empresa->update(['fechada_manual' => $request->boolean('fechada_manual')]);
            $empresa->load(['horarios', 'pausasAgendadas']);

            return [
                'ok'   => true,
                'body' => [
                    'success'          => true,
                    'empresa_aberta'   => $empresa->isAberta(),
                    'fechado_ate'      => $empresa->getFechadoAte(),
                    'fechada_manual'   => (bool) $empresa->fechada_manual,
                ],
            ];
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ['ok' => false, 'http' => 404, 'body' => ['success' => false, 'message' => 'Empresa não encontrada']];
        }
    }
}
