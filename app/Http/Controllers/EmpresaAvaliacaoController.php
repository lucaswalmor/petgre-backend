<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\EmpresaAvaliacao\EmpresaAvaliacaoStoreRequest;
use App\Http\Resources\EmpresaAvaliacao\EmpresaAvaliacaoResource;
use App\Models\EmpresaAvaliacao;
use App\Models\AvaliacaoModeracao;
use App\Models\Pedido;
use Illuminate\Support\Facades\Auth;
use App\Helpers\VerificaEmpresa;

class EmpresaAvaliacaoController extends Controller
{
    /**
     * Lista avaliações da empresa autenticada (sem dados identificadores do cliente).
     */
    public function index(Request $request)
    {
        $usuario     = Auth::user();
        $empresasIds = $usuario->empresas->pluck('id');

        $query = EmpresaAvaliacao::whereIn('empresa_id', $empresasIds)
            ->with(['pedido', 'moderacao']);

        if ($request->has('empresa_id') && $request->empresa_id) {
            if (VerificaEmpresa::verificaEmpresaPertenceAoUsuario((int) $request->empresa_id)) {
                $query->where('empresa_id', $request->empresa_id);
            }
        }

        if ($request->has('nota') && $request->nota) {
            $query->where('nota', $request->nota);
        }

        $orderBy        = $request->get('order_by', 'created_at');
        $orderDirection = $request->get('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        $perPage    = $request->get('per_page', 15);
        $avaliacoes = $query->paginate($perPage);

        return response()->json([
            'avaliacoes' => EmpresaAvaliacaoResource::collection($avaliacoes),
            'paginacao'  => [
                'total'         => $avaliacoes->total(),
                'per_page'      => $avaliacoes->perPage(),
                'current_page'  => $avaliacoes->currentPage(),
                'last_page'     => $avaliacoes->lastPage(),
                'from'          => $avaliacoes->firstItem(),
                'to'            => $avaliacoes->lastItem(),
                'has_more_pages'=> $avaliacoes->hasMorePages(),
            ],
        ]);
    }

    /**
     * Cliente cria uma avaliação para um pedido entregue.
     */
    public function store(EmpresaAvaliacaoStoreRequest $request)
    {
        $usuario   = Auth::user();
        $validacao = EmpresaAvaliacao::usuarioPodeAvaliarPedido($usuario->id, $request->pedido_id);

        if (!$validacao['pode']) {
            return response()->json([
                'success' => false,
                'error'   => 'Não é possível avaliar este pedido',
                'message' => $validacao['motivo'],
            ], 400);
        }

        $pedido = $validacao['pedido'];

        if ($request->has('empresa_id') && $request->empresa_id !== $pedido->empresa_id) {
            return response()->json([
                'success' => false,
                'error'   => 'Empresa inválida',
                'message' => 'A empresa informada não corresponde ao pedido.',
            ], 400);
        }

        $avaliacao = EmpresaAvaliacao::create([
            'empresa_id' => $pedido->empresa_id,
            'usuario_id' => $usuario->id,
            'pedido_id'  => $request->pedido_id,
            'descricao'  => $request->descricao,
            'nota'       => $request->nota,
        ]);

        $avaliacao->load(['pedido']);

        return response()->json([
            'success'   => true,
            'message'   => 'Avaliação criada com sucesso',
            'avaliacao' => new EmpresaAvaliacaoResource($avaliacao),
        ], 201);
    }

    /**
     * Detalhes de uma avaliação específica (sem dados do cliente).
     */
    public function show(string $id)
    {
        $avaliacao = EmpresaAvaliacao::with(['pedido', 'moderacao'])->findOrFail($id);

        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($avaliacao->empresa_id)) {
            return response()->json([
                'success' => false,
                'error'   => 'Acesso negado',
                'message' => 'Você não tem permissão para visualizar esta avaliação.',
            ], 403);
        }

        return response()->json([
            'avaliacao' => new EmpresaAvaliacaoResource($avaliacao),
        ]);
    }

    /**
     * Lojista solicita moderação de uma avaliação com conteúdo ofensivo.
     */
    public function solicitarModeracao(Request $request, string $id)
    {
        $request->validate([
            'motivo' => 'required|string|min:20|max:1000',
        ]);

        $avaliacao = EmpresaAvaliacao::findOrFail($id);

        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($avaliacao->empresa_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Você não tem permissão para solicitar moderação desta avaliação.',
            ], 403);
        }

        $jaExiste = AvaliacaoModeracao::where('avaliacao_id', $id)->exists();
        if ($jaExiste) {
            return response()->json([
                'success' => false,
                'message' => 'Já existe uma solicitação de moderação para esta avaliação.',
            ], 409);
        }

        $moderacao = AvaliacaoModeracao::create([
            'avaliacao_id' => $avaliacao->id,
            'empresa_id'   => $avaliacao->empresa_id,
            'motivo'       => $request->motivo,
            'status'       => AvaliacaoModeracao::STATUS_PENDENTE,
        ]);

        return response()->json([
            'success'   => true,
            'message'   => 'Solicitação de moderação enviada. Nossa equipe irá analisar em breve.',
            'moderacao' => [
                'id'     => $moderacao->id,
                'status' => $moderacao->status,
            ],
        ], 201);
    }

    /**
     * Rota pública: avaliações de uma empresa para exibição no site do cliente.
     * Retorna dados sem identificação do avaliador.
     */
    public function avaliacoesPorEmpresa(Request $request, string $empresaId)
    {
        $query = EmpresaAvaliacao::where('empresa_id', $empresaId)
            ->with(['pedido'])
            ->orderBy('created_at', 'desc');

        if ($request->has('nota') && $request->nota) {
            $query->where('nota', '>=', $request->nota);
        }

        if ($request->boolean('recentes')) {
            $query->where('created_at', '>=', now()->subDays(30));
        }

        $perPage    = $request->get('per_page', 10);
        $avaliacoes = $query->paginate($perPage);

        $estatisticas = [
            'total_avaliacoes'   => EmpresaAvaliacao::contarAvaliacoesEmpresa($empresaId),
            'media_nota'         => EmpresaAvaliacao::where('empresa_id', $empresaId)
                ->selectRaw('AVG(nota) as media')
                ->first()->media ?? 0,
            'distribuicao_notas' => EmpresaAvaliacao::where('empresa_id', $empresaId)
                ->selectRaw('nota, COUNT(*) as quantidade')
                ->groupBy('nota')
                ->pluck('quantidade', 'nota')
                ->toArray(),
        ];

        return response()->json([
            'empresa_id'  => $empresaId,
            'estatisticas'=> $estatisticas,
            'avaliacoes'  => EmpresaAvaliacaoResource::collection($avaliacoes),
            'paginacao'   => [
                'total'         => $avaliacoes->total(),
                'per_page'      => $avaliacoes->perPage(),
                'current_page'  => $avaliacoes->currentPage(),
                'last_page'     => $avaliacoes->lastPage(),
                'from'          => $avaliacoes->firstItem(),
                'to'            => $avaliacoes->lastItem(),
                'has_more_pages'=> $avaliacoes->hasMorePages(),
            ],
        ]);
    }
}
