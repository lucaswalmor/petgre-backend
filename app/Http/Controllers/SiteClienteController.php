<?php

namespace App\Http\Controllers;

use App\Services\SiteCliente\SiteClienteCatalogoProdutosService;
use App\Services\SiteCliente\SiteClienteEmpresaPublicaService;
use App\Services\SiteCliente\SiteClienteListagemEmpresasService;
use App\Services\SiteCliente\SiteClientePedidoClienteService;
use App\Services\SiteCliente\SiteClientePerfilContaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SiteClienteController extends Controller
{
    public function __construct(
        private SiteClienteListagemEmpresasService $listagemEmpresasService,
        private SiteClienteEmpresaPublicaService $empresaPublicaService,
        private SiteClientePedidoClienteService $pedidoClienteService,
        private SiteClientePerfilContaService $perfilContaService,
        private SiteClienteCatalogoProdutosService $catalogoProdutosService,
    ) {}

    public function getEmpresas(Request $request)
    {
        return response()->json($this->listagemEmpresasService->listar($request));
    }

    public function getEmpresa($slug)
    {
        return response()->json($this->empresaPublicaService->obterPorSlug($slug));
    }

    public function getOrdenacaoPublica($empresaId)
    {
        return response()->json($this->empresaPublicaService->obterOrdenacaoSecoes((int) $empresaId));
    }

    public function getPedidos(Request $request)
    {
        return response()->json($this->pedidoClienteService->listarPedidos(Auth::user()));
    }

    public function getPedido($id)
    {
        return response()->json($this->pedidoClienteService->obterPedido(Auth::user(), $id));
    }

    public function getPerfil()
    {
        return response()->json($this->perfilContaService->obterPerfil(Auth::user()));
    }

    public function getEnderecos()
    {
        return response()->json($this->perfilContaService->listarEnderecos(Auth::user()));
    }

    public function meusCupons()
    {
        return response()->json($this->perfilContaService->meusCupons(Auth::user()));
    }

    public function storePedido(Request $request)
    {
        $resultado = $this->pedidoClienteService->criarPedido($request, Auth::user());
        if (! $resultado['ok']) {
            return response()->json($resultado['body'], $resultado['http']);
        }

        return response()->json($resultado['body'], $resultado['http']);
    }

    public function atualizarPerfil(Request $request)
    {
        return response()->json($this->perfilContaService->atualizarPerfil($request, Auth::user()));
    }

    public function alterarSenha(Request $request)
    {
        $resultado = $this->perfilContaService->alterarSenha($request, Auth::user());
        if (! $resultado['ok']) {
            return response()->json($resultado['body'], $resultado['http']);
        }

        return response()->json($resultado['body']);
    }

    public function excluirConta()
    {
        $resultado = $this->perfilContaService->excluirConta(Auth::user());
        if (! $resultado['ok']) {
            return response()->json($resultado['body'], $resultado['http']);
        }

        return response()->json($resultado['body']);
    }

    public function getProdutos(Request $request)
    {
        return response()->json($this->catalogoProdutosService->listar($request));
    }
}
