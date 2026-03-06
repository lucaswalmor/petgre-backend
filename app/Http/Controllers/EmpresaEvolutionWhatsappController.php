<?php

namespace App\Http\Controllers;

use App\Helpers\VerificaEmpresa;
use App\Http\Resources\EmpresaEvolutionWhatsappResource;
use App\Models\EmpresaEvolutionWhatsapp;
use App\Services\EvolutionApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmpresaEvolutionWhatsappController extends Controller
{
    public function __construct(
        private EvolutionApiService $evolutionApi
    ) {
    }

    /**
     * Retorna os dados da instância da empresa (ou null). Inclui status atualizado na Evolution API.
     */
    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->empresa_id;
        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($empresaId)) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $instancia = EmpresaEvolutionWhatsapp::where('empresa_id', $empresaId)->first();
        if (!$instancia) {
            return response()->json([
                'success' => true,
                'instancia' => null,
            ]);
        }

        $statusResult = $this->evolutionApi->buscarStatus($instancia->instance_name);
        if ($statusResult['success'] && isset($statusResult['state'])) {
            $instancia->status = $statusResult['state'];
            if ($statusResult['state'] === 'open' && !$instancia->conectado_em) {
                $instancia->conectado_em = now();
            }
            $instancia->save();
        }

        return response()->json([
            'success' => true,
            'instancia' => new EmpresaEvolutionWhatsappResource($instancia->fresh()),
        ]);
    }

    /**
     * Cria a instância na Evolution API e salva na tabela. Apenas se a empresa ainda não tem instância.
     */
    public function criar(Request $request): JsonResponse
    {
        $empresaId = $request->empresa_id;
        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($empresaId)) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        if (EmpresaEvolutionWhatsapp::where('empresa_id', $empresaId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta empresa já possui uma instância WhatsApp cadastrada.',
            ], 422);
        }

        $numero = $request->input('numero');
        $nomeDispositivo = $this->formatarNomeInstancia($request->input('nome_dispositivo') ?? '');

        if (empty($nomeDispositivo)) {
            return response()->json([
                'success' => false,
                'message' => 'Nome do dispositivo é obrigatório.',
            ], 422);
        }

        $nomeInstancia = $nomeDispositivo . '_' . $empresaId;
        $result = $this->evolutionApi->criarInstancia($nomeInstancia);
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Erro ao criar instância na Evolution API.',
            ], 502);
        }

        $instancia = EmpresaEvolutionWhatsapp::create([
            'empresa_id' => $empresaId,
            'instance_name' => $nomeInstancia,
            'numero' => $numero,
            'status' => 'close',
        ]);

        return response()->json([
            'success' => true,
            'instancia' => new EmpresaEvolutionWhatsappResource($instancia),
        ], 201);
    }

    /**
     * Formata o nome da instância: maiúsculas, espaços → underline, remove acentuação.
     */
    private function formatarNomeInstancia(string $nome): string
    {
        // Remove acentuação
        $nome = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
        // Remove caracteres especiais exceto espaço e underline
        $nome = preg_replace('/[^a-zA-Z0-9_ ]/', '', $nome);
        // Espaços viram underline
        $nome = str_replace(' ', '_', $nome);
        // Maiúsculas
        $nome = strtoupper($nome);
        // Remove underlines duplicados
        $nome = preg_replace('/_+/', '_', $nome);
        // Trim underline no início e fim
        $nome = trim($nome, '_');

        return $nome;
    }

    /**
     * Retorna o QR Code para conexão. Disponível apenas se instância existir e status != open.
     */
    public function buscarQrCode(Request $request): JsonResponse
    {
        $empresaId = $request->empresa_id;
        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($empresaId)) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $instancia = EmpresaEvolutionWhatsapp::where('empresa_id', $empresaId)->first();
        if (!$instancia) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhuma instância cadastrada. Crie uma instância primeiro.',
            ], 404);
        }
        if ($instancia->status === 'open') {
            return response()->json([
                'success' => false,
                'message' => 'Instância já está conectada.',
            ], 422);
        }

        $result = $this->evolutionApi->buscarQrCode($instancia->instance_name);
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Erro ao buscar QR Code.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'base64' => $result['base64'] ?? null,
            'pairing_code' => $result['pairingCode'] ?? null,
        ]);
    }

    /**
     * Consulta o status na Evolution API, atualiza a tabela e retorna o status.
     */
    public function atualizarStatus(Request $request): JsonResponse
    {
        $empresaId = $request->empresa_id;
        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($empresaId)) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $instancia = EmpresaEvolutionWhatsapp::where('empresa_id', $empresaId)->first();
        if (!$instancia) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhuma instância cadastrada.',
            ], 404);
        }

        $result = $this->evolutionApi->buscarStatus($instancia->instance_name);
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Erro ao consultar status.',
            ], 502);
        }

        $instancia->status = $result['state'];
        if ($result['state'] === 'open' && !$instancia->conectado_em) {
            $instancia->conectado_em = now();
        }
        $instancia->save();

        return response()->json([
            'success' => true,
            'status' => $instancia->status,
            'instancia' => new EmpresaEvolutionWhatsappResource($instancia->fresh()),
        ]);
    }

    /**
     * Desconecta a instância (logout) e atualiza status para close.
     */
    public function desconectar(Request $request): JsonResponse
    {
        $empresaId = $request->empresa_id;
        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($empresaId)) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $instancia = EmpresaEvolutionWhatsapp::where('empresa_id', $empresaId)->first();
        if (!$instancia) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhuma instância cadastrada.',
            ], 404);
        }

        $result = $this->evolutionApi->desconectarInstancia($instancia->instance_name);
        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Erro ao desconectar.',
            ], 502);
        }

        $instancia->update(['status' => 'close', 'conectado_em' => null]);

        return response()->json([
            'success' => true,
            'instancia' => new EmpresaEvolutionWhatsappResource($instancia->fresh()),
        ]);
    }

    /**
     * Deleta a instância na Evolution API e remove o registro da tabela.
     */
    public function deletar(Request $request): JsonResponse
    {
        $empresaId = $request->empresa_id;
        if (!VerificaEmpresa::verificaEmpresaPertenceAoUsuario($empresaId)) {
            return response()->json(['error' => 'Acesso negado'], 403);
        }

        $instancia = EmpresaEvolutionWhatsapp::where('empresa_id', $empresaId)->first();
        if (!$instancia) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhuma instância cadastrada.',
            ], 404);
        }

        $nomeInstancia = $instancia->instance_name;
        $this->evolutionApi->deletarInstancia($nomeInstancia);
        $instancia->delete();

        return response()->json(['success' => true]);
    }
}
