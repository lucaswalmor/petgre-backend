<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resposta no chamado - PetGre</title>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:#333;">

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc; padding:30px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff; border-radius:12px; box-shadow:0 4px 6px rgba(0,0,0,0.1); overflow:hidden;">

                    <tr>
                        <td align="center" style="padding:40px 40px 20px 40px;">
                            <h1 style="color:#3b82f6; font-size:28px; margin:0 0 20px 0;">PetGre</h1>
                            <h2 style="color:#1f2937; font-size:22px; font-weight:700; margin:0 0 8px 0;">Resposta no seu chamado</h2>
                            <p style="color:#6b7280; font-size:15px; margin:0;">O suporte respondeu ao chamado #{{ $ticket->id }}.</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 40px;">
                            <p style="color:#4b5563; font-size:15px; margin:0 0 10px 0;">Olá, {{ $ticket->criadoPor->nome }}!</p>
                            <p style="color:#4b5563; font-size:15px; margin:0 0 15px 0;">Há uma nova resposta no chamado <strong>{{ $ticket->assunto }}</strong>.</p>
                            @if($ultimaMensagem)
                            <div style="background-color:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:16px; color:#1e40af; font-size:14px; line-height:1.6;">
                                {!! $ultimaMensagem->mensagem !!}
                            </div>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:20px 40px 30px 40px; border-top:1px solid #e5e7eb;">
                            <p style="color:#9ca3af; font-size:12px; margin:0;">Acesse o painel para ver a conversa completa e responder se precisar.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
