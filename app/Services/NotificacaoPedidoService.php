<?php

namespace App\Services;

use App\Helpers\MensagemPedidoHelper;
use App\Models\EmpresaEvolutionWhatsapp;
use App\Models\Pedido;
use App\Models\StatusPedidos;
use Illuminate\Support\Facades\Log;

class NotificacaoPedidoService
{
    private EvolutionMensagensService $evolutionMensagens;

    public function __construct(EvolutionMensagensService $evolutionMensagens)
    {
        $this->evolutionMensagens = $evolutionMensagens;
    }

    /**
     * Notifica o cliente via WhatsApp quando o status do pedido é alterado.
     *
     * @param Pedido $pedido Pedido com relacionamentos carregados
     * @param int $novoStatusId ID do novo status
     * @param string|null $observacao Observação opcional sobre a mudança de status
     * @return void
     */
    public function notificarClienteStatusAlterado(Pedido $pedido, int $novoStatusId, ?string $observacao): void
    {
        try {
            // Buscar instância WhatsApp da empresa
            $instancia = EmpresaEvolutionWhatsapp::where('empresa_id', $pedido->empresa_id)
                ->where('status', 'open')
                ->first();

            if (!$instancia) {
                // Empresa não tem instância conectada, não envia notificação
                return;
            }

            // Buscar telefone do cliente (usuário que fez o pedido)
            $cliente = $pedido->usuario;
            if (!$cliente || empty($cliente->telefone)) {
                // Cliente não tem telefone cadastrado
                return;
            }

            // Formatar número para internacional
            $numeroFormatado = $this->evolutionMensagens->formatarNumeroInternacional($cliente->telefone);
            if (!$numeroFormatado) {
                Log::warning('Número de telefone inválido do cliente', [
                    'pedido_id' => $pedido->id,
                    'usuario_id' => $cliente->id,
                    'telefone' => $cliente->telefone,
                ]);
                return;
            }

            // Buscar slug do novo status
            $status = StatusPedidos::find($novoStatusId);
            if (!$status) {
                return;
            }

            // Gerar mensagem amigável
            $mensagem = MensagemPedidoHelper::gerarMensagemStatus($pedido, $status->slug, $observacao);

            // Enviar mensagem
            $resultado = $this->evolutionMensagens->enviarMensagemTexto(
                $instancia->instance_name,
                $numeroFormatado,
                $mensagem
            );

            if (!$resultado['success']) {
                Log::warning('Falha ao enviar notificação WhatsApp', [
                    'pedido_id' => $pedido->id,
                    'error' => $resultado['message'] ?? 'Erro desconhecido',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Erro ao notificar cliente de status alterado', [
                'pedido_id' => $pedido->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
