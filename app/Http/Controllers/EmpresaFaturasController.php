<?php

namespace App\Http\Controllers;

use App\Http\Resources\EmpresaFaturaResource;
use App\Models\EmpresaFatura;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class EmpresaFaturasController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();
        if (!$user->isMaster()) {
            return response()->json(['success' => false, 'message' => 'Acesso negado.'], 403);
        }

        $faturas = EmpresaFatura::where('usuario_id', $user->id)
            ->orderBy('vencimento', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'faturas' => EmpresaFaturaResource::collection($faturas),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user->isMaster()) {
            return response()->json(['success' => false, 'message' => 'Acesso negado.'], 403);
        }

        $fatura = EmpresaFatura::where('usuario_id', $user->id)->findOrFail($id);
        $fatura->setAttribute('incluir_pix_qrcode', true);

        return response()->json([
            'success' => true,
            'fatura' => new EmpresaFaturaResource($fatura),
        ]);
    }
}
