<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\Kit\StoreKitRequest;
use App\Http\Requests\Kit\UpdateKitRequest;
use App\Http\Requests\Kit\KitUploadImageRequest;
use App\Http\Resources\Kit\KitResource;
use App\Http\Resources\Kit\KitCollection;
use App\Models\Kit;
use App\Models\KitItem;
use App\Models\Produto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Helpers\VerificaEmpresa;

class KitController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->empresa_id;
        $query = Kit::where('empresa_id', $empresaId)->with(['itens.produto']);

        if ($request->has('ativo') && $request->ativo !== null && $request->ativo !== '') {
            $query->where('ativo', $request->boolean('ativo'));
        }

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where('nome', 'like', "%{$term}%");
        }

        $orderBy = $request->get('order_by', 'created_at');
        $orderDirection = $request->get('order_direction', 'desc');
        $allowedOrder = ['nome', 'preco', 'ativo', 'created_at'];
        if (!in_array($orderBy, $allowedOrder)) {
            $orderBy = 'created_at';
        }
        $query->orderBy($orderBy, $orderDirection);

        $perPage = $request->get('per_page', 15);
        $kits = $query->paginate($perPage);

        return new KitCollection($kits);
    }

    public function store(StoreKitRequest $request)
    {
        $empresaId = $request->empresa_id;
        DB::beginTransaction();
        try {
            $kit = Kit::create([
                'empresa_id' => $empresaId,
                'nome' => $request->nome,
                'descricao' => $request->descricao,
                'preco' => $request->preco,
                'ativo' => $request->boolean('ativo', true),
            ]);

            foreach ($request->itens as $item) {
                KitItem::create([
                    'kit_id' => $kit->id,
                    'produto_id' => $item['produto_id'],
                    'quantidade' => (int) $item['quantidade'],
                ]);
            }

            DB::commit();
            $kit->load(['itens.produto']);

            return response()->json([
                'success' => true,
                'message' => 'Kit criado com sucesso',
                'kit' => new KitResource($kit),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar kit',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        $empresaId = $request->empresa_id;
        $kit = Kit::where('empresa_id', $empresaId)
            ->with(['itens.produto'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'kit' => new KitResource($kit),
        ]);
    }

    public function update(UpdateKitRequest $request, string $id)
    {
        $empresaId = $request->empresa_id;
        $kit = Kit::where('empresa_id', $empresaId)->findOrFail($id);

        DB::beginTransaction();
        try {
            $kit->update([
                'nome' => $request->nome,
                'descricao' => $request->descricao,
                'preco' => $request->preco,
                'ativo' => $request->boolean('ativo', $kit->ativo),
            ]);

            KitItem::where('kit_id', $kit->id)->delete();
            foreach ($request->itens as $item) {
                KitItem::create([
                    'kit_id' => $kit->id,
                    'produto_id' => $item['produto_id'],
                    'quantidade' => (int) $item['quantidade'],
                ]);
            }

            DB::commit();
            $kit->load(['itens.produto']);

            return response()->json([
                'success' => true,
                'message' => 'Kit atualizado com sucesso',
                'kit' => new KitResource($kit),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar kit',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, string $id)
    {
        $empresaId = $request->empresa_id;
        $kit = Kit::where('empresa_id', $empresaId)->findOrFail($id);
        $kit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kit excluído com sucesso',
        ]);
    }

    public function uploadImagem(KitUploadImageRequest $request, string $id)
    {
        try {
            $kit = Kit::findOrFail($id);
            if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($kit->empresa_id)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Acesso negado',
                    'message' => 'Você não tem permissão para acessar este kit.',
                ], 403);
            }

            if (!$request->hasFile('imagem')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhuma imagem enviada',
                ], 400);
            }

            if ($kit->imagem) {
                $pathRelativo = str_replace(env('CLOUDFLARE_R2_PUBLIC_URL') . '/', '', $kit->imagem);
                Storage::disk('r2')->delete($pathRelativo);
            }

            $path = $request->file('imagem')->store(
                "empresas/kits/{$kit->empresa_id}/{$kit->id}",
                'r2'
            );
            $kit->update(['imagem' => env('CLOUDFLARE_R2_PUBLIC_URL') . '/' . $path]);

            $kit->load(['itens.produto']);
            return response()->json([
                'success' => true,
                'message' => 'Imagem do kit atualizada com sucesso',
                'kit' => new KitResource($kit),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Kit não encontrado',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao enviar imagem',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function toggleAtivo(Request $request, string $id)
    {
        $empresaId = $request->empresa_id;
        $kit = Kit::where('empresa_id', $empresaId)->findOrFail($id);
        $kit->update(['ativo' => !$kit->ativo]);
        $kit->load(['itens.produto']);

        return response()->json([
            'success' => true,
            'message' => 'Status do kit alterado com sucesso',
            'kit' => new KitResource($kit),
        ]);
    }

    public function estatisticas(Request $request)
    {
        $empresaId = $request->empresa_id;
        $totalKits = Kit::where('empresa_id', $empresaId)->count();
        $kitsAtivos = Kit::where('empresa_id', $empresaId)->where('ativo', true)->count();
        $kitsInativos = Kit::where('empresa_id', $empresaId)->where('ativo', false)->count();

        $produtoMaisUsado = KitItem::whereHas('kit', fn ($q) => $q->where('empresa_id', $empresaId))
            ->select('produto_id', DB::raw('SUM(quantidade) as total'))
            ->groupBy('produto_id')
            ->orderByDesc('total')
            ->first();

        $produtoMaisUsadoNome = null;
        if ($produtoMaisUsado) {
            $p = Produto::find($produtoMaisUsado->produto_id);
            $produtoMaisUsadoNome = $p ? $p->nome : null;
        }

        return response()->json([
            'success' => true,
            'estatisticas' => [
                'total_kits' => $totalKits,
                'kits_ativos' => $kitsAtivos,
                'kits_inativos' => $kitsInativos,
                'produto_mais_usado_nome' => $produtoMaisUsadoNome,
            ],
        ]);
    }
}
