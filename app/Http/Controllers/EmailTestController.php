<?php

namespace App\Http\Controllers;

use App\Services\EmailService;
use App\Mail\NovoLojistaMail;
use App\Mail\NovoClienteMail;
use App\Mail\NovoFuncionarioMail;
use App\Models\User;
use App\Models\Empresa;
use App\Models\NichosEmpresa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmailTestController extends Controller
{
    protected EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Testar envio de email
     * GET /api/email/test?to=email@exemplo.com
     */
    public function test(Request $request): JsonResponse
    {
        $request->validate([
            'to' => 'required|email'
        ]);

        $to = $request->query('to');

        $result = $this->emailService->testEmail($to);

        if ($result['success']) {
            return response()->json([
                'message' => 'Email de teste enviado com sucesso!',
                'data' => $result
            ]);
        }

        return response()->json([
            'message' => 'Erro ao enviar email de teste',
            'data' => $result
        ], 500);
    }

    /**
     * Verificar status da configuração de email
     * GET /api/email/status
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'mailer' => config('mail.default'),
            'host' => config('mail.mailers.' . config('mail.default') . '.host', 'N/A'),
            'port' => config('mail.mailers.' . config('mail.default') . '.port', 'N/A'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'encryption' => config('mail.mailers.' . config('mail.default') . '.encryption', 'N/A'),
            'status' => 'configured'
        ]);
    }

    /**
     * Testar email de boas-vindas para nova empresa
     * GET /api/email/test-bem-vindo?to=email@exemplo.com
     */
    public function testBemVindo(Request $request): JsonResponse
    {
        $request->validate([
            'to' => 'required|email'
        ]);

        $to = $request->query('to');

        try {
            // Criar dados fictícios de empresa recém cadastrada
            $nicho = NichosEmpresa::first() ?? (object)['nome' => 'Petshop'];

            $empresaFake = (object) [
                'nome_fantasia' => 'PetShop do João',
                'razao_social' => 'João da Silva ME',
                'nicho' => $nicho
            ];

            $usuarioFake = (object) [
                'nome' => 'João Silva',
                'email' => $to,
                'telefone' => '(34) 99999-9999'
            ];

            // Enviar email usando o Mailable
            $success = $this->emailService->sendMailable($to, new NovoLojistaMail($empresaFake, $usuarioFake));

            if ($success) {
                return response()->json([
                    'message' => 'Email de boas-vindas enviado com sucesso!',
                    'to' => $to,
                    'type' => 'bem-vindo-empresa',
                    'empresa' => $empresaFake->nome_fantasia,
                    'usuario' => $usuarioFake->nome,
                    'template' => 'NovoLojistaMail'
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao enviar email de boas-vindas',
                'error' => $e->getMessage(),
                'to' => $to
            ], 500);
        }
    }

    /**
     * Teste de email de boas-vindas para funcionário
     */
    public function testBemVindoFuncionario(Request $request): JsonResponse
    {
        $to = $request->query('to', 'test@example.com');

        try {
            // Criar dados fake para teste
            $usuarioFake = (object) [
                'nome' => 'João Silva',
                'email' => $to,
                'telefone' => '(34) 99999-9999'
            ];

            $empresaFake = (object) [
                'nome_fantasia' => 'PetShop do João',
                'razao_social' => 'João Silva ME',
                'nicho' => (object) ['nome' => 'Petshop']
            ];

            $senhaFake = 'AbCdEf123456';

            $this->emailService->sendMailable($to, new NovoFuncionarioMail($usuarioFake, $empresaFake, $senhaFake));

            return response()->json([
                'message' => 'Email de boas-vindas para funcionário enviado com sucesso!',
                'to' => $to,
                'type' => 'bem-vindo-funcionario',
                'empresa' => $empresaFake->nome_fantasia,
                'usuario' => $usuarioFake->nome,
                'template' => 'NovoFuncionarioMail'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao enviar email de boas-vindas para funcionário',
                'error' => $e->getMessage(),
                'to' => $to
            ], 500);
        }
    }

    /**
     * Teste de email de boas-vindas para cliente
     */
    public function testBemVindoCliente(Request $request): JsonResponse
    {
        $to = $request->query('to', 'test@example.com');

        try {
            // Criar dados fake para teste
            $usuarioFake = (object) [
                'nome' => 'Maria Santos',
                'email' => $to,
                'telefone' => '(34) 88888-8888'
            ];

            $this->emailService->sendMailable($to, new NovoClienteMail($usuarioFake));

            return response()->json([
                'message' => 'Email de boas-vindas para cliente enviado com sucesso!',
                'to' => $to,
                'type' => 'bem-vindo-cliente',
                'usuario' => $usuarioFake->nome,
                'template' => 'NovoClienteMail'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao enviar email de boas-vindas para cliente',
                'error' => $e->getMessage(),
                'to' => $to
            ], 500);
        }
    }
}