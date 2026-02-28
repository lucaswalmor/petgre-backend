<?php

namespace App\Http\Controllers;

use App\Mail\AssinaturaInativaMail;
use App\Models\Empresa;
use App\Models\EmpresaFatura;
use App\Models\EmpresaFaturamento;
use App\Models\User;
use App\Services\AsaasService;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AsaasWebhookController extends Controller
{
    public function __construct(
        private AsaasService $asaasService,
        private EmailService $emailService
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $token = $request->header('asaas-access-token');
        if ($token !== config('services.asaas.webhook_token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $event = $request->input('event');
        $payment = $request->input('payment', []);

        try {
            switch ($event) {
                case 'PAYMENT_CREATED':
                    $this->handlePaymentCreated($payment);
                    break;
                case 'PAYMENT_RECEIVED':
                case 'PAYMENT_CONFIRMED':
                    $this->handlePaymentReceived($payment);
                    break;
                case 'PAYMENT_OVERDUE':
                    $this->handlePaymentOverdue($payment);
                    break;
                case 'PAYMENT_DELETED':
                case 'PAYMENT_REFUNDED':
                    $this->handlePaymentCanceled($payment);
                    break;
            }
        } catch (\Throwable $e) {
            Log::error('AsaasWebhook error: ' . $e->getMessage(), [
                'event' => $event,
                'payment' => $payment,
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json(['success' => true], 200);
    }

    private function handlePaymentCreated(array $payment): void
    {
        $paymentId = $payment['id'] ?? null;
        if (!$paymentId) {
            return;
        }

        $faturamento = EmpresaFaturamento::where('asaas_customer_id', $payment['customer'] ?? '')->first();
        if (!$faturamento) {
            Log::warning('AsaasWebhook PAYMENT_CREATED: customer não encontrado', ['customer' => $payment['customer'] ?? null]);
            return;
        }

        $dados = $this->asaasService->buscarPagamento($paymentId);
        if (empty($dados['id'])) {
            return;
        }

        $dueDate = $dados['dueDate'] ?? null;
        $mesRef = $dueDate ? Carbon::parse($dueDate)->format('Y-m') : now()->format('Y-m');

        EmpresaFatura::create([
            'usuario_id' => $faturamento->usuario_id,
            'asaas_payment_id' => $paymentId,
            'mes_referencia' => $mesRef,
            'valor' => (float) ($dados['value'] ?? 0),
            'status' => 'pendente',
            'vencimento' => $dueDate ? Carbon::parse($dueDate)->format('Y-m-d') : now()->addDays(3)->format('Y-m-d'),
            'pix_qrcode_base64' => $dados['pixTransaction']['encodedImage'] ?? null,
            'pix_copia_cola' => $dados['pixTransaction']['payload'] ?? null,
            'link_fatura' => $dados['invoiceUrl'] ?? null,
        ]);
    }

    private function handlePaymentReceived(array $payment): void
    {
        $paymentId = $payment['id'] ?? null;
        if (!$paymentId) {
            return;
        }

        $fatura = EmpresaFatura::where('asaas_payment_id', $paymentId)->first();
        if (!$fatura) {
            return;
        }

        $pagoEm = isset($payment['paymentDate']) ? Carbon::parse($payment['paymentDate'])->format('Y-m-d') : now()->format('Y-m-d');
        $fatura->update(['status' => 'pago', 'pago_em' => $pagoEm]);

        $empresaIds = DB::table('usuarios_empresas')->where('usuario_id', $fatura->usuario_id)->pluck('empresa_id');
        Empresa::whereIn('id', $empresaIds)->update(['ativo' => true]);
        EmpresaFaturamento::where('usuario_id', $fatura->usuario_id)->update(['assinatura_ativa' => true]);
    }

    private function handlePaymentOverdue(array $payment): void
    {
        $paymentId = $payment['id'] ?? null;
        if (!$paymentId) {
            return;
        }

        $fatura = EmpresaFatura::where('asaas_payment_id', $paymentId)->first();
        if (!$fatura) {
            return;
        }

        $fatura->update(['status' => 'vencido']);

        // Enviar email imediatamente informando que a fatura venceu
        $usuario = User::find($fatura->usuario_id);
        if ($usuario) {
            try {
                $this->emailService->sendMailable(
                    $usuario->email,
                    new AssinaturaInativaMail(
                        $usuario,
                        (float) $fatura->valor,
                        $fatura->vencimento?->format('d/m/Y') ?? '',
                        $fatura->link_fatura,
                        $fatura->pix_copia_cola,
                        'vencida'
                    )
                );
                Log::info('Email de fatura vencida enviado', [
                    'usuario_id' => $usuario->id,
                    'fatura_id' => $fatura->id,
                    'email' => $usuario->email
                ]);
            } catch (\Exception $e) {
                Log::error('Erro ao enviar email de fatura vencida', [
                    'usuario_id' => $usuario->id,
                    'fatura_id' => $fatura->id,
                    'erro' => $e->getMessage()
                ]);
            }
        }

        $vencimento = $fatura->vencimento ? Carbon::parse($fatura->vencimento) : now();
        $diasAtraso = (int) $vencimento->diffInDays(now(), false);
        if ($diasAtraso >= 5) {
            $empresaIds = DB::table('usuarios_empresas')->where('usuario_id', $fatura->usuario_id)->pluck('empresa_id');
            Empresa::whereIn('id', $empresaIds)->update(['ativo' => false]);

            // Enviar email de desativação (empresas desativadas)
            if ($usuario) {
                try {
                    $this->emailService->sendMailable(
                        $usuario->email,
                        new AssinaturaInativaMail(
                            $usuario,
                            (float) $fatura->valor,
                            $fatura->vencimento?->format('d/m/Y') ?? '',
                            $fatura->link_fatura,
                            $fatura->pix_copia_cola,
                            'desativada'
                        )
                    );
                    Log::info('Email de empresas desativadas enviado', [
                        'usuario_id' => $usuario->id,
                        'fatura_id' => $fatura->id,
                        'empresas_desativadas' => $empresaIds->toArray()
                    ]);
                } catch (\Exception $e) {
                    Log::error('Erro ao enviar email de empresas desativadas', [
                        'usuario_id' => $usuario->id,
                        'fatura_id' => $fatura->id,
                        'erro' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    private function handlePaymentCanceled(array $payment): void
    {
        $paymentId = $payment['id'] ?? null;
        if (!$paymentId) {
            return;
        }

        EmpresaFatura::where('asaas_payment_id', $paymentId)->update(['status' => 'cancelado']);
    }
}
