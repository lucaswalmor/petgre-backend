<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cobrança Mensal PetGre</title>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:#333;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc; padding:30px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff; border-radius:12px; box-shadow:0 4px 6px rgba(0,0,0,0.1); overflow:hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background:linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); padding:30px 40px; text-align:center;">
                            <h1 style="color:#ffffff; font-size:28px; margin:0; font-weight:600;">PetGre</h1>
                            <p style="color:#dbeafe; font-size:14px; margin:8px 0 0 0;">Cobrança Mensal</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:40px;">
                            <h2 style="color:#1f2937; font-size:22px; margin:0 0 20px 0;">Olá, {{ $usuario->nome }}!</h2>

                            <p style="color:#4b5563; font-size:15px; line-height:1.6; margin:0 0 20px 0;">
                                A cobrança do mês de <strong>{{ $mesReferencia }}</strong> foi gerada com sucesso.
                            </p>

                            <!-- Resumo da Cobrança -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc; border-radius:8px; margin:20px 0;">
                                <tr>
                                    <td style="padding:20px;">
                                        <h3 style="color:#1f2937; font-size:16px; margin:0 0 15px 0;">Resumo da Cobrança</h3>

                                        <table width="100%" cellpadding="8" cellspacing="0" border="0">
                                            <tr>
                                                <td style="color:#6b7280; font-size:14px; border-bottom:1px solid #e5e7eb;">Período:</td>
                                                <td style="color:#1f2937; font-size:14px; font-weight:500; border-bottom:1px solid #e5e7eb; text-align:right;">{{ $mesReferencia }}</td>
                                            </tr>
                                            @if($quantidadePedidos > 0)
                                            <tr>
                                                <td style="color:#6b7280; font-size:14px; border-bottom:1px solid #e5e7eb;">Pedidos no mês:</td>
                                                <td style="color:#1f2937; font-size:14px; font-weight:500; border-bottom:1px solid #e5e7eb; text-align:right;">{{ $quantidadePedidos }} pedidos</td>
                                            </tr>
                                            @endif
                                            @if($quantidadeFiliais > 0)
                                            <tr>
                                                <td style="color:#6b7280; font-size:14px; border-bottom:1px solid #e5e7eb;">Filiais ativas:</td>
                                                <td style="color:#1f2937; font-size:14px; font-weight:500; border-bottom:1px solid #e5e7eb; text-align:right;">{{ $quantidadeFiliais }}</td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <td style="color:#1f2937; font-size:16px; font-weight:600; padding-top:12px;">Valor:</td>
                                                <td style="color:#059669; font-size:18px; font-weight:700; text-align:right; padding-top:12px;">R$ {{ number_format($valor, 2, ',', '.') }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Vencimento -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fef3c7; border-left:4px solid #f59e0b; border-radius:4px; margin:20px 0;">
                                <tr>
                                    <td style="padding:15px 20px;">
                                        <p style="color:#92400e; font-size:14px; margin:0; font-weight:500;">
                                            ⚠️ Vencimento: <strong>{{ \Carbon\Carbon::parse($vencimento)->format('d/m/Y') }}</strong>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Instruções -->
                            <p style="color:#4b5563; font-size:15px; line-height:1.6; margin:20px 0;">
                                <strong>Como pagar:</strong>
                            </p>
                            <ul style="color:#4b5563; font-size:14px; line-height:1.6; margin:0 0 20px 0; padding-left:20px;">
                                <li>Acesse o painel PetGre em <strong>Faturamento</strong></li>
                                <li>Clique no link de pagamento disponível</li>
                                <li>Pague via PIX (código copia-cola ou QR Code)</li>
                                <li>O pagamento será confirmado automaticamente</li>
                            </ul>

                            <!-- Alerta -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fee2e2; border-left:4px solid #ef4444; border-radius:4px; margin:20px 0;">
                                <tr>
                                    <td style="padding:15px 20px;">
                                        <p style="color:#991b1b; font-size:14px; margin:0; font-weight:500;">
                                            ⚠️ Importante: Se não houver pagamento até 5 dias após o vencimento, a sua loja será temporariamente inativada e não aparecerá mais para os clientes no site.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Footer -->
                            <p style="color:#6b7280; font-size:13px; line-height:1.5; margin:30px 0 0 0; text-align:center;">
                                Dúvidas? Entre em contato com nosso suporte.<br>
                                <strong>PetGre</strong> - Conectando você ao mundo pet 🐾
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
