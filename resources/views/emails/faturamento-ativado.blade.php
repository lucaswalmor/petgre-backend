<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinatura ativada</title>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; color:#333;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc; padding:30px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff; border-radius:12px; box-shadow:0 4px 6px rgba(0,0,0,0.1); overflow:hidden;">
                    <tr>
                        <td style="padding:40px;">
                            <h1 style="color:#3b82f6; font-size:24px; margin:0 0 20px 0;">PetGre</h1>
                            <h2 style="color:#1f2937; font-size:20px; margin:0 0 10px 0;">Olá, {{ $usuario->nome }}!</h2>
                            <p style="color:#4b5563; font-size:15px; line-height:1.6; margin:0 0 20px 0;">
                                Sua assinatura PetGre foi ativada. Valor: <strong>R$ {{ number_format($valor, 2, ',', '.') }}</strong>.
                            </p>
                            <p style="color:#4b5563; font-size:15px; line-height:1.6; margin:0;">
                                Vencimento: <strong>{{ $vencimento }}</strong>. O Asaas enviará o link/PIX para pagamento.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
