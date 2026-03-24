<?php

namespace App\Http\Controllers;

use App\Http\Requests\Empresa\EmpresaStoreRequest;
use App\Http\Requests\Empresa\EmpresaUpdateRequest;
use App\Http\Requests\Empresa\EmpresaUploadImageRequest;
use App\Http\Resources\EmpresaResource;
use App\Http\Resources\Usuario\UsuarioResource;
use App\Services\Empresa\EmpresaAtualizacaoService;
use App\Services\Empresa\EmpresaConsultaService;
use App\Services\Empresa\EmpresaCriacaoService;
use App\Services\Empresa\EmpresaImagemService;
use App\Services\Empresa\EmpresaListagemService;
use App\Services\Empresa\EmpresaStatusLojaService;
use App\Services\Empresa\EmpresaVerificacaoCadastroService;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    public function __construct(
        private EmpresaListagemService $listagemService,
        private EmpresaCriacaoService $criacaoService,
        private EmpresaImagemService $imagemService,
        private EmpresaConsultaService $consultaService,
        private EmpresaAtualizacaoService $atualizacaoService,
        private EmpresaStatusLojaService $statusLojaService,
        private EmpresaVerificacaoCadastroService $verificacaoCadastroService,
    ) {}

    public function index()
    {
        return response()->json($this->listagemService->listarDoUsuarioAutenticado());
    }

    public function store(EmpresaStoreRequest $request)
    {
        if ($request->boolean('is_filial')) {
            $resultado = $this->criacaoService->criarFilial($request);
            if (! $resultado['ok']) {
                return response()->json($resultado['body'], $resultado['http']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Filial criada com sucesso',
                'empresa' => new EmpresaResource($resultado['empresa']),
            ], 201);
        }

        $resultado = $this->criacaoService->criarMatriz($request);
        if (! $resultado['ok']) {
            return response()->json($resultado['body'], $resultado['http']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Empresa criada com sucesso',
            'empresa' => new EmpresaResource($resultado['empresa']),
            'usuario' => new UsuarioResource($resultado['usuario']),
        ], 201);
    }

    public function uploadImage(EmpresaUploadImageRequest $request, string $id)
    {
        $resultado = $this->imagemService->atualizarLogoBanner($request, $id);
        if (! $resultado['ok']) {
            return response()->json($resultado['body'], $resultado['http']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Imagem(ns) atualizada(s) com sucesso',
            'empresa' => $resultado['empresa'],
        ]);
    }

    public function show(Request $request, string $id)
    {
        $resultado = $this->consultaService->obterDetalhe($request, $id);
        if (! $resultado['ok']) {
            return response()->json($resultado['body'], $resultado['http']);
        }

        if ($resultado['modo'] === 'basic') {
            return response()->json($resultado['body']);
        }

        return response()->json([
            'success' => true,
            'empresa' => new EmpresaResource($resultado['empresa'], $resultado['additionalData']),
        ]);
    }

    public function update(EmpresaUpdateRequest $request, string $id)
    {
        $resultado = $this->atualizacaoService->atualizar($request, $id);
        if (! $resultado['ok']) {
            return response()->json($resultado['body'], $resultado['http']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Empresa atualizada com sucesso',
            'empresa' => $resultado['empresa'],
        ]);
    }

    public function destroy(string $id)
    {
        //
    }

    public function status(Request $request, string $id)
    {
        $resultado = $this->statusLojaService->obterStatusAbertura($id);
        if (! $resultado['ok']) {
            return response()->json($resultado['body'], $resultado['http']);
        }

        return response()->json($resultado['body']);
    }

    public function statusManual(Request $request, string $id)
    {
        $resultado = $this->statusLojaService->definirFechamentoManual($request, $id);
        if (! $resultado['ok']) {
            return response()->json($resultado['body'], $resultado['http']);
        }

        return response()->json($resultado['body']);
    }

    public function verificarCadastro(Request $request, string $id)
    {
        $resultado = $this->verificacaoCadastroService->resumoVerificacaoCadastro($id);
        if (! $resultado['ok']) {
            return response()->json($resultado['body'], $resultado['http']);
        }

        return response()->json($resultado['body']);
    }

    public function bairrosDisponiveis(Request $request, string $empresaId)
    {
        $resultado = $this->verificacaoCadastroService->bairrosDisponiveisParaEntrega($empresaId);
        if (! $resultado['ok']) {
            return response()->json($resultado['body'], $resultado['http']);
        }

        return response()->json($resultado['body']);
    }
}
