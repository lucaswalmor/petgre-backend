<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmpresaContext
{
    /**
     * Valida o header x-empresa-id e garante que o usuário possui vínculo
     * com a empresa. Em caso de sucesso faz merge de empresa_id no request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $empresaId = $request->header('x-empresa-id');

        if (empty($empresaId) || !is_numeric($empresaId)) {
            return response()->json([
                'success' => false,
                'message' => 'Header x-empresa-id é obrigatório e deve ser um ID válido.',
            ], 422);
        }

        $empresaId = (int) $empresaId;
        $user = $request->user();

        if (!$user || !$user->empresas()->where('empresas.id', $empresaId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Você não possui vínculo com esta empresa.',
            ], 403);
        }

        $request->merge(['empresa_id' => $empresaId]);

        return $next($request);
    }
}
