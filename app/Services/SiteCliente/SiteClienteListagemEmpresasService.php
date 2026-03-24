<?php

namespace App\Services\SiteCliente;

use App\Http\Resources\SiteEmpresaResource;
use App\Models\Empresa;
use App\Models\NichosEmpresa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SiteClienteListagemEmpresasService
{
    public function listar(Request $request): array
    {
        Log::info($request->all());
        $query = Empresa::where('ativo', true)
            ->where('cadastro_completo', true)
            ->with(['nicho', 'horarios', 'pausasAgendadas', 'avaliacoes', 'bairrosEntregas.bairro', 'configuracoes'])
            ->withCount(['avaliacoes', 'empresaFavoritos'])
            ->withAvg('avaliacoes', 'nota');

        if ($request->has('nicho_id') && ! empty($request->nicho_id)) {
            $query->where('nicho_id', $request->nicho_id);
        }

        if ($request->has('q') && ! empty(trim($request->q))) {
            $query->where(function ($q) use ($request) {
                $q->where('nome_fantasia', 'like', '%' . $request->q . '%')
                    ->orWhere('razao_social', 'like', '%' . $request->q . '%');
            });
        }

        if ($request->has('cidade') && ! empty(trim($request->cidade))) {
            $query->whereHas('endereco', function ($q) use ($request) {
                $q->where('cidade', $request->cidade);
            });
        }

        if (! $request->has('cidade') && $request->has('bairro') && ! empty(trim($request->bairro))) {
            $query->whereHas('bairrosEntregas', function ($q) use ($request) {
                $q->whereHas('bairro', function ($qb) use ($request) {
                    $qb->where('nome', $request->bairro)
                        ->where('ativo', true);
                })
                    ->where('ativo', true);
            });
        }

        if ($request->has('abertas') && $request->abertas == 'true') {
            $agoraFiltro = now('America/Sao_Paulo');
            $mapaDia = [0 => 'domingo', 1 => 'segunda', 2 => 'terca', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sabado'];
            $diaSemanaTexto = $mapaDia[$agoraFiltro->dayOfWeek] ?? 'segunda';
            $horaAtualFiltro = $agoraFiltro->format('H:i:s');

            $query->whereHas('horarios', function ($q) use ($diaSemanaTexto, $horaAtualFiltro) {
                $q->where('dia_semana', $diaSemanaTexto)
                    ->where('horario_inicio', '<=', $horaAtualFiltro)
                    ->where('horario_fim', '>=', $horaAtualFiltro);
            });
        }

        if ($request->has('avaliacao_minima') && $request->avaliacao_minima > 0) {
            $query->having('avaliacoes_avg_nota', '>=', $request->avaliacao_minima);
        }

        if ($request->has('faz_entrega') && $request->faz_entrega == 'true') {
            $query->whereHas('configuracoes', function ($q) {
                $q->where('faz_entrega', true);
            });
        }

        if ($request->has('faz_retirada') && $request->faz_retirada == 'true') {
            $query->whereHas('configuracoes', function ($q) {
                $q->where('faz_retirada', true);
            });
        }

        if ($request->has('has_favoritos') && $request->has_favoritos == 'true') {
            if ($request->has('person') && ! empty($request->person)) {
                $usuario = User::find($request->person);
                if ($usuario) {
                    $favoritosIds = $usuario->empresaFavoritos()->pluck('empresa_id')->toArray();
                    if (! empty($favoritosIds)) {
                        $query->whereIn('id', $favoritosIds);
                    } else {
                        $query->where('id', 0);
                    }
                }
            } else {
                $query->where('id', 0);
            }
        }

        $ordenacao = $request->input('ordenacao', 'relevancia');
        if ($ordenacao === null || $ordenacao === '') {
            $ordenacao = 'relevancia';
        }
        switch ($ordenacao) {
            case 'avaliacao':
                $this->ordenarQueryEmpresasSiteCliente($query, 'avaliacao');
                break;
            case 'nome_asc':
                $this->ordenarQueryEmpresasSiteCliente($query, 'nome_asc');
                break;
            case 'nome_desc':
                $this->ordenarQueryEmpresasSiteCliente($query, 'nome_desc');
                break;
            default:
                $this->ordenarQueryEmpresasSiteCliente($query, 'relevancia');
                break;
        }

        $empresas = $query->paginate(20);
        $nichos = NichosEmpresa::where('ativo', true)->get(['id', 'nome', 'imagem', 'slug']);

        Log::info($empresas);
        Log::info($nichos);

        return [
            'success'   => true,
            'empresas'  => SiteEmpresaResource::collection($empresas),
            'nichos'    => $nichos,
            'paginacao' => [
                'total'            => $empresas->total(),
                'per_page'         => $empresas->perPage(),
                'current_page'     => $empresas->currentPage(),
                'last_page'        => $empresas->lastPage(),
                'has_more_pages'   => $empresas->hasMorePages(),
            ],
        ];
    }

    private function ordenarQueryEmpresasSiteCliente($query, string $modoSecundario): void
    {
        $agora = now('America/Sao_Paulo');
        $mapaDia = [0 => 'domingo', 1 => 'segunda', 2 => 'terca', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sabado'];
        $hojeTexto = $mapaDia[$agora->dayOfWeek] ?? 'segunda';
        $horaAtual = $agora->format('H:i:s');

        $sqlPrioridadeAberta = '(CASE
            WHEN empresas.fechada_manual = 1 THEN 0
            WHEN empresas.fechada_manual = 0 THEN 2
            WHEN EXISTS (
                SELECT 1 FROM empresa_horarios eh
                WHERE eh.empresa_id = empresas.id
                AND eh.dia_semana = ?
                AND eh.deleted_at IS NULL
                AND ? >= eh.horario_inicio
                AND ? <= eh.horario_fim
            ) THEN 1
            ELSE 0
        END)';

        $query->orderByRaw($sqlPrioridadeAberta . ' DESC', [$hojeTexto, $horaAtual, $horaAtual]);

        switch ($modoSecundario) {
            case 'avaliacao':
                $query->orderByDesc('avaliacoes_avg_nota');
                break;
            case 'nome_asc':
                $query->orderBy('empresas.nome_fantasia', 'asc')
                    ->orderByDesc('avaliacoes_avg_nota');
                break;
            case 'nome_desc':
                $query->orderBy('empresas.nome_fantasia', 'desc')
                    ->orderByDesc('avaliacoes_avg_nota');
                break;
            default:
                $query->orderByDesc('avaliacoes_avg_nota');
                break;
        }

        $query->orderByDesc('empresa_favoritos_count')
            ->orderBy('empresas.created_at', 'asc');
    }
}
