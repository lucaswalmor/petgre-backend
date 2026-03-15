<?php

namespace App\Http\Controllers;

use App\Models\EmpresaOrdenacaoListaLoja;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmpresaOrdenacaoController extends Controller
{
    /**
     * Obter ordenação da loja
     */
    public function getOrdenacao($empresaId)
    {
        $ordenacao = EmpresaOrdenacaoListaLoja::where('empresa_id', $empresaId)
            ->orderBy('ordem')
            ->get(['secao', 'ordem', 'ativo']);

        // Se não tiver configuração, retorna padrão (3 seções)
        if ($ordenacao->isEmpty()) {
            return response()->json([
                'data' => [
                    ['secao' => 'servicos', 'ordem' => 1, 'ativo' => true],
                    ['secao' => 'produtos', 'ordem' => 2, 'ativo' => true],
                    ['secao' => 'kits', 'ordem' => 3, 'ativo' => true]
                ]
            ]);
        }

        return response()->json(['data' => $ordenacao]);
    }

    /**
     * Salvar ordenação da loja
     */
    public function salvarOrdenacao(Request $request, $empresaId)
    {
        $validator = Validator::make($request->all(), [
            'secoes' => 'required|array|min:1',
            'secoes.*' => 'required|string|in:servicos,produtos,kits'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verifica se usuário tem permissão na empresa
        $empresa = Empresa::find($empresaId);
        if (!$empresa) {
            return response()->json(['error' => 'Empresa não encontrada'], 404);
        }

        $user = $request->user();
        $temVinculo = $user->empresas()->where('empresas.id', $empresaId)->exists();
        
        if (!$temVinculo && !$user->is_master) {
            return response()->json(['error' => 'Sem permissão'], 403);
        }

        try {
            EmpresaOrdenacaoListaLoja::salvarOrdenacao($empresaId, $request->secoes);
            
            return response()->json([
                'message' => 'Ordenação salva com sucesso',
                'data' => EmpresaOrdenacaoListaLoja::getOrdenacaoPadrao($empresaId)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao salvar ordenação'], 500);
        }
    }

    /**
     * Resetar para ordenação padrão
     */
    public function resetarOrdenacao(Request $request, $empresaId)
    {
        // Verifica permissão
        $empresa = Empresa::find($empresaId);
        if (!$empresa) {
            return response()->json(['error' => 'Empresa não encontrada'], 404);
        }

        $user = $request->user();
        $temVinculo = $user->empresas()->where('empresas.id', $empresaId)->exists();
        
        if (!$temVinculo && !$user->is_master) {
            return response()->json(['error' => 'Sem permissão'], 403);
        }

        try {
            // Desativa todas as configurações existentes
            EmpresaOrdenacaoListaLoja::where('empresa_id', $empresaId)
                ->update(['ativo' => false]);

            return response()->json([
                'message' => 'Ordenação resetada para padrão',
                'data' => ['produtos', 'kits']
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao resetar ordenação'], 500);
        }
    }
}