<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo à Equipe - PetGre</title>
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
                            <h1 style="color:#1f2937; font-size:26px; font-weight:700; margin:0 0 8px 0;">👋 Bem-vindo à Equipe!</h1>
                            <p style="color:#6b7280; font-size:15px; margin:0;">Olá, {{ $usuario->nome }}!</p>
                            <p style="color:#6b7280; font-size:15px; margin:8px 0 0 0;">Você foi adicionado à equipe da {{ $empresa->nome_fantasia }}</p>
                        </td>
                    </tr>

                    <!-- Dados de Acesso -->
                    <tr>
                        <td style="padding:20px 40px;">
                            <h3 style="color:#1f2937; font-size:18px; font-weight:600; margin:0 0 15px 0;">🔑 Seus Dados de Acesso</h3>
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6; border-radius:8px; padding:20px;">
                                <tr>
                                    <td style="padding:6px 20px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Email:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $usuario->email }}</td>
                                            </tr>
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Senha Temporária:</td>
                                                <td style="color:#dc2626; font-size:14px; font-weight:600; padding:4px 0;">{{ $senha }}</td>
                                            </tr>
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Empresa:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $empresa->nome_fantasia }}</td>
                                            </tr>
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Tipo:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">Funcionário</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <div style="background-color:#fef3c7; border:1px solid #f59e0b; border-radius:6px; padding:15px; margin:20px 0;">
                                <p style="color:#92400e; font-size:13px; font-weight:600; margin:0 0 5px 0;">⚠️ Importante:</p>
                                <p style="color:#92400e; font-size:13px; margin:0;">
                                    Esta é uma senha temporária. Você deve alterá-la no primeiro acesso ao sistema.
                                </p>
                            </div>
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
                                                    <p style="margin:0 0 3px 0; color:#065f46; font-size:15px; font-weight:600;">Acesse o Sistema</p>
                                                    <p style="margin:0; color:#047857; font-size:13px;">Use seu email e a senha temporária para fazer login</p>
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
                                                    <p style="margin:0 0 3px 0; color:#065f46; font-size:15px; font-weight:600;">Altere sua Senha</p>
                                                    <p style="margin:0; color:#047857; font-size:13px;">Defina uma nova senha segura no seu primeiro acesso</p>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Passo 3 -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
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
                                                    <p style="margin:0 0 3px 0; color:#065f46; font-size:15px; font-weight:600;">Conheça o Sistema</p>
                                                    <p style="margin:0; color:#047857; font-size:13px;">Explore as funcionalidades disponíveis para sua função</p>
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
                            <a href="{{ url('/login') }}"
                               style="display:inline-block; background-color:#3b82f6; color:#ffffff; text-decoration:none; padding:14px 32px; border-radius:8px; font-weight:600; font-size:15px;">
                                Acessar Sistema
                            </a>
                            <p style="margin:16px 0 0 0; color:#6b7280; font-size:13px; line-height:1.5; text-align:center;">
                                Precisa de ajuda? Entre em contato com o administrador da empresa<br>
                                ou acesse nosso suporte: suporte@petgre.com
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