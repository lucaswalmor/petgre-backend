<?php

namespace App\Helpers;

use App\Models\Pedido;

class MensagemPedidoHelper
{
    /**
     * Nomes amigáveis dos status.
     */
    private static array $nomesStatus = [
        'pendente' => '⏳ Pendente',
        'confirmado' => '✅ Confirmado',
        'em_preparacao' => '📦 Em Separação',
        'em_entrega' => '🚚 Em Entrega',
        'entregue' => '🎉 Entregue',
        'cancelado' => '❌ Cancelado',
    ];

    /**
     * Emojis decorativos por status.
     */
    private static array $emojisStatus = [
        'pendente' => '⏳',
        'confirmado' => '✅',
        'em_preparacao' => '📦',
        'em_entrega' => '🚚',
        'entregue' => '🎉',
        'cancelado' => '❌',
    ];

    /**
     * Gera mensagem amigável de atualização de status do pedido.
     */
    public static function gerarMensagemStatus(Pedido $pedido, string $statusSlug, ?string $observacao = null): string
    {
        $empresaNome = $pedido->empresa->nome_fantasia ?? $pedido->empresa->razao_social ?? 'Loja';
        $codigoPedido = $pedido->id;
        $total = number_format($pedido->total, 2, ',', '.');
        $emoji = self::$emojisStatus[$statusSlug] ?? '📦';
        $statusNome = self::$nomesStatus[$statusSlug] ?? ucfirst(str_replace('_', ' ', $statusSlug));

        $mensagem = "{$emoji} *Atualização do seu Pedido*\n\n";
        $mensagem .= "Olá! Tudo bem? 👋\n\n";
        $mensagem .= "Seu pedido *#{$codigoPedido}* na *{$empresaNome}* teve uma atualização:\n\n";
        $mensagem .= "*Status:* {$statusNome}\n";

        if ($observacao) {
            $mensagem .= "*Observação:* {$observacao}\n";
        }

        $mensagem .= "\n";

        // Mensagens específicas por status
        $mensagem .= match ($statusSlug) {
            'pendente' => "⏰ Recebemos seu pedido e estamos aguardando confirmação.\nEm breve entraremos em contato!",
            'confirmado' => "🎉 Ótimo! Seu pedido foi *confirmado* e já está sendo separado.\nAgradecemos a preferência! 🐾",
            'em_preparacao' => "📦 Seu pedido está sendo *separado com carinho*!\nLogo logo estará pronto para entrega.",
            'em_entrega' => "🚚 Seu pedido saiu para *entrega*!\nFique atento, nosso entregador está a caminho. 🏍️",
            'entregue' => "🎉 *Pedido entregue!* 🐕\nEsperamos que seu pet aproveite os produtos!\nAgradecemos por escolher a *{$empresaNome}*! 💚\n\n🌟 Não esqueça de avaliar seu pedido!",
            'cancelado' => "❌ Seu pedido foi *cancelado*.\nSe tiver alguma dúvida, entre em contato conosco.\nPedimos desculpas pelo inconveniente.",
            default => "📦 Acompanhe seu pedido pela plataforma.",
        };

        $mensagem .= "\n\n";
        $mensagem .= "💰 *Total:* R$ {$total}\n\n";
        $mensagem .= "_Esta é uma mensagem automática. Por favor, não responda._";

        return $mensagem;
    }

    /**
     * Gera mensagem de confirmação de pedido criado.
     */
    public static function gerarMensagemNovoPedido(Pedido $pedido): string
    {
        $empresaNome = $pedido->empresa->nome_fantasia ?? $pedido->empresa->razao_social ?? 'Loja';
        $codigoPedido = $pedido->id;
        $total = number_format($pedido->total, 2, ',', '.');

        $mensagem = "🛒 *Pedido Recebido!*\n\n";
        $mensagem .= "Olá! 👋\n\n";
        $mensagem .= "Recebemos seu pedido *#{$codigoPedido}* na *{$empresaNome}*!\n\n";
        $mensagem .= "📋 *Resumo:*\n";

        // Lista os itens
        foreach ($pedido->itens as $item) {
            $nome = $item->produto->nome ?? 'Produto';
            $quantidade = $item->quantidade;
            $mensagem .= "• {$nome} (x{$quantidade})\n";
        }

        $mensagem .= "\n💰 *Total:* R$ {$total}\n\n";
        $mensagem .= "⏳ Status: *Pendente*\n";
        $mensagem .= "Em breve confirmaremos seu pedido!\n\n";
        $mensagem .= "_Esta é uma mensagem automática. Por favor, não responda._";

        return $mensagem;
    }
}
