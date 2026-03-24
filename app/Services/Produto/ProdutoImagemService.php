<?php

namespace App\Services\Produto;

use App\Helpers\VerificaEmpresa;
use App\Http\Requests\Produto\ProdutoUploadImageRequest;
use App\Http\Resources\Produto\ProdutoResource;
use App\Models\Produto;
use Illuminate\Support\Facades\Storage;

class ProdutoImagemService
{
    /**
     * @return array{ok: true, produto: ProdutoResource}|array{ok: false, http: int, success?: bool, error?: string, message: string}
     */
    public function atualizar(ProdutoUploadImageRequest $request, string $id): array
    {
        try {
            $produto = Produto::findOrFail($id);

            if (! VerificaEmpresa::verificaEmpresaPertenceAoUsuario($produto->empresa_id)) {
                return [
                    'ok'      => false,
                    'http'    => 403,
                    'success' => false,
                    'error'   => 'Acesso não permitido',
                    'message' => 'Você não tem acesso a este produto.',
                ];
            }

            $dadosAtualizacao = [];

            if ($request->hasFile('imagem')) {
                if ($produto->imagem) {
                    $imagemPathRelativo = str_replace(env('CLOUDFLARE_R2_PUBLIC_URL') . '/', '', $produto->imagem);
                    Storage::disk('r2')->delete($imagemPathRelativo);
                }

                $imagemPath = $request->file('imagem')->store("empresas/produtos/{$produto->empresa_id}/{$produto->id}/produto", 'r2');
                $dadosAtualizacao['imagem'] = env('CLOUDFLARE_R2_PUBLIC_URL') . '/' . $imagemPath;
            }

            if (! empty($dadosAtualizacao)) {
                $produto->update($dadosAtualizacao);

                return [
                    'ok'      => true,
                    'produto' => new ProdutoResource($produto->load(['categoria', 'unidadeMedida', 'empresa'])),
                ];
            }

            return [
                'ok'      => false,
                'http'    => 400,
                'success' => false,
                'message' => 'Nenhuma imagem foi enviada',
            ];
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return [
                'ok'      => false,
                'http'    => 404,
                'success' => false,
                'message' => 'Produto não encontrado',
            ];
        } catch (\Exception $e) {
            return [
                'ok'      => false,
                'http'    => 500,
                'success' => false,
                'error'   => 'Não foi possível processar',
                'message' => 'Tente novamente em alguns instantes.',
            ];
        }
    }
}
