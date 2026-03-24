<?php

namespace App\Services\Empresa;

use App\Helpers\FormatHelper;
use App\Helpers\VerificaEmpresa;
use App\Http\Resources\EmpresaResource;
use App\Models\Empresa;
use App\Http\Requests\Empresa\EmpresaUpdateRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmpresaAtualizacaoService
{
    public function __construct(
        private EmpresaCadastroProgressoService $cadastroProgresso
    ) {}

    /**
     * @return array{ok: true, empresa: EmpresaResource}|array{ok: false, http: int, body: array<string, mixed>}
     */
    public function atualizar(EmpresaUpdateRequest $request, string $id): array
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

            DB::beginTransaction();

            $empresa = Empresa::findOrFail($id);

            $dadosEmpresa = $request->all();
            if (isset($dadosEmpresa['nome_fantasia']) || isset($dadosEmpresa['razao_social'])) {
                $textoParaSlug = $dadosEmpresa['nome_fantasia'] ?? $empresa->nome_fantasia ?? $dadosEmpresa['razao_social'] ?? $empresa->razao_social;
                $slugBase = FormatHelper::formatSlug($textoParaSlug);
                $dadosEmpresa['slug'] = $slugBase;
                $slugExiste = fn ($s) => Empresa::where('slug', $s)->where('id', '!=', $empresa->id)->exists();
                if ($slugExiste($dadosEmpresa['slug'])) {
                    do {
                        $dadosEmpresa['slug'] = $slugBase . '-' . Str::random(8);
                    } while ($slugExiste($dadosEmpresa['slug']));
                }
            }

            if (isset($dadosEmpresa['telefone'])) {
                $dadosEmpresa['telefone'] = FormatHelper::formatOnlyNumbers($dadosEmpresa['telefone']);
            }

            if ($request->hasFile('path_banner')) {
                if ($empresa->path_banner) {
                    Storage::disk('public')->delete($empresa->path_banner);
                }
                $bannerPath = $request->file('path_banner')->store('empresas/banners', 'public');
                $dadosEmpresa['path_banner'] = $bannerPath;
            }

            $dadosBasicos = collect($dadosEmpresa)->only([
                'razao_social',
                'nome_fantasia',
                'slug',
                'email',
                'telefone',
                'cnpj',
                'path_logo',
                'path_banner',
                'nicho_id',
                'ativo',
            ])->toArray();

            $empresa->update($dadosBasicos);

            if (isset($dadosEmpresa['configuracoes'])) {
                $empresa->configuracoes()->updateOrCreate(
                    ['empresa_id' => $id],
                    $dadosEmpresa['configuracoes']
                );
            }

            if (isset($dadosEmpresa['horarios']) && is_array($dadosEmpresa['horarios'])) {
                $empresa->horarios()->delete();
                foreach ($dadosEmpresa['horarios'] as $horario) {
                    $horario['slug'] = FormatHelper::formatSlug($horario['dia_semana']);
                    $empresa->horarios()->create($horario);
                }
            }

            if (isset($dadosEmpresa['endereco'])) {
                $empresa->endereco()->updateOrCreate(
                    ['empresa_id' => $id],
                    $dadosEmpresa['endereco']
                );
            }

            if (isset($dadosEmpresa['formas_pagamento']) && is_array($dadosEmpresa['formas_pagamento'])) {
                $empresa->formasPagamentos()->delete();
                foreach ($dadosEmpresa['formas_pagamento'] as $forma) {
                    $empresa->formasPagamentos()->create($forma);
                }
            }

            if (isset($dadosEmpresa['bairros_entrega']) && is_array($dadosEmpresa['bairros_entrega'])) {
                $empresa->bairrosEntregas()->delete();
                foreach ($dadosEmpresa['bairros_entrega'] as $bairro) {
                    $empresa->bairrosEntregas()->create($bairro);
                }
            }

            if (! $empresa->cadastro_completo) {
                $empresa->refresh();
                $empresa->load(['endereco', 'configuracoes', 'formasPagamentos', 'horarios', 'bairrosEntregas']);
                $this->cadastroProgresso->verificarCadastroCompleto($empresa);
            }

            DB::commit();

            $empresa->refresh();
            $empresa->load(['configuracoes', 'horarios', 'formasPagamentos.formaPagamento', 'endereco', 'bairrosEntregas.bairro']);

            return [
                'ok'      => true,
                'empresa' => new EmpresaResource($empresa),
            ];
        } catch (\Exception $e) {
            DB::rollBack();

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
