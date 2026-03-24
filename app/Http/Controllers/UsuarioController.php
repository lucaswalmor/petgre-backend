<?php

namespace App\Http\Controllers;

use App\Http\Requests\Usuarios\UsuarioStoreRequest;
use App\Http\Requests\Usuarios\UsuarioUpdateRequest;
use App\Services\Usuario\UsuarioAtualizacaoPainelService;
use App\Services\Usuario\UsuarioCadastroService;
use App\Services\Usuario\UsuarioConsultaPainelService;
use App\Services\Usuario\UsuarioListagemPainelService;
use App\Services\Usuario\UsuarioRecuperacaoSenhaService;
use App\Services\Usuario\UsuarioRemocaoPainelService;
use App\Services\Usuario\UsuarioSenhaPrimeiroLoginService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UsuarioController extends Controller
{
    public function __construct(
        private UsuarioListagemPainelService $listagemPainelService,
        private UsuarioCadastroService $cadastroService,
        private UsuarioConsultaPainelService $consultaPainelService,
        private UsuarioAtualizacaoPainelService $atualizacaoPainelService,
        private UsuarioRemocaoPainelService $remocaoPainelService,
        private UsuarioRecuperacaoSenhaService $recuperacaoSenhaService,
        private UsuarioSenhaPrimeiroLoginService $senhaPrimeiroLoginService,
    ) {}

    public function index(Request $request)
    {
        return response()->json($this->listagemPainelService->listarPaginado($request));
    }

    public function store(UsuarioStoreRequest $request)
    {
        $resultado = $this->cadastroService->criar($request);

        return response()->json($resultado['body'], $resultado['http']);
    }

    public function show(string $id)
    {
        $resultado = $this->consultaPainelService->obter($id, Auth::user());

        return response()->json($resultado['body'], $resultado['http'] ?? 200);
    }

    public function update(UsuarioUpdateRequest $request, string $id)
    {
        $resultado = $this->atualizacaoPainelService->atualizar($request, $id);

        return response()->json($resultado['body'], $resultado['http'] ?? 200);
    }

    public function destroy(string $id)
    {
        $resultado = $this->remocaoPainelService->remover($id, Auth::user());

        return response()->json($resultado['body'], $resultado['http'] ?? 200);
    }

    public function enviarCodigoRecuperacao(Request $request)
    {
        $resultado = $this->recuperacaoSenhaService->enviarCodigo($request);

        return response()->json($resultado['body'], $resultado['http'] ?? 200);
    }

    public function verificarCodigoRecuperacao(Request $request)
    {
        $resultado = $this->recuperacaoSenhaService->verificarCodigo($request);

        return response()->json($resultado['body'], $resultado['http'] ?? 200);
    }

    public function alterarSenhaPublico(Request $request)
    {
        $resultado = $this->recuperacaoSenhaService->alterarSenhaComToken($request);

        return response()->json($resultado['body'], $resultado['http'] ?? 200);
    }

    public function alterarSenhaPrimeiroLogin(Request $request)
    {
        $resultado = $this->senhaPrimeiroLoginService->alterar($request, Auth::user());

        return response()->json($resultado['body'], $resultado['http'] ?? 200);
    }
}
