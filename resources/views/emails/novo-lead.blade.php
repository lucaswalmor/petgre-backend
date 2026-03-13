<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Lead - PetGre</title>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:#333;">

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc; padding:30px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff; border-radius:12px; box-shadow:0 4px 6px rgba(0,0,0,0.1); overflow:hidden;">

                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding:40px 40px 20px 40px; background:linear-gradient(135deg, #3b82f6 0%, #10b981 100%);">
                            <h1 style="color:#ffffff; font-size:28px; margin:0;">🎉 Novo Lead Interessado!</h1>
                            <p style="color:#e0f2fe; font-size:15px; margin:10px 0 0 0;">Um novo potencial cliente se cadastrou na landing page</p>
                        </td>
                    </tr>

                    <!-- Saudação -->
                    <tr>
                        <td style="padding:30px 40px 10px 40px;">
                            <h2 style="color:#1f2937; font-size:20px; font-weight:600; margin:0 0 15px 0;">👋 Olá, Equipe PetGre!</h2>
                            <p style="color:#4b5563; font-size:15px; line-height:1.6; margin:0;">
                                Um novo lead demonstrou interesse na plataforma PetGre através da landing page. Estes são os dados do potencial cliente:
                            </p>
                        </td>
                    </tr>

                    <!-- Dados do Lead -->
                    <tr>
                        <td style="padding:20px 40px;">
                            <h3 style="color:#1f2937; font-size:18px; font-weight:600; margin:0 0 15px 0;">📋 Dados do Lead</h3>
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6; border-radius:8px; padding:20px;">
                                <tr>
                                    <td style="padding:6px 20px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Nome:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $lead['nome'] }}</td>
                                            </tr>
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Email:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $lead['email'] }}</td>
                                            </tr>
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">WhatsApp:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $lead['whatsapp'] }}</td>
                                            </tr>
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">Tipo:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $lead['tipo_empresa'] ?? 'Não informado' }}</td>
                                            </tr>
                                            @if(isset($lead['utm_source']))
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">UTM Source:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $lead['utm_source'] }}</td>
                                            </tr>
                                            @endif
                                            @if(isset($lead['utm_medium']))
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">UTM Medium:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $lead['utm_medium'] }}</td>
                                            </tr>
                                            @endif
                                            @if(isset($lead['utm_campaign']))
                                            <tr>
                                                <td width="130" style="font-weight:600; color:#374151; font-size:14px; padding:4px 0;">UTM Campaign:</td>
                                                <td style="color:#6b7280; font-size:14px; padding:4px 0;">{{ $lead['utm_campaign'] }}</td>
                                            </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Ações Recomendadas -->
                    <tr>
                        <td style="padding:10px 40px 20px 40px;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#ecfdf5; border:1px solid #d1fae5; border-radius:8px;">
                                <tr>
                                    <td style="padding:25px;">
                                        <h3 style="color:#1f2937; font-size:18px; font-weight:600; margin:0 0 20px 0;">🎯 Ações Recomendadas</h3>

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
                                                    <p style="margin:0 0 3px 0; color:#065f46; font-size:15px; font-weight:600;">Entre em Contato Rapidamente</p>
                                                    <p style="margin:0; color:#047857; font-size:13px;">Ligue ou envie WhatsApp em até 24h para maior conversão</p>
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
                                                    <p style="margin:0 0 3px 0; color:#065f46; font-size:15px; font-weight:600;">Agende uma Demonstração</p>
                                                    <p style="margin:0; color:#047857; font-size:13px;">Mostre o painel e funcionalidades ao vivo</p>
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
                                                    <p style="margin:0 0 3px 0; color:#065f46; font-size:15px; font-weight:600;">Cadastre no Sistema</p>
                                                    <p style="margin:0; color:#047857; font-size:13px;">Após a conversão, faça o cadastro completo no painel</p>
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
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $lead['whatsapp']) }}?text=Olá {{ $lead['nome'] }}! Vi que você se interessou pelo PetGre. Posso te ajudar com alguma dúvida?"
                               style="display:inline-block; background-color:#10b981; color:#ffffff; text-decoration:none; padding:14px 32px; border-radius:8px; font-weight:600; font-size:15px;">
                                💬 Chamar no WhatsApp
                            </a>
                            <p style="margin:16px 0 0 0; color:#6b7280; font-size:13px; line-height:1.5; text-align:center;">
                                Lead recebido em: {{ now()->format('d/m/Y H:i:s') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding:20px 40px 30px 40px; border-top:1px solid #e5e7eb;">
                            <p style="color:#374151; font-size:14px; font-weight:600; margin:0 0 6px 0;">PetGre — Sistema para Empresas Pet</p>
                            <p style="color:#9ca3af; font-size:13px; margin:0 0 12px 0;">Transformando o caos em organização, um pedido por vez.</p>
                            <p style="color:#9ca3af; font-size:12px; margin:0;">
                                Este email foi enviado automaticamente pelo sistema de leads.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
