<?php

namespace App\Services\SiteCliente;

use App\Http\Resources\SiteEmpresaResource;
use App\Models\Categorias;
use App\Models\Empresa;
use App\Models\EmpresaOrdenacaoListaLoja;
use App\Models\UsuarioLog;
use Illuminate\Support\Facades\Auth;

class SiteClienteEmpresaPublicaService
{
    public function obterPorSlug(string $slug): array
    {
        $empresa = Empresa::where('slug', $slug)
            ->where('ativo', true)
            ->where('cadastro_completo', true)
            ->with([
                'nicho',
                'endereco',
                'horarios',
                'pausasAgendadas',
                'bairrosEntregas.bairro',
                'produtos' => function ($query) {
                    $query->where('ativo', true)->with(['categoria', 'unidadeMedida']);
                },
                'kits' => function ($query) {
                    $query->where('ativo', true)->with(['itens.produto']);
                },
                'formasPagamentos.formaPagamento',
                'configuracoes',
                'avaliacoes' => function ($query) {
                    $query->with('usuario:id,nome')->latest()->limit(10);
                },
            ])
            ->firstOrFail();

        $categorias = Categorias::where('ativo', true)->get();

        $usuario = Auth::user();
        if ($usuario) {
            $lojaAberta = $empresa->isAberta();
            $acao = $lojaAberta ? 'acesso_loja_aberta' : 'acesso_loja_fechada';

            UsuarioLog::create([
                'usuario_id'       => $usuario->id,
                'empresa_id'       => $empresa->id,
                'acao'             => $acao,
                'dados_adicionais' => [
                    'horario_acesso' => now()->format('H:i:s'),
                    'dia_semana'     => now()->dayOfWeek,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }

        $ordenacao = EmpresaOrdenacaoListaLoja::where('empresa_id', $empresa->id)
            ->where('ativo', true)
            ->orderBy('ordem')
            ->pluck('secao')
            ->toArray();

        if (empty($ordenacao)) {
            $ordenacao = ['servicos', 'produtos', 'kits'];
        }

        return [
            'success'            => true,
            'empresa'            => new SiteEmpresaResource($empresa),
            'categorias'         => $categorias,
            'ordenacao_secoes'   => $ordenacao,
        ];
    }

    public function obterOrdenacaoSecoes(int $empresaId): array
    {
        $ordenacao = EmpresaOrdenacaoListaLoja::where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderBy('ordem')
            ->pluck('secao')
            ->toArray();

        if (empty($ordenacao)) {
            $ordenacao = ['servicos', 'produtos', 'kits'];
        }

        return [
            'success'   => true,
            'ordenacao' => $ordenacao,
        ];
    }
}
