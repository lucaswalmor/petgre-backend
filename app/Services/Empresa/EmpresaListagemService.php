<?php

namespace App\Services\Empresa;

use App\Http\Resources\EmpresaResource;
use Illuminate\Support\Facades\Auth;

class EmpresaListagemService
{
    public function listarDoUsuarioAutenticado(): array
    {
        $usuario = Auth::user();
        $empresas = $usuario->usuarioEmpresas()
            ->with(['empresa.filiais'])
            ->get()
            ->pluck('empresa');

        return [
            'success'  => true,
            'empresas' => EmpresaResource::collection($empresas),
        ];
    }
}
