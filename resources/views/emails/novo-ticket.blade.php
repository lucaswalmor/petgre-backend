<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Chamado - PetGre</title>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:#333;">

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc; padding:30px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff; border-radius:12px; box-shadow:0 4px 6px rgba(0,0,0,0.1); overflow:hidden;">

                    <tr>
                        <td align="center" style="padding:40px 40px 20px 40px;">
                            <h1 style="color:#3b82f6; font-size:28px; margin:0 0 20px 0;">PetGre</h1>
                            <h2 style="color:#1f2937; font-size:22px; font-weight:700; margin:0 0 8px 0;">Novo chamado recebido</h2>
                            <p style="color:#6b7280; font-size:15px; margin:0;">Um lojista abriu um novo ticket de suporte.</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 40px;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6; border-radius:8px; padding:20px;">
                                <tr>
                                    <td style="padding:6px 0;">
                                        <strong style="color:#374151; font-size:14px;">Chamado #{{ $ticket->id }}</strong><br>
                                        <span style="color:#6b7280; font-size:14px;">{{ $ticket->assunto }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0 0 0; font-size:14px;">
                                        <strong style="color:#374151;">Empresa:</strong> {{ $ticket->empresa->nome_fantasia ?? $ticket->empresa->razao_social ?? 'N/A' }}<br>
                                        <strong style="color:#374151;">Aberto por:</strong> {{ $ticket->criadoPor->nome }} ({{ $ticket->criadoPor->email }})<br>
                                        <strong style="color:#374151;">Data:</strong> {{ $ticket->created_at->format('d/m/Y H:i') }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @if($ticket->mensagens->isNotEmpty())
                    <tr>
                        <td style="padding:10px 40px 20px 40px;">
                            <h3 style="color:#1f2937; font-size:16px; font-weight:600; margin:0 0 10px 0;">Mensagem:</h3>
                            <div style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:16px; color:#4b5563; font-size:14px; line-height:1.6;">
                                {!! $ticket->mensagens->first()->mensagem !!}
                            </div>
                        </td>
                    </tr>
                    @endif

                    <tr>
                        <td align="center" style="padding:20px 40px 30px 40px; border-top:1px solid #e5e7eb;">
                            <p style="color:#9ca3af; font-size:12px; margin:0;">Acesse o painel de chamados para responder.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
