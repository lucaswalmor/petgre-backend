<?php

namespace App\Services;

use App\Mail\EstoqueMinimoMail;
use App\Models\Produto;
use Illuminate\Support\Facades\Log;

class NotificacaoEstoqueService
{
    private EvolutionMensagensService $evolutionMensagens;

    public function __construct(EvolutionMensagensService $evolutionMensagens)
    {
        $this->evolutionMensagens = $evolutionMensagens;
    }

    /**
     * Envia notificações de estoque mínimo (email e WhatsApp) para a empresa.
     *
     * @param Produto $produto Produto com estoque baixo
     * @param mixed $empresa Empresa dona do produto
     * @return void
     */
    public function notificarEstoqueMinimo(Produto $produto, $empresa): void
    {
        if (!$empresa || !$empresa->configuracoes) {
            return;
        }

        // Enviar email se configurado
        $this->enviarEmailEstoqueMinimo($produto, $empresa);

        // Enviar WhatsApp se configurado
        $this->enviarWhatsAppEstoqueMinimo($produto, $empresa);
    }

    /**
     * Envia email de notificação de estoque mínimo.
     */
    private function enviarEmailEstoqueMinimo(Produto $produto, $empresa): void
    {
        try {
            $emailConfiguracao = $empresa->configuracoes->email;

            if (empty($emailConfiguracao)) {
                return;
            }

            app(EmailService::class)->sendMailable($emailConfiguracao, new EstoqueMinimoMail($produto, $empresa));
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar email de estoque mínimo', [
                'produto_id' => $produto->id,
                'empresa_id' => $empresa->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envia notificação de estoque mínimo via WhatsApp.
     */
    private function enviarWhatsAppEstoqueMinimo(Produto $produto, $empresa): void
    {
        try {
            // Verificar se empresa tem número de WhatsApp configurado
            if (empty($empresa->configuracoes->whatsapp_pedidos)) {
                return;
            }

            // Buscar instância do sistema
            $instanceSistema = config('services.evolution_api.instance_sistema');
            if (empty($instanceSistema)) {
                Log::warning('Instância do sistema não configurada para envio de WhatsApp');
                return;
            }

            // Formatar número do WhatsApp da empresa
            $numeroEmpresa = preg_replace('/[^0-9]/', '', $empresa->configuracoes->whatsapp_pedidos);
            if (empty($numeroEmpresa)) {
                return;
            }

            // Garantir que o número tenha o código do país (55)
            if (!str_starts_with($numeroEmpresa, '55')) {
                $numeroEmpresa = '55' . $numeroEmpresa;
            }

            // Criar mensagem amigável
            $mensagem = $this->gerarMensagemEstoqueMinimo($produto);

            // Enviar mensagem usando a instância do sistema
            $resultado = $this->evolutionMensagens->enviarMensagemTexto($instanceSistema, $numeroEmpresa, $mensagem);

            if (!$resultado['success']) {
                Log::warning('Falha ao enviar notificação de estoque mínimo via WhatsApp', [
                    'produto_id' => $produto->id,
                    'empresa_id' => $empresa->id,
                    'error' => $resultado['message'] ?? 'Erro desconhecido',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar notificação de estoque mínimo via WhatsApp', [
                'produto_id' => $produto->id,
                'empresa_id' => $empresa->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Gera mensagem amigável de estoque mínimo para o lojista.
     */
    private function gerarMensagemEstoqueMinimo(Produto $produto): string
    {
        $nomeProduto = $produto->nome;
        $estoqueAtual = $produto->estoque;
        $estoqueMinimo = $produto->estoque_minimo;
        $unidade = $produto->vende_granel ? 'kg' : 'un';

        return <<<MENSAGEM
🚨 *Alerta de Estoque - PetGre*

Olá! Tudo bem?

Passando para avisar que o produto *{$nomeProduto}* está com estoque baixo:

📦 Estoque atual: {$estoqueAtual} {$unidade}
⚠️ Estoque mínimo: {$estoqueMinimo} {$unidade}

Recomendamos repor o estoque para evitar ficar sem produto para seus clientes.

Acesse o sistema para mais detalhes: https://painel.petgre.com.br

Atenciosamente,
Equipe PetGre 🐾
MENSAGEM;
    }
}
