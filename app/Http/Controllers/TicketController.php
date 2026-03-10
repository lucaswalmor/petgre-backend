<?php

namespace App\Http\Controllers;

use App\Http\Requests\Ticket\TicketMessageRequest;
use App\Http\Requests\Ticket\TicketStoreRequest;
use App\Mail\NovoTicketMail;
use App\Models\Ticket;
use App\Models\TicketMensagem;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function __construct(private EmailService $emailService)
    {
    }

    /**
     * Lista tickets da empresa do usuário (qualquer usuário da empresa vê os tickets da empresa).
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $empresaIds = $user->empresas()->pluck('empresas.id');
        if ($empresaIds->isEmpty()) {
            return response()->json(['success' => true, 'tickets' => [], 'meta' => ['total' => 0]]);
        }
        $query = Ticket::with(['criadoPor:id,nome,email', 'mensagens' => fn ($q) => $q->latest()->limit(1)])
            ->whereIn('empresa_id', $empresaIds)
            ->orderByDesc('updated_at');
        $total = $query->count();
        $perPage = (int) $request->get('per_page', 15);
        $page = (int) $request->get('page', 1);
        $tickets = $query->skip(($page - 1) * $perPage)->take($perPage)->get();
        $items = $tickets->map(function (Ticket $t) {
            $last = $t->mensagens->first();
            return [
                'id' => $t->id,
                'assunto' => $t->assunto,
                'status' => $t->status,
                'created_at' => $t->created_at->toIso8601String(),
                'last_reply_at' => $last ? $last->created_at->toIso8601String() : $t->updated_at->toIso8601String(),
            ];
        });
        return response()->json([
            'success' => true,
            'tickets' => $items,
            'meta' => ['total' => $total, 'per_page' => $perPage, 'current_page' => $page],
        ]);
    }

    /**
     * Cria ticket e primeira mensagem; envia email para admins (desenvolvedor).
     */
    public function store(TicketStoreRequest $request): JsonResponse
    {
        $user = Auth::user();
        $empresa = $user->empresas()->first();
        if (!$empresa) {
            return response()->json(['success' => false, 'message' => 'Usuário não está vinculado a nenhuma empresa.'], 422);
        }
        DB::beginTransaction();
        try {
            $ticket = Ticket::create([
                'empresa_id' => $empresa->id,
                'assunto' => $request->input('subject'),
                'status' => 'aberto',
                'criado_por' => $user->id,
            ]);
            TicketMensagem::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'tipo_remetente' => 'cliente',
                'mensagem' => $request->input('body'),
            ]);
            DB::commit();
            $this->emailService->sendMailable('lucaswsb52@gmail.com', new NovoTicketMail($ticket->fresh(['criadoPor', 'empresa', 'mensagens'])));
            return response()->json([
                'success' => true,
                'message' => 'Chamado aberto com sucesso.',
                'ticket' => ['id' => $ticket->id, 'assunto' => $ticket->assunto, 'status' => $ticket->status],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Exibe ticket com mensagens (valida por empresa_id).
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $empresaIds = $user->empresas()->pluck('empresas.id');
        $ticket = Ticket::with(['criadoPor:id,nome,email', 'empresa:id,nome_fantasia,razao_social', 'mensagens.user:id,nome'])
            ->whereIn('empresa_id', $empresaIds)
            ->find($id);
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Chamado não encontrado.'], 404);
        }
        $mensagens = $ticket->mensagens->map(fn ($m) => [
            'id' => $m->id,
            'user_id' => $m->user_id,
            'tipo_remetente' => $m->tipo_remetente,
            'mensagem' => $m->mensagem,
            'created_at' => $m->created_at->toIso8601String(),
            'user_nome' => $m->user->nome ?? null,
        ]);
        return response()->json([
            'success' => true,
            'ticket' => [
                'id' => $ticket->id,
                'assunto' => $ticket->assunto,
                'status' => $ticket->status,
                'empresa_id' => $ticket->empresa_id,
                'created_at' => $ticket->created_at->toIso8601String(),
                'updated_at' => $ticket->updated_at->toIso8601String(),
                'mensagens' => $mensagens,
            ],
        ]);
    }

    /**
     * Adiciona mensagem do cliente; mantém status (em andamento).
     */
    public function storeMessage(int $id, TicketMessageRequest $request): JsonResponse
    {
        $user = Auth::user();
        $empresaIds = $user->empresas()->pluck('empresas.id');
        $ticket = Ticket::whereIn('empresa_id', $empresaIds)->find($id);
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Chamado não encontrado.'], 404);
        }
        if (in_array($ticket->status, ['fechado'], true)) {
            return response()->json(['success' => false, 'message' => 'Este chamado está encerrado.'], 422);
        }
        $ticket->mensagens()->create([
            'user_id' => $user->id,
            'tipo_remetente' => 'cliente',
            'mensagem' => $request->input('body'),
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Mensagem enviada.',
        ]);
    }
}
