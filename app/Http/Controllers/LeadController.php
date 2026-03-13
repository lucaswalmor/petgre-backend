<?php

namespace App\Http\Controllers;

use App\Mail\NovoLeadMail;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class LeadController extends Controller
{
    /**
     * Store a newly created lead in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'whatsapp' => 'required|string|min:11|max:20',
            'tipo_empresa' => 'required|in:petshop,clinica_veterinaria,banho_tosa,outros',
            'utm_source' => 'nullable|string|max:255',
            'utm_medium' => 'nullable|string|max:255',
            'utm_campaign' => 'nullable|string|max:255',
            'utm_term' => 'nullable|string|max:255',
            'utm_content' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $data['ip_address'] = $request->ip();
        $data['user_agent'] = $request->userAgent();
        $data['referrer'] = $request->headers->get('referer');

        $lead = Lead::create($data);

        try {
            Mail::to('lucaswsb52@gmail.com')->send(new NovoLeadMail($lead));
        } catch (\Exception $e) {
            \Log::error('Erro ao enviar email de lead: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Lead cadastrado com sucesso!',
            'lead' => $lead,
        ], 201);
    }

    /**
     * Display a listing of leads (admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Lead::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('whatsapp', 'like', "%{$search}%");
            });
        }

        $leads = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($leads);
    }

    /**
     * Display the specified lead.
     */
    public function show(int $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);
        return response()->json($lead);
    }

    /**
     * Update the specified lead.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:novo,contatado,qualificado,descartado',
            'observacoes' => 'nullable|string',
            'contato_em' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $lead->update($validator->validated());

        return response()->json([
            'message' => 'Lead atualizado com sucesso!',
            'lead' => $lead,
        ]);
    }

    /**
     * Remove the specified lead.
     */
    public function destroy(int $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);
        $lead->delete();

        return response()->json([
            'message' => 'Lead excluído com sucesso!',
        ]);
    }
}
