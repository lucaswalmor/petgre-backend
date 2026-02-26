<?php

namespace App\Http\Controllers;

use App\Mail\RespostaTicketMail;
use App\Models\Ticket;
use App\Models\TicketMensagem;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChamadosController extends Controller
{
    public function __construct(private EmailService $emailService) {}

    private function isDesenvolvedor(): bool
    {
        $user = Auth::user();
        return $user && (bool) $user->desenvolvedor;
    }

    private function failDesenvolvedor(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Acesso negado. Apenas administradores podem acessar.'], 403);
    }

    /**
     * Lista todos os tickets (com filtros e paginação).
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->isDesenvolvedor()) {
            return $this->failDesenvolvedor();
        }
        $query = Ticket::with(['criadoPor:id,nome,email', 'empresa:id,nome_fantasia,razao_social']);
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('empresa')) {
            $term = $request->empresa;
            $query->whereHas('empresa', fn($q) => $q->where('nome_fantasia', 'like', "%{$term}%")->orWhere('razao_social', 'like', "%{$term}%"));
        }
        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->data_inicio);
        }
        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->data_fim);
        }
        $query->orderByDesc('updated_at');
        $total = $query->count();
        $perPage = (int) $request->get('per_page', 15);
        $page = (int) $request->get('page', 1);
        $tickets = $query->skip(($page - 1) * $perPage)->take($perPage)->get();
        $items = $tickets->map(fn(Ticket $t) => [
            'id' => $t->id,
            'assunto' => $t->assunto,
            'status' => $t->status,
            'empresa' => $t->empresa ? ['id' => $t->empresa->id, 'nome' => $t->empresa->nome_fantasia ?? $t->empresa->razao_social] : null,
            'aberto_por' => $t->criadoPor ? ['id' => $t->criadoPor->id, 'nome' => $t->criadoPor->nome] : null,
            'created_at' => $t->created_at->toIso8601String(),
        ]);
        return response()->json([
            'success' => true,
            'tickets' => $items,
            'meta' => ['total' => $total, 'per_page' => $perPage, 'current_page' => $page],
        ]);
    }

    /**
     * Exibe ticket com mensagens.
     */
    public function show(int $id): JsonResponse
    {
        if (!$this->isDesenvolvedor()) {
            return $this->failDesenvolvedor();
        }
        $ticket = Ticket::with(['criadoPor:id,nome,email', 'empresa:id,nome_fantasia,razao_social', 'mensagens.user:id,nome'])
            ->find($id);
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Chamado não encontrado.'], 404);
        }
        $mensagens = $ticket->mensagens->map(fn($m) => [
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
                'empresa' => $ticket->empresa ? ['id' => $ticket->empresa->id, 'nome' => $ticket->empresa->nome_fantasia ?? $ticket->empresa->razao_social] : null,
                'aberto_por' => $ticket->criadoPor ? ['id' => $ticket->criadoPor->id, 'nome' => $ticket->criadoPor->nome, 'email' => $ticket->criadoPor->email] : null,
                'created_at' => $ticket->created_at->toIso8601String(),
                'updated_at' => $ticket->updated_at->toIso8601String(),
                'mensagens' => $mensagens,
            ],
        ]);
    }

    /**
     * Responde ao ticket (admin). Atualiza status para respondido e envia email ao cliente.
     */
    public function responder(Request $request, int $id): JsonResponse
    {
        if (!$this->isDesenvolvedor()) {
            return $this->failDesenvolvedor();
        }
        $request->validate(['mensagem' => 'required|string']);
        $ticket = Ticket::with('criadoPor')->find($id);
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Chamado não encontrado.'], 404);
        }
        if ($ticket->status === 'fechado') {
            return response()->json(['success' => false, 'message' => 'Chamado já está encerrado.'], 422);
        }
        $user = Auth::user();
        TicketMensagem::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'tipo_remetente' => 'admin',
            'mensagem' => $request->mensagem,
        ]);
        $ticket->update(['status' => 'respondido']);
        $ticket->load('mensagens');
        $clienteEmail = $ticket->criadoPor->email ?? null;
        if ($clienteEmail) {
            $this->emailService->sendMailable($clienteEmail, new RespostaTicketMail($ticket->fresh(['criadoPor', 'empresa'])));
        }
        return response()->json(['success' => true, 'message' => 'Resposta enviada.']);
    }

    /**
     * Conclui (fecha) o ticket.
     */
    public function concluir(int $id): JsonResponse
    {
        if (!$this->isDesenvolvedor()) {
            return $this->failDesenvolvedor();
        }
        $ticket = Ticket::find($id);
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Chamado não encontrado.'], 404);
        }
        $ticket->update(['status' => 'fechado']);
        return response()->json(['success' => true, 'message' => 'Chamado concluído.']);
    }

    /**
     * Exclui o ticket (e mensagens em cascade).
     */
    public function destroy(int $id): JsonResponse
    {
        if (!$this->isDesenvolvedor()) {
            return $this->failDesenvolvedor();
        }
        $ticket = Ticket::find($id);
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Chamado não encontrado.'], 404);
        }
        $ticket->delete();
        return response()->json(['success' => true, 'message' => 'Chamado excluído.']);
    }

    /**
     * Conclui múltiplos tickets.
     */
    public function concluirLote(Request $request): JsonResponse
    {
        if (!$this->isDesenvolvedor()) {
            return $this->failDesenvolvedor();
        }
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        Ticket::whereIn('id', $request->ids)->update(['status' => 'fechado']);
        return response()->json(['success' => true, 'message' => 'Chamados concluídos.']);
    }

    /**
     * Exclui múltiplos tickets.
     */
    public function excluirLote(Request $request): JsonResponse
    {
        if (!$this->isDesenvolvedor()) {
            return $this->failDesenvolvedor();
        }
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        Ticket::whereIn('id', $request->ids)->delete();
        return response()->json(['success' => true, 'message' => 'Chamados excluídos.']);
    }
}
