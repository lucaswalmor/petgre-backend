<?php

namespace App\Services\Empresa;

use App\Helpers\VerificaEmpresa;
use App\Http\Requests\Empresa\EmpresaUploadImageRequest;
use App\Http\Resources\EmpresaResource;
use App\Models\Empresa;
use Illuminate\Support\Facades\Storage;

class EmpresaImagemService
{
    /**
     * @return array{ok: true, empresa: EmpresaResource}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function atualizarLogoBanner(EmpresaUploadImageRequest $request, string $id): array
    {
        try {
            if (! VerificaEmpresa::verificaEmpresaPertenceAoUsuario((int) $id)) {
                return [
                    'ok'   => false,
                    'http' => 403,
                    'body' => [
                        'success' => false,
                        'error'   => 'Acesso negado',
                        'message' => 'Você não tem permissão para acessar esta empresa.',
                    ],
                ];
            }

            $empresa = Empresa::findOrFail($id);
            $tipo = $request->query('tipo');
            $dadosAtualizacao = [];

            if ($tipo === 'banner' && $request->hasFile('banner')) {
                if ($empresa->path_banner) {
                    $bannerPathRelativo = str_replace(env('CLOUDFLARE_R2_PUBLIC_URL') . '/', '', $empresa->path_banner);
                    Storage::disk('r2')->delete($bannerPathRelativo);
                }

                $bannerPath = $request->file('banner')->store("empresas/banners/{$id}/banner", 'r2');
                $dadosAtualizacao['path_banner'] = env('CLOUDFLARE_R2_PUBLIC_URL') . '/' . $bannerPath;
            } elseif ($tipo === 'logo' && $request->hasFile('logo')) {
                if ($empresa->path_logo) {
                    $logoPathRelativo = str_replace(env('CLOUDFLARE_R2_PUBLIC_URL') . '/', '', $empresa->path_logo);
                    Storage::disk('r2')->delete($logoPathRelativo);
                }

                $logoPath = $request->file('logo')->store("empresas/logos/{$id}/logo", 'r2');
                $dadosAtualizacao['path_logo'] = env('CLOUDFLARE_R2_PUBLIC_URL') . '/' . $logoPath;
            } elseif (! $tipo) {
                if ($request->hasFile('banner')) {
                    if ($empresa->path_banner) {
                        $bannerPathRelativo = str_replace(env('CLOUDFLARE_R2_PUBLIC_URL') . '/', '', $empresa->path_banner);
                        Storage::disk('r2')->delete($bannerPathRelativo);
                    }
                    $bannerPath = $request->file('banner')->store("empresas/banners/{$id}/banner", 'r2');
                    $dadosAtualizacao['path_banner'] = env('CLOUDFLARE_R2_PUBLIC_URL') . '/' . $bannerPath;
                }

                if ($request->hasFile('logo')) {
                    if ($empresa->path_logo) {
                        $logoPathRelativo = str_replace(env('CLOUDFLARE_R2_PUBLIC_URL') . '/', '', $empresa->path_logo);
                        Storage::disk('r2')->delete($logoPathRelativo);
                    }
                    $logoPath = $request->file('logo')->store("empresas/logos/{$id}/logo", 'r2');
                    $dadosAtualizacao['path_logo'] = env('CLOUDFLARE_R2_PUBLIC_URL') . '/' . $logoPath;
                }
            }

            if (! empty($dadosAtualizacao)) {
                $empresa->update($dadosAtualizacao);

                return [
                    'ok'      => true,
                    'empresa' => new EmpresaResource($empresa),
                ];
            }

            return [
                'ok'   => false,
                'http' => 400,
                'body' => [
                    'success' => false,
                    'message' => 'Nenhuma imagem foi enviada',
                ],
            ];
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return [
                'ok'   => false,
                'http' => 404,
                'body' => [
                    'success' => false,
                    'message' => 'Empresa não encontrada',
                ],
            ];
        } catch (\Exception $e) {
            return [
                'ok'   => false,
                'http' => 500,
                'body' => [
                    'success' => false,
                    'error'   => 'Erro interno do servidor',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }
}
