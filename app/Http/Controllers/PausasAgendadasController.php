<?php

namespace App\Http\Controllers;

use App\Helpers\VerificaEmpresa;
use App\Http\Requests\PausaAgendada\PausaAgendadaStoreRequest;
use App\Http\Requests\PausaAgendada\PausaAgendadaUpdateRequest;
use App\Http\Resources\PausaAgendada\PausaAgendadaResource;
use App\Models\EmpresaPausaAgendada;
use Illuminate\Http\Request;

class PausasAgendadasController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->empresa_id;
        if (!$empresaId) {
            return response()->json(['success' => true, 'pausas' => []]);
        }

        $pausas = EmpresaPausaAgendada::where('empresa_id', $empresaId)
            ->orderBy('data_inicio', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'pausas' => PausaAgendadaResource::collection($pausas)->resolve(),
        ]);
    }

    public function store(PausaAgendadaStoreRequest $request)
    {
        $pausa = EmpresaPausaAgendada::create([
            'empresa_id' => $request->empresa_id,
            'data_inicio' => $request->data_inicio,
            'data_fim' => $request->data_fim,
            'motivo' => $request->motivo,
            'recorrente' => $request->boolean('recorrente'),
        ]);

        return response()->json([
            'success' => true,
            'pausa' => new PausaAgendadaResource($pausa),
        ], 201);
    }

    public function update(PausaAgendadaUpdateRequest $request, int $id)
    {
        $pausa = EmpresaPausaAgendada::findOrFail($id);
        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($pausa->empresa_id)) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $pausa->update([
            'data_inicio' => $request->data_inicio,
            'data_fim' => $request->data_fim,
            'motivo' => $request->motivo,
            'recorrente' => $request->boolean('recorrente'),
        ]);

        return response()->json([
            'success' => true,
            'pausa' => new PausaAgendadaResource($pausa->fresh()),
        ]);
    }

    public function destroy(int $id)
    {
        $pausa = EmpresaPausaAgendada::findOrFail($id);
        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($pausa->empresa_id)) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }
        $pausa->delete();
        return response()->json(['success' => true]);
    }
}
