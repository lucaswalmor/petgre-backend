<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\EmpresaCupom\EmpresaCupomStoreRequest;
use App\Http\Requests\EmpresaCupom\EmpresaCupomUpdateRequest;
use App\Http\Resources\EmpresaCupom\EmpresaCupomResource;
use App\Http\Resources\EmpresaCupom\EmpresaCupomCollection;
use App\Models\EmpresaCupom;
use App\Models\EmpresaCupomUsado;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmpresaCuponsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $empresaId = $request->empresa_id;
        $query = EmpresaCupom::where('empresa_id', $empresaId)
            ->with('empresa');

        // Filtros opcionais
        if ($request->has('status') && $request->status) {
            if ($request->status === 'ativo') {
                $query->ativos();
            } elseif ($request->status === 'inativo') {
                $query->where('ativo', false);
            } elseif ($request->status === 'expirado') {
                $query->where('data_fim', '<', now());
            }
        }

        if ($request->has('tipo') && $request->tipo) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('q') && $request->q) {
            $searchTerm = $request->q;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('codigo', 'like', "%{$searchTerm}%");
            });
        }

        // Ordenação
        $orderBy = $request->get('order_by', 'created_at');
        $orderDirection = $request->get('order_direction', 'desc');
        $allowedOrderBy = ['codigo', 'tipo', 'valor', 'data_inicio', 'data_fim', 'created_at', 'updated_at'];

        if (!in_array($orderBy, $allowedOrderBy)) {
            $orderBy = 'created_at';
        }

        $query->orderBy($orderBy, $orderDirection);

        // Paginação
        $perPage = $request->get('per_page', 15);
        $cupons = $query->paginate($perPage);

        return new EmpresaCupomCollection($cupons);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(EmpresaCupomStoreRequest $request)
    {
        $empresaId = $request->empresa_id;
        DB::beginTransaction();
        try {
            $cupom = EmpresaCupom::create([
                'empresa_id' => $empresaId,
                'codigo' => $request->codigo,
                'tipo' => $request->tipo,
                'valor' => $request->valor,
                'valor_minimo' => $request->valor_minimo,
                'data_inicio' => $request->data_inicio,
                'data_fim' => $request->data_fim,
                'limite_uso' => $request->limite_uso,
                'ativo' => $request->ativo ?? true,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cupom criado com sucesso',
                'cupom' => new EmpresaCupomResource($cupom)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar cupom',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $empresaId = $request->empresa_id;
        $cupom = EmpresaCupom::where('empresa_id', $empresaId)
            ->with('empresa')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'cupom' => new EmpresaCupomResource($cupom)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EmpresaCupomUpdateRequest $request, string $id)
    {
        $empresaId = $request->empresa_id;
        $cupom = EmpresaCupom::where('empresa_id', $empresaId)->findOrFail($id);

        DB::beginTransaction();
        try {
            $updateData = [];

            if ($request->has('codigo')) $updateData['codigo'] = $request->codigo;
            if ($request->has('tipo')) $updateData['tipo'] = $request->tipo;
            if ($request->has('valor')) $updateData['valor'] = $request->valor;
            if ($request->has('valor_minimo')) $updateData['valor_minimo'] = $request->valor_minimo;
            if ($request->has('data_inicio')) $updateData['data_inicio'] = $request->data_inicio;
            if ($request->has('data_fim')) $updateData['data_fim'] = $request->data_fim;
            if ($request->has('limite_uso')) $updateData['limite_uso'] = $request->limite_uso;
            if ($request->has('ativo')) $updateData['ativo'] = $request->ativo;

            $cupom->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cupom atualizado com sucesso',
                'cupom' => new EmpresaCupomResource($cupom->fresh())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar cupom',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $empresaId = $request->empresa_id;
        $cupom = EmpresaCupom::where('empresa_id', $empresaId)->findOrFail($id);

        // Verificar se o cupom já foi usado
        $usos = $cupom->usos()->count();
        if ($usos > 0) {
            return response()->json([
                'success' => false,
                'error' => 'Cupom não pode ser excluído',
                'message' => "Este cupom já foi usado {$usos} vez(es) e não pode ser excluído."
            ], 400);
        }

        $cupom->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cupom excluído com sucesso'
        ]);
    }

    /**
     * Toggle status ativo/inativo do cupom
     */
    public function toggleAtivo(Request $request, string $id)
    {
        $empresaId = $request->empresa_id;
        $cupom = EmpresaCupom::where('empresa_id', $empresaId)->findOrFail($id);

        $cupom->update(['ativo' => !$cupom->ativo]);

        return response()->json([
            'success' => true,
            'message' => 'Status do cupom alterado com sucesso',
            'cupom' => new EmpresaCupomResource($cupom->fresh())
        ]);
    }

    /**
     * Ver usos do cupom
     */
    public function usos(string $id, Request $request)
    {
        $empresaId = $request->empresa_id;
        $cupom = EmpresaCupom::where('empresa_id', $empresaId)->findOrFail($id);

        $query = EmpresaCupomUsado::where('empresa_cupom_id', $cupom->id)
            ->with(['usuario', 'pedido']);

        // Filtros opcionais
        if ($request->has('data_inicio') && $request->data_inicio) {
            $query->where('created_at', '>=', $request->data_inicio . ' 00:00:00');
        }

        if ($request->has('data_fim') && $request->data_fim) {
            $query->where('created_at', '<=', $request->data_fim . ' 23:59:59');
        }

        // Ordenação
        $orderBy = $request->get('order_by', 'created_at');
        $orderDirection = $request->get('order_direction', 'desc');

        $query->orderBy($orderBy, $orderDirection);

        // Paginação
        $perPage = $request->get('per_page', 15);
        $usos = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'cupom' => [
                'id' => $cupom->id,
                'codigo' => $cupom->codigo,
                'usos_totais' => $cupom->usos()->count(),
            ],
            'usos' => $usos->map(function ($uso) {
                return [
                    'id' => $uso->id,
                    'usuario' => [
                        'id' => $uso->usuario->id,
                        'nome' => $uso->usuario->nome,
                        'email' => $uso->usuario->email,
                    ],
                    'pedido' => [
                        'id' => $uso->pedido->id,
                        'total' => $uso->pedido->total,
                        'status' => $uso->pedido->statusPedido->nome,
                    ],
                    'usado_em' => $uso->created_at->format('Y-m-d H:i:s'),
                ];
            }),
            'paginacao' => [
                'total' => $usos->total(),
                'per_page' => $usos->perPage(),
                'current_page' => $usos->currentPage(),
                'last_page' => $usos->lastPage(),
                'from' => $usos->firstItem(),
                'to' => $usos->lastItem(),
                'has_more_pages' => $usos->hasMorePages(),
            ]
        ]);
    }

    /**
     * Estatísticas dos cupons da empresa
     */
    public function estatisticas(Request $request)
    {
        $empresaId = $request->empresa_id;
        $estatisticas = [
            'total_cupons' => EmpresaCupom::where('empresa_id', $empresaId)->count(),
            'cupons_ativos' => EmpresaCupom::where('empresa_id', $empresaId)->ativos()->count(),
            'cupons_inativos' => EmpresaCupom::where('empresa_id', $empresaId)->where('ativo', false)->count(),
            'cupons_expirados' => EmpresaCupom::where('empresa_id', $empresaId)->where('data_fim', '<', now())->count(),
            'total_usos' => EmpresaCupomUsado::daEmpresa($empresaId)->count(),
            'usos_ultimos_30_dias' => EmpresaCupomUsado::daEmpresa($empresaId)->recentes(30)->count(),
            'valor_total_descontos' => EmpresaCupomUsado::daEmpresa($empresaId)
                ->join('pedidos', 'empresa_cupons_usados.pedido_id', '=', 'pedidos.id')
                ->sum('pedidos.desconto'),
        ];

        return response()->json([
            'success' => true,
            'estatisticas' => $estatisticas
        ]);
    }
}