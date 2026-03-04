<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estoque Mínimo - PetGre</title>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:#333;">

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc; padding:30px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff; border-radius:12px; box-shadow:0 4px 6px rgba(0,0,0,0.1); overflow:hidden;">

                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding:40px 40px 20px 40px;">
                            <h1 style="color:#3b82f6; font-size:28px; margin:0 0 20px 0;">PetGre</h1>
                            <h2 style="color:#1f2937; font-size:22px; font-weight:700; margin:0 0 8px 0;">⚠️ Estoque mínimo atingido</h2>
                            <p style="color:#6b7280; font-size:15px; margin:0;">O produto abaixo atingiu ou ficou abaixo do estoque mínimo configurado.</p>
                        </td>
                    </tr>

                    <!-- Dados do produto -->
                    <tr>
                        <td style="padding:20px 40px;">
                            <h3 style="color:#1f2937; font-size:18px; font-weight:600; margin:0 0 15px 0;">📦 Produto</h3>
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fef3c7; border:1px solid #f59e0b; border-radius:8px; padding:20px;">
                                <tr>
                                    <td style="padding:6px 20px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td width="140" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Nome:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $produto->nome }}</td>
                                            </tr>
                                            <tr>
                                                <td width="140" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Estoque atual:</td>
                                                <td style="color:#b45309; font-size:14px; font-weight:600; padding:4px 0;">{{ number_format($produto->estoque, 3, ',', '.') }}</td>
                                            </tr>
                                            <tr>
                                                <td width="140" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Estoque mínimo:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ number_format($produto->estoque_minimo ?? 0, 3, ',', '.') }}</td>
                                            </tr>
                                            <tr>
                                                <td width="140" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Loja:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $empresa->nome_fantasia ?? 'N/A' }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Ação recomendada -->
                    <tr>
                        <td style="padding:10px 40px 20px 40px;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#ecfdf5; border:1px solid #d1fae5; border-radius:8px;">
                                <tr>
                                    <td style="padding:20px;">
                                        <p style="color:#065f46; font-size:14px; font-weight:600; margin:0 0 8px 0;">💡 Recomendação</p>
                                        <p style="color:#047857; font-size:13px; margin:0;">Acesse o painel PetGre e verifique o estoque deste produto. Considere fazer um novo pedido ao fornecedor para não perder vendas.</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding:20px 40px 30px 40px; border-top:1px solid #e5e7eb;">
                            <p style="color:#374151; font-size:14px; font-weight:600; margin:0 0 6px 0;">PetGre — Conectando você ao mundo pet</p>
                            <p style="color:#9ca3af; font-size:13px; margin:0 0 12px 0;">Transformando o caos em organização, um pedido por vez.</p>
                            <p style="color:#9ca3af; font-size:12px; margin:0;">
                                Este email foi enviado automaticamente. Por favor, não responda diretamente.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
