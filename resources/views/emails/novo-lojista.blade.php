<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo ao PetGre</title>
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
                            <h1 style="color:#1f2937; font-size:26px; font-weight:700; margin:0 0 8px 0;">Bem-vindo ao PetGre!</h1>
                            <p style="color:#6b7280; font-size:15px; margin:0;">Sua empresa foi cadastrada com sucesso</p>
                        </td>
                    </tr>

                    <!-- Saudação -->
                    <tr>
                        <td style="padding:20px 40px 10px 40px;">
                            <h2 style="color:#1f2937; font-size:20px; font-weight:600; margin:0 0 10px 0;">Olá, {{ $usuario->nome }}!</h2>
                            <p style="color:#4b5563; font-size:15px; line-height:1.6; margin:0;">
                                Parabéns! Sua empresa foi cadastrada com sucesso na plataforma PetGre. Estamos felizes em ter você conosco para revolucionar o mercado pet da sua região.
                            </p>
                        </td>
                    </tr>

                    <!-- Dados da Empresa -->
                    <tr>
                        <td style="padding:20px 40px;">
                            <h3 style="color:#1f2937; font-size:18px; font-weight:600; margin:0 0 15px 0;">Dados da Empresa</h3>
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6; border-radius:8px; padding:20px;">
                                <tr>
                                    <td style="padding:6px 20px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Empresa:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $empresa->nome_fantasia }}</td>
                                            </tr>
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Razão Social:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $empresa->razao_social }}</td>
                                            </tr>
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Email:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $usuario->email }}</td>
                                            </tr>
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Telefone:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $usuario->telefone }}</td>
                                            </tr>
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Nicho:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $empresa->nicho->nome ?? 'Pet' }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Próximos Passos -->
                    <tr>
                        <td style="padding:10px 40px 20px 40px;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#ecfdf5; border:1px solid #d1fae5; border-radius:8px;">
                                <tr>
                                    <td style="padding:25px;">
                                        <h3 style="color:#1f2937; font-size:18px; font-weight:600; margin:0 0 20px 0;">🎯 Próximos Passos</h3>

                                        <!-- Passo 1 -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;">
                                            <tr>
                                                <td width="36" valign="top">
                                                    <table cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td width="28" height="28" align="center" valign="middle"
                                                                style="background-color:#10b981; border-radius:50%; color:#ffffff; font-size:13px; font-weight:700; line-height:28px; text-align:center;">
                                                                1
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td style="padding-left:12px; vertical-align:top;">
                                                    <p style="margin:0 0 3px 0; color:#065f46; font-size:15px; font-weight:600;">Faça Login</p>
                                                    <p style="margin:0; color:#047857; font-size:13px;">Acesse sua conta com o email e senha cadastrados</p>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Passo 2 -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;">
                                            <tr>
                                                <td width="36" valign="top">
                                                    <table cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td width="28" height="28" align="center" valign="middle"
                                                                style="background-color:#10b981; border-radius:50%; color:#ffffff; font-size:13px; font-weight:700; line-height:28px; text-align:center;">
                                                                2
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td style="padding-left:12px; vertical-align:top;">
                                                    <p style="margin:0 0 3px 0; color:#065f46; font-size:15px; font-weight:600;">Complete seu Cadastro</p>
                                                    <p style="margin:0; color:#047857; font-size:13px;">Adicione produtos, configure horários e formas de pagamento</p>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Passo 3 -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;">
                                            <tr>
                                                <td width="36" valign="top">
                                                    <table cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td width="28" height="28" align="center" valign="middle"
                                                                style="background-color:#10b981; border-radius:50%; color:#ffffff; font-size:13px; font-weight:700; line-height:28px; text-align:center;">
                                                                3
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td style="padding-left:12px; vertical-align:top;">
                                                    <p style="margin:0 0 3px 0; color:#065f46; font-size:15px; font-weight:600;">Configure WhatsApp</p>
                                                    <p style="margin:0; color:#047857; font-size:13px;">Conecte seu WhatsApp para receber pedidos automaticamente</p>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Passo 4 -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td width="36" valign="top">
                                                    <table cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td width="28" height="28" align="center" valign="middle"
                                                                style="background-color:#10b981; border-radius:50%; color:#ffffff; font-size:13px; font-weight:700; line-height:28px; text-align:center;">
                                                                4
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td style="padding-left:12px; vertical-align:top;">
                                                    <p style="margin:0 0 3px 0; color:#065f46; font-size:15px; font-weight:600;">Comece a Vender</p>
                                                    <p style="margin:0; color:#047857; font-size:13px;">Seus produtos estarão visíveis para milhares de clientes</p>
                                                </td>
                                            </tr>
                                        </table>

                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Botão CTA -->
                    <tr>
                        <td align="center" style="padding:10px 40px 30px 40px;">
                            <a href="{{ url('/') }}"
                               style="display:inline-block; background-color:#3b82f6; color:#ffffff; text-decoration:none; padding:14px 32px; border-radius:8px; font-weight:600; font-size:15px;">
                                Acessar Meu Painel
                            </a>
                            <p style="margin:16px 0 0 0; color:#6b7280; font-size:13px; line-height:1.5; text-align:center;">
                                Precisa de ajuda? Entre em contato conosco<br>
                                Email: suporte@petgre.com | WhatsApp: (34) 99999-9999
                            </p>
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