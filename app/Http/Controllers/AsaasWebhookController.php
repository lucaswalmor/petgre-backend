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

        Log::info('Asaas Webhook recebido', [
            'event' => $event,
            'payment_id' => $payment['id'] ?? null,
            'customer' => $payment['customer'] ?? null,
        ]);

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

        // Verificar se a fatura já existe (foi criada pelo nosso sistema)
        $faturaExistente = EmpresaFatura::where('asaas_payment_id', $paymentId)->first();
        if ($faturaExistente) {
            Log::info('AsaasWebhook PAYMENT_CREATED: fatura já existe no sistema', [
                'payment_id' => $paymentId,
                'fatura_id' => $faturaExistente->id,
            ]);
            return;
        }

        // Buscar dados de faturamento pelo customer_id
        $faturamento = EmpresaFaturamento::where('asaas_customer_id', $payment['customer'] ?? '')->first();
        if (!$faturamento) {
            Log::warning('AsaasWebhook PAYMENT_CREATED: customer não encontrado', [
                'customer' => $payment['customer'] ?? null,
            ]);
            return;
        }

        // Buscar detalhes completos do pagamento na API do Asaas
        $dados = $this->asaasService->buscarPagamento($paymentId);
        if (empty($dados['id'])) {
            Log::error('AsaasWebhook PAYMENT_CREATED: falha ao buscar pagamento na API', [
                'payment_id' => $paymentId,
            ]);
            return;
        }

        $dueDate = $dados['dueDate'] ?? null;
        $mesRef = $dueDate ? Carbon::parse($dueDate)->format('Y-m') : now()->format('Y-m');

        // Extrair mês de referência da descrição se disponível
        $descricao = $dados['description'] ?? '';
        if (preg_match('/Cobrança mensal (\d{4}-\d{2})/', $descricao, $matches)) {
            $mesRef = $matches[1];
        }

        // Criar fatura no sistema
        $fatura = EmpresaFatura::create([
            'usuario_id' => $faturamento->usuario_id,
            'empresa_id' => null, // Será atualizado manualmente ou via descrição
            'asaas_payment_id' => $paymentId,
            'mes_referencia' => $mesRef,
            'valor' => (float) ($dados['value'] ?? 0),
            'status' => 'pendente',
            'vencimento' => $dueDate ? Carbon::parse($dueDate)->format('Y-m-d') : now()->addDays(5)->format('Y-m-d'),
            'quantidade_pedidos' => 0,
            'quantidade_filiais' => 0,
            'pix_qrcode_base64' => $dados['pixTransaction']['encodedImage'] ?? null,
            'pix_copia_cola' => $dados['pixTransaction']['payload'] ?? null,
            'link_fatura' => $dados['invoiceUrl'] ?? null,
        ]);

        Log::info('AsaasWebhook PAYMENT_CREATED: fatura criada', [
            'payment_id' => $paymentId,
            'fatura_id' => $fatura->id,
            'usuario_id' => $faturamento->usuario_id,
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
            Log::warning('AsaasWebhook PAYMENT_RECEIVED: fatura não encontrada', [
                'payment_id' => $paymentId,
            ]);
            return;
        }

        $pagoEm = isset($payment['paymentDate']) ? Carbon::parse($payment['paymentDate'])->format('Y-m-d') : now()->format('Y-m-d');
        $fatura->update(['status' => 'pago', 'pago_em' => $pagoEm]);

        // Ativar matriz e todas as filiais do grupo
        $this->ativarMatrizEFiliais($fatura);

        Log::info('AsaasWebhook PAYMENT_RECEIVED: fatura paga, empresas ativadas', [
            'payment_id' => $paymentId,
            'fatura_id' => $fatura->id,
            'empresa_id' => $fatura->empresa_id,
        ]);
    }

    /**
     * Ativar matriz e todas as suas filiais
     */
    private function ativarMatrizEFiliais(EmpresaFatura $fatura): void
    {
        // Se tem empresa_id na fatura, usar ela como matriz
        if ($fatura->empresa_id) {
            $matrizId = $fatura->empresa_id;
        } else {
            // Senão, buscar matriz pelo usuário
            $matriz = Empresa::whereHas('usuarioEmpresas', fn ($q) => $q->where('usuario_id', $fatura->usuario_id))
                ->where('is_matriz', true)
                ->first();

            if (!$matriz) {
                Log::warning('AsaasWebhook: matriz não encontrada para ativação', [
                    'usuario_id' => $fatura->usuario_id,
                ]);
                return;
            }

            $matrizId = $matriz->id;
        }

        // Ativar matriz
        Empresa::where('id', $matrizId)->update(['ativo' => true]);

        // Ativar todas as filiais da matriz
        Empresa::where('empresa_matriz_id', $matrizId)->update(['ativo' => true]);

        // Atualizar flag de assinatura ativa no faturamento
        EmpresaFaturamento::where('usuario_id', $fatura->usuario_id)->update(['assinatura_ativa' => true]);

        Log::info('AsaasWebhook: matriz e filiais ativadas', [
            'matriz_id' => $matrizId,
            'fatura_id' => $fatura->id,
        ]);
    }

    private function handlePaymentOverdue(array $payment): void
    {
        $paymentId = $payment['id'] ?? null;
        if (!$paymentId) {
            return;
        }

        $fatura = EmpresaFatura::where('asaas_payment_id', $paymentId)->first();
        if (!$fatura) {
            Log::warning('AsaasWebhook PAYMENT_OVERDUE: fatura não encontrada', [
                'payment_id' => $paymentId,
            ]);
            return;
        }

        $fatura->update(['status' => 'vencido']);

        Log::info('AsaasWebhook PAYMENT_OVERDUE: fatura marcada como vencida', [
            'payment_id' => $paymentId,
            'fatura_id' => $fatura->id,
        ]);

        // Notificar usuário sobre vencimento
        $this->notificarVencimento($fatura);
    }

    /**
     * Notificar usuário sobre vencimento da fatura
     */
    private function notificarVencimento(EmpresaFatura $fatura): void
    {
        $usuario = User::find($fatura->usuario_id);
        if (!$usuario) {
            return;
        }

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

            Log::info('AsaasWebhook: email de vencimento enviado', [
                'usuario_id' => $usuario->id,
                'fatura_id' => $fatura->id,
            ]);
        } catch (\Exception $e) {
            Log::error('AsaasWebhook: erro ao enviar email de vencimento', [
                'usuario_id' => $usuario->id,
                'fatura_id' => $fatura->id,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    private function handlePaymentCanceled(array $payment): void
    {
        $paymentId = $payment['id'] ?? null;
        if (!$paymentId) {
            return;
        }

        EmpresaFatura::where('asaas_payment_id', $paymentId)->update(['status' => 'cancelado']);

        Log::info('AsaasWebhook PAYMENT_CANCELED/REFUNDED: fatura cancelada', [
            'payment_id' => $paymentId,
        ]);
    }
}
