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

                    <!-- Header com Logo -->
                    <tr>
                        <td align="center" style="padding:40px 40px 20px 40px;">
                            @if($logoBase64)
                                <img src="{{ $logoBase64 }}" alt="PetGre" width="140" style="display:block; margin:0 auto 20px auto;">
                            @else
                                <h1 style="color:#3b82f6; font-size:28px; margin:0 0 20px 0;">PetGre</h1>
                            @endif
                            <h1 style="color:#1f2937; font-size:26px; font-weight:700; margin:0 0 8px 0;">🎉 Bem-vindo ao PetGre!</h1>
                            <p style="color:#6b7280; font-size:15px; margin:0;">Olá, {{ $usuario->nome }}!</p>
                            <p style="color:#6b7280; font-size:15px; margin:8px 0 0 0;">Sua conta foi criada com sucesso!</p>
                        </td>
                    </tr>

                    <!-- Dados da Conta -->
                    <tr>
                        <td style="padding:20px 40px;">
                            <h3 style="color:#1f2937; font-size:18px; font-weight:600; margin:0 0 15px 0;">📋 Seus Dados</h3>
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6; border-radius:8px; padding:20px;">
                                <tr>
                                    <td style="padding:6px 20px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Nome:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $usuario->nome }}</td>
                                            </tr>
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Email:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $usuario->email }}</td>
                                            </tr>
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Telefone:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $usuario->telefone ?? 'Não informado' }}</td>
                                            </tr>
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Tipo de Conta:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">Cliente</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- O que você pode fazer -->
                    <tr>
                        <td style="padding:10px 40px 20px 40px;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#ecfdf5; border:1px solid #d1fae5; border-radius:8px;">
                                <tr>
                                    <td style="padding:25px;">
                                        <h3 style="color:#1f2937; font-size:18px; font-weight:600; margin:0 0 20px 0;">🚀 O que você pode fazer agora</h3>

                                        <!-- Item 1 -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;">
                                            <tr>
                                                <td width="36" valign="top">
                                                    <table cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td width="28" height="28" align="center" valign="middle"
                                                                style="background-color:#10b981; border-radius:50%; color:#ffffff; font-size:13px; font-weight:700; line-height:28px; text-align:center;">
                                                                🏪
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td style="padding-left:12px; vertical-align:top;">
                                                    <p style="margin:0 0 3px 0; color:#065f46; font-size:15px; font-weight:600;">Encontrar Empresas</p>
                                                    <p style="margin:0; color:#047857; font-size:13px;">Descubra petshops, veterinárias e outros serviços próximos a você</p>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Item 2 -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;">
                                            <tr>
                                                <td width="36" valign="top">
                                                    <table cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td width="28" height="28" align="center" valign="middle"
                                                                style="background-color:#10b981; border-radius:50%; color:#ffffff; font-size:13px; font-weight:700; line-height:28px; text-align:center;">
                                                                🛒
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td style="padding-left:12px; vertical-align:top;">
                                                    <p style="margin:0 0 3px 0; color:#065f46; font-size:15px; font-weight:600;">Fazer Pedidos</p>
                                                    <p style="margin:0; color:#047857; font-size:13px;">Compre produtos e serviços de forma organizada e segura</p>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Item 3 -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:16px;">
                                            <tr>
                                                <td width="36" valign="top">
                                                    <table cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td width="28" height="28" align="center" valign="middle"
                                                                style="background-color:#10b981; border-radius:50%; color:#ffffff; font-size:13px; font-weight:700; line-height:28px; text-align:center;">
                                                                ⭐
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td style="padding-left:12px; vertical-align:top;">
                                                    <p style="margin:0 0 3px 0; color:#065f46; font-size:15px; font-weight:600;">Avaliar Serviços</p>
                                                    <p style="margin:0; color:#047857; font-size:13px;">Ajude outros clientes deixando suas avaliações e comentários</p>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Item 4 -->
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td width="36" valign="top">
                                                    <table cellpadding="0" cellspacing="0" border="0">
                                                        <tr>
                                                            <td width="28" height="28" align="center" valign="middle"
                                                                style="background-color:#10b981; border-radius:50%; color:#ffffff; font-size:13px; font-weight:700; line-height:28px; text-align:center;">
                                                                🎁
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td style="padding-left:12px; vertical-align:top;">
                                                    <p style="margin:0 0 3px 0; color:#065f46; font-size:15px; font-weight:600;">Ganhar Descontos</p>
                                                    <p style="margin:0; color:#047857; font-size:13px;">Use cupons de desconto e aproveite promoções exclusivas</p>
                                                </td>
                                            </tr>
                                        </table>

                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Primeiro Pedido -->
                    <tr>
                        <td style="padding:20px 40px;">
                            <div style="background-color:#fef3c7; border:1px solid #f59e0b; border-radius:6px; padding:15px;">
                                <p style="color:#92400e; font-size:14px; font-weight:600; margin:0 0 8px 0;">💡 Dica: Faça seu primeiro pedido!</p>
                                <p style="color:#92400e; font-size:13px; margin:0;">
                                    Experimente nosso sistema fazendo um pedido em alguma loja próxima.
                                    É rápido, fácil e você ganha descontos exclusivos em seu primeiro pedido.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- Botão CTA -->
                    <tr>
                        <td align="center" style="padding:10px 40px 30px 40px;">
                            <a href="{{ url('/') }}"
                               style="display:inline-block; background-color:#3b82f6; color:#ffffff; text-decoration:none; padding:14px 32px; border-radius:8px; font-weight:600; font-size:15px;">
                                Começar a Explorar
                            </a>
                            <p style="margin:16px 0 0 0; color:#6b7280; font-size:13px; line-height:1.5; text-align:center;">
                                Precisa de ajuda? Nossa equipe está aqui para te auxiliar<br>
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