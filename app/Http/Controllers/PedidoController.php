<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pedido\PedidoStoreRequest;
use App\Http\Requests\Pedido\PedidoUpdateRequest;
use App\Services\Pedido\PedidoAtualizacaoPainelService;
use App\Services\Pedido\PedidoConsultaService;
use App\Services\Pedido\PedidoCriacaoClienteService;
use App\Services\Pedido\PedidoCupomValidacaoService;
use App\Services\Pedido\PedidoEstatisticasService;
use App\Services\Pedido\PedidoExclusaoService;
use App\Services\Pedido\PedidoListagemPainelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PedidoController extends Controller
{
    public function __construct(
        private PedidoEstatisticasService $estatisticasService,
        private PedidoListagemPainelService $listagemPainelService,
        private PedidoCriacaoClienteService $criacaoClienteService,
        private PedidoConsultaService $consultaService,
        private PedidoAtualizacaoPainelService $atualizacaoPainelService,
        private PedidoExclusaoService $exclusaoService,
        private PedidoCupomValidacaoService $cupomValidacaoService,
    ) {}

    public function estatisticas(Request $request)
    {
        return response()->json($this->estatisticasService->obterParaEmpresa($request));
    }

    public function index(Request $request)
    {
        return $this->listagemPainelService->listarPaginado($request);
    }

    public function store(PedidoStoreRequest $request)
    {
        $resultado = $this->criacaoClienteService->criar($request, Auth::user());
        if (! $resultado['ok']) {
            return response()->json($resultado['body'], $resultado['http']);
        }

        return response()->json($resultado['body'], $resultado['http']);
    }

    public function show(string $id)
    {
        $resultado = $this->consultaService->obterDetalhe($id, Auth::user());
        if (! $resultado['ok']) {
            return response()->json($resultado['body'], $resultado['http']);
        }

        return response()->json($resultado['body']);
    }

    public function update(PedidoUpdateRequest $request, string $id)
    {
        $resultado = $this->atualizacaoPainelService->atualizar($request, $id);
        if (! $resultado['ok']) {
            return response()->json($resultado['body'], $resultado['http']);
        }

        return response()->json($resultado['body']);
    }

    public function destroy(string $id)
    {
        $resultado = $this->exclusaoService->excluirSePendente($id);
        if (! $resultado['ok']) {
            return response()->json($resultado['body'], $resultado['http']);
        }

        return response()->json($resultado['body']);
    }

    public function validarCupom(Request $request)
    {
        $resultado = $this->cupomValidacaoService->validar($request, Auth::user());
        if (! $resultado['ok']) {
            return response()->json($resultado['body'], $resultado['http']);
        }

        return response()->json($resultado['body']);
    }
}
