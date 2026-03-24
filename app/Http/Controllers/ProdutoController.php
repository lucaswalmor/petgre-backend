<?php

namespace App\Http\Controllers;

use App\Http\Requests\Produto\ProdutoLoteRequest;
use App\Http\Requests\Produto\ProdutoStoreRequest;
use App\Http\Requests\Produto\ProdutoUpdateRequest;
use App\Http\Requests\Produto\ProdutoUploadImageRequest;
use App\Http\Resources\Produto\ProdutoResource;
use App\Services\Produto\ProdutoCatalogoAuxiliarService;
use App\Services\Produto\ProdutoCrudService;
use App\Services\Produto\ProdutoImagemService;
use App\Services\Produto\ProdutoImportacaoPlanilhaService;
use App\Services\Produto\ProdutoListagemService;
use App\Services\Produto\ProdutoLoteService;
use App\Services\Produto\ProdutoOperacoesRapidasService;
use App\Services\Produto\ProdutoPromocaoService;
use Illuminate\Http\Request;

class ProdutoController extends Controller
{
    public function __construct(
        private ProdutoListagemService $listagemService,
        private ProdutoPromocaoService $promocaoService,
        private ProdutoCrudService $crudService,
        private ProdutoOperacoesRapidasService $operacoesRapidasService,
        private ProdutoImagemService $imagemService,
        private ProdutoLoteService $loteService,
        private ProdutoCatalogoAuxiliarService $catalogoAuxiliarService,
        private ProdutoImportacaoPlanilhaService $importacaoPlanilhaService,
    ) {}

    public function index(Request $request)
    {
        $payload = $this->listagemService->listarPaginado($request, (int) $request->empresa_id);

        return response()->json($payload);
    }

    public function calcularPromocao(Request $request)
    {
        $request->validate([
            'preco_original'     => 'required|numeric|min:0',
            'preco_promocional'   => 'nullable|numeric|min:0',
            'percentual'          => 'nullable|numeric|min:0|max:100',
        ]);

        $precoOriginal = (float) $request->preco_original;
        $precoPromocional = $request->has('preco_promocional') && $request->preco_promocional !== '' && $request->preco_promocional !== null
            ? (float) $request->preco_promocional
            : null;
        $percentual = $request->has('percentual') && $request->percentual !== '' && $request->percentual !== null
            ? (float) $request->percentual
            : null;

        $resultado = $this->promocaoService->calcular($precoOriginal, $precoPromocional, $percentual);

        return response()->json($resultado);
    }

