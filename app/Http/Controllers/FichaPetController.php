<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FichaPet;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\FichaPet\FichaPetStoreRequest;
use App\Http\Requests\FichaPet\FichaPetUpdateRequest;

class FichaPetController extends Controller
{
    /**
     * Listar fichas do usuário logado para uma empresa
     */
    public function index(Request $request)
    {
        $usuario = Auth::user();
        $empresaId = $request->get('empresa_id');

        $query = FichaPet::where('usuario_id', $usuario->id);

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        $fichas = $query->orderBy('nome')->get();

        return response()->json([
            'success' => true,
            'fichas' => $fichas
        ]);
    }

    /**
     * Criar nova ficha de pet
     */
    public function store(FichaPetStoreRequest $request)
    {
        $usuario = Auth::user();

        $ficha = FichaPet::create([
            'usuario_id' => $usuario->id,
            'empresa_id' => $request->empresa_id,
            'nome' => $request->nome,
            'raca' => $request->raca,
            'porte' => $request->porte,
            'tamanho_pelagem' => $request->tamanho_pelagem,
            'idade' => $request->idade,
            'unidade_idade' => $request->unidade_idade ?? 'anos',
            'comportamento' => $request->comportamento,
            'alergias' => $request->alergias,
            'foto_url' => $request->foto_url
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ficha do pet criada com sucesso',
            'ficha' => $ficha
        ], 201);
    }

    /**
     * Mostrar detalhes de uma ficha
     */
    public function show($id)
    {
        $usuario = Auth::user();

        $ficha = FichaPet::where('id', $id)
            ->where('usuario_id', $usuario->id)
            ->first();

        if (!$ficha) {
            return response()->json([
                'success' => false,
                'message' => 'Ficha não encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'ficha' => $ficha
        ]);
    }

    /**
     * Atualizar ficha do pet
     */
    public function update(FichaPetUpdateRequest $request, $id)
    {
        $usuario = Auth::user();

        $ficha = FichaPet::where('id', $id)
            ->where('usuario_id', $usuario->id)
            ->first();

        if (!$ficha) {
            return response()->json([
                'success' => false,
                'message' => 'Ficha não encontrada'
            ], 404);
        }

        $ficha->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Ficha atualizada com sucesso',
            'ficha' => $ficha
        ]);
    }

    /**
     * Remover ficha do pet
     */
    public function destroy($id)
    {
        $usuario = Auth::user();

        $ficha = FichaPet::where('id', $id)
            ->where('usuario_id', $usuario->id)
            ->first();

        if (!$ficha) {
            return response()->json([
                'success' => false,
                'message' => 'Ficha não encontrada'
            ], 404);
        }

        $ficha->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ficha removida com sucesso'
        ]);
    }
}
