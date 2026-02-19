<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperação de Senha - PetGre</title>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:#333;">

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc; padding:30px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff; border-radius:12px; box-shadow:0 4px 6px rgba(0,0,0,0.1); overflow:hidden;">

                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding:40px 40px 20px 40px;">
                            <h1 style="color:#1f2937; font-size:26px; font-weight:700; margin:0 0 8px 0;">🔐 Recuperação de Senha</h1>
                            <p style="color:#6b7280; font-size:15px; margin:0;">Olá, {{ $usuario->nome }}!</p>
                            <p style="color:#6b7280; font-size:15px; margin:8px 0 0 0;">Recebemos uma solicitação para redefinir sua senha no PetGre.</p>
                        </td>
                    </tr>

                    <!-- Código de Verificação -->
                    <tr>
                        <td align="center" style="padding:20px 40px;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6; border-radius:8px;">
                                <tr>
                                    <td style="padding:30px;">
                                        <h2 style="color:#1f2937; font-size:18px; font-weight:600; margin:0 0 15px 0; text-align:center;">Seu Código de Verificação</h2>

                                        <!-- Código em destaque -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td align="center" style="padding:20px;">
                                                    <table cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td width="60" height="60" align="center" valign="middle"
                                                                style="background-color:#3b82f6; border-radius:8px; color:#ffffff; font-size:24px; font-weight:700; line-height:60px; text-align:center; letter-spacing:2px;">
                                                                {{ substr($token, 0, 1) }}
                                                            </td>
                                                            <td width="10"></td>
                                                            <td width="60" height="60" align="center" valign="middle"
                                                                style="background-color:#3b82f6; border-radius:8px; color:#ffffff; font-size:24px; font-weight:700; line-height:60px; text-align:center; letter-spacing:2px;">
                                                                {{ substr($token, 1, 1) }}
                                                            </td>
                                                            <td width="10"></td>
                                                            <td width="60" height="60" align="center" valign="middle"
                                                                style="background-color:#3b82f6; border-radius:8px; color:#ffffff; font-size:24px; font-weight:700; line-height:60px; text-align:center; letter-spacing:2px;">
                                                                {{ substr($token, 2, 1) }}
                                                            </td>
                                                            <td width="10"></td>
                                                            <td width="60" height="60" align="center" valign="middle"
                                                                style="background-color:#3b82f6; border-radius:8px; color:#ffffff; font-size:24px; font-weight:700; line-height:60px; text-align:center; letter-spacing:2px;">
                                                                {{ substr($token, 3, 1) }}
                                                            </td>
                                                            <td width="10"></td>
                                                            <td width="60" height="60" align="center" valign="middle"
                                                                style="background-color:#3b82f6; border-radius:8px; color:#ffffff; font-size:24px; font-weight:700; line-height:60px; text-align:center; letter-spacing:2px;">
                                                                {{ substr($token, 4, 1) }}
                                                            </td>
                                                            <td width="10"></td>
                                                            <td width="60" height="60" align="center" valign="middle"
                                                                style="background-color:#3b82f6; border-radius:8px; color:#ffffff; font-size:24px; font-weight:700; line-height:60px; text-align:center; letter-spacing:2px;">
                                                                {{ substr($token, 5, 1) }}
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                        <p style="color:#374151; font-size:14px; margin:20px 0 0 0; text-align:center;">
                                            Digite este código de 6 dígitos na tela de recuperação de senha.<br>
                                            <strong>O código expira em 15 minutos.</strong>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Instruções -->
                    <tr>
                        <td style="padding:20px 40px;">
                            <h3 style="color:#1f2937; font-size:16px; font-weight:600; margin:0 0 10px 0;">Como proceder:</h3>
                            <ol style="color:#4b5563; font-size:14px; line-height:1.6; margin:0; padding-left:20px;">
                                <li>Copie ou anote o código acima</li>
                                <li>Volte para a tela de recuperação de senha</li>
                                <li>Digite o código quando solicitado</li>
                                <li>Defina sua nova senha</li>
                            </ol>

                            <div style="background-color:#fef3c7; border:1px solid #f59e0b; border-radius:6px; padding:15px; margin:20px 0;">
                                <p style="color:#92400e; font-size:13px; font-weight:600; margin:0 0 5px 0;">⚠️ Segurança</p>
                                <p style="color:#92400e; font-size:13px; margin:0;">
                                    Este código é pessoal e intransferível. Se você não solicitou a recuperação de senha, ignore este email.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding:20px 40px 30px 40px; border-top:1px solid #e5e7eb;">
                            <p style="color:#374151; font-size:14px; font-weight:600; margin:0 0 6px 0;">PetGre — Conectando você ao mundo pet</p>
                            <p style="color:#9ca3af; font-size:13px; margin:0 0 12px 0;">Transformando o caos em organização, um pedido por vez.</p>
                            <p style="color:#9ca3af; font-size:12px; margin:0;">
                                Este email foi enviado automaticamente. Não responda diretamente.<br>
                                Se precisar de ajuda, acesse nosso site ou entre em contato.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>