    public function store(ProdutoStoreRequest $request)
    {
        try {
            $produto = $this->crudService->criar($request);

            return response()->json([
                'message' => 'Produto criado com sucesso',
                'produto' => new ProdutoResource($produto),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Não foi possível criar o produto',
                'message' => 'Verifique os dados e tente novamente.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        $produto = $this->crudService->obterDetalhe($id);

        if ($produto === null) {
            return response()->json([
                'error'   => 'Acesso não permitido',
                'message' => 'Você não tem acesso a este produto.',
            ], 403);
        }

        return response()->json([
            'produto' => new ProdutoResource($produto),
        ]);
    }

    public function update(ProdutoUpdateRequest $request, string $id)
    {
        try {
            $produto = $this->crudService->atualizar($request, $id);

            return response()->json([
                'message' => 'Produto atualizado com sucesso',
                'produto' => new ProdutoResource($produto),
            ]);
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() === 'acesso_negado') {
                return response()->json([
                    'error'   => 'Acesso não permitido',
                    'message' => 'Você não tem acesso a este produto.',
                ], 403);
            }
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Não foi possível atualizar o produto',
                'message' => 'Verifique os dados e tente novamente.',
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        $resultado = $this->crudService->excluir($id);

        if (! $resultado['ok']) {
            if ($resultado['codigo'] === 'acesso') {
                return response()->json([
                    'error'   => 'Acesso não permitido',
                    'message' => 'Você não tem acesso a este produto.',
                ], 403);
            }

            return response()->json([
                'error'   => 'Não foi possível excluir',
                'message' => 'Este produto está sendo usado em pedidos e não pode ser removido.',
            ], 400);
        }

        return response()->json([
            'success'    => true,
            'message'    => 'Produto deletado com sucesso',
            'produto_id' => $resultado['produto_id'],
        ]);
    }

    public function toggleDestaque(string $id)
    {
        $produto = $this->operacoesRapidasService->alternarDestaque($id);

        if ($produto === null) {
            return response()->json([
                'error'   => 'Acesso não permitido',
                'message' => 'Você não tem acesso a este produto.',
            ], 403);
        }

        return response()->json([
            'message' => 'Status de destaque alterado com sucesso',
            'produto' => new ProdutoResource($produto),
        ]);
    }

    public function toggleAtivo(string $id)
    {
        $produto = $this->operacoesRapidasService->alternarAtivo($id);

        if ($produto === null) {
            return response()->json([
                'error'   => 'Acesso não permitido',
                'message' => 'Você não tem acesso a este produto.',
            ], 403);
        }

        return response()->json([
            'message' => 'Status do produto alterado com sucesso',
            'produto' => new ProdutoResource($produto),
        ]);
    }

    public function search(Request $request)
    {
        $payload = $this->listagemService->buscar($request, (int) $request->empresa_id);

        return response()->json($payload);
    }

    public function uploadImage(ProdutoUploadImageRequest $request, string $id)
    {
        $resultado = $this->imagemService->atualizar($request, $id);

        if (! $resultado['ok']) {
            $body = ['message' => $resultado['message']];
            if (isset($resultado['success'])) {
                $body['success'] = $resultado['success'];
            }
            if (isset($resultado['error'])) {
                $body['error'] = $resultado['error'];
            }

            return response()->json($body, $resultado['http']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Imagem do produto atualizada com sucesso',
            'produto' => $resultado['produto'],
        ]);
    }

    public function duplicar(string $id)
    {
        try {
            $novoProduto = $this->operacoesRapidasService->duplicar($id);

            if ($novoProduto === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem acesso a este produto.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Produto duplicado com sucesso',
                'produto' => new ProdutoResource($novoProduto),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Não foi possível duplicar o produto. Tente novamente.',
            ], 500);
        }
    }

    public function storeLote(ProdutoLoteRequest $request)
    {
        $resultado = $this->loteService->cadastrarLote($request);

        if (! $resultado['ok']) {
            $body = ['success' => false, 'message' => $resultado['message']];
            if (isset($resultado['criados'])) {
                $body['criados'] = $resultado['criados'];
            }
            if (isset($resultado['erros'])) {
                $body['erros'] = $resultado['erros'];
            }

            return response()->json($body, $resultado['http']);
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Produtos cadastrados com sucesso.',
            'produtos' => $resultado['produtos'],
        ], 201);
    }

    public function listarCategorias()
    {
        return response()->json($this->catalogoAuxiliarService->listarCategorias());
    }

    public function destroyLote(Request $request)
    {
        $resultado = $this->loteService->excluirEmLote($request);

        if (! $resultado['ok']) {
            $body = ['message' => $resultado['message']];
            if (isset($resultado['error'])) {
                $body['error'] = $resultado['error'];
            }

            return response()->json($body, $resultado['http']);
        }

        return response()->json([
            'message' => $resultado['message'],
        ]);
    }

    public function listarUnidadesMedidas()
    {
        return response()->json($this->catalogoAuxiliarService->listarUnidadesMedidas());
    }

    public function listarTerceiros()
    {
        return response()->json($this->catalogoAuxiliarService->listarTerceiros());
    }

    public function importar(Request $request)
    {
        $resultado = $this->importacaoPlanilhaService->importar($request);

        if (! $resultado['ok']) {
            $body = ['message' => $resultado['message']];
            if (isset($resultado['error'])) {
                $body['error'] = $resultado['error'];
            }

            return response()->json($body, $resultado['http']);
        }

        return response()->json($resultado['payload']);
    }

    public function downloadModelo()
    {
        return $this->importacaoPlanilhaService->downloadModelo();
    }

    public function downloadPlanilhaErros()
    {
        $resultado = $this->importacaoPlanilhaService->resolverDownloadPlanilhaErros();

        if (! $resultado['ok']) {
            $body = ['message' => $resultado['message']];
            if (isset($resultado['error'])) {
                $body['error'] = $resultado['error'];
            }

            return response()->json($body, $resultado['http']);
        }

        return response()->download($resultado['path'], $resultado['filename']);
    }
}
