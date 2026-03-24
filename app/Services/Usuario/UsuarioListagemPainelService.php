<?php

namespace App\Services\Usuario;

use App\Http\Resources\Usuario\UsuarioResource;
use App\Models\User;
use Illuminate\Http\Request;

class UsuarioListagemPainelService
{
    private const ORDER_BY_PERMITIDOS = [
        'created_at',
        'updated_at',
        'id',
        'nome',
        'email',
    ];

    public function listarPaginado(Request $request): array
    {
        $empresaId = $request->empresa_id;
        $query = User::whereHas('empresas', function ($q) use ($empresaId) {
            $q->where('empresas.id', $empresaId);
        })->with(['permissoes', 'enderecos', 'empresas']);

        if ($request->has('ativo') && $request->ativo !== null) {
            $query->where('ativo', $request->boolean('ativo'));
        }

        if ($request->has('is_master') && $request->is_master !== null) {
            $query->where('is_master', $request->boolean('is_master'));
        }

        if ($request->has('nome') && $request->nome) {
            $query->where('nome', 'like', '%' . $request->nome . '%');
        }

        if ($request->has('email') && $request->email) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }

        $orderBy = $request->get('order_by', 'created_at');
        if (! in_array($orderBy, self::ORDER_BY_PERMITIDOS, true)) {
            $orderBy = 'created_at';
        }
        $orderDirection = strtolower((string) $request->get('order_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($orderBy, $orderDirection);

        $perPage = (int) $request->get('per_page', 15);
        if ($perPage < 1) {
            $perPage = 15;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $usuarios = $query->paginate($perPage);

        return [
            'usuarios'  => UsuarioResource::collection($usuarios),
            'paginacao' => [
                'total'          => $usuarios->total(),
                'per_page'       => $usuarios->perPage(),
                'current_page'   => $usuarios->currentPage(),
                'last_page'      => $usuarios->lastPage(),
                'from'           => $usuarios->firstItem(),
                'to'             => $usuarios->lastItem(),
            ],
        ];
    }
}
