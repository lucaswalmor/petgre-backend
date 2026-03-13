<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Mensagem de Contato - PetGre</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #032d9e 0%, #14b33e 100%);
            padding: 30px;
            text-align: center;
        }
        .header img {
            max-width: 150px;
            margin-bottom: 15px;
        }
        .header h1 {
            color: white;
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #032d9e;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 0 8px 8px 0;
        }
        .info-box h3 {
            margin-top: 0;
            color: #032d9e;
            font-size: 18px;
        }
        .field {
            margin-bottom: 15px;
        }
        .field-label {
            font-weight: bold;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .field-value {
            color: #333;
            font-size: 14px;
        }
        .message-box {
            background: #fff;
            border: 1px solid #e0e0e0;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .message-box h3 {
            margin-top: 0;
            color: #14b33e;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ $logoUrl }}" alt="PetGre Logo">
            <h1>📨 Nova Mensagem de Contato</h1>
        </div>

        <div class="content">
            <div class="info-box">
                <h3>Informações do Remetente</h3>

                <div class="field">
                    <div class="field-label">Nome</div>
                    <div class="field-value">{{ $data['nome'] }}</div>
                </div>

                <div class="field">
                    <div class="field-label">Email</div>
                    <div class="field-value">
                        <a href="mailto:{{ $data['email'] }}" style="color: #032d9e; text-decoration: none;">
                            {{ $data['email'] }}
                        </a>
                    </div>
                </div>

                <div class="field">
                    <div class="field-label">Assunto</div>
                    <div class="field-value" style="color: #14b33e; font-weight: bold;">
                        {{ ucfirst(str_replace('_', ' ', $data['assunto'])) }}
                    </div>
                </div>

                @if(!empty($data['ip_address']))
                <div class="field">
                    <div class="field-label">IP Address</div>
                    <div class="field-value">{{ $data['ip_address'] }}</div>
                </div>
                @endif
            </div>

            <div class="message-box">
                <h3>📝 Mensagem</h3>
                <p style="white-space: pre-wrap; font-size: 14px; line-height: 1.8;">{{ $data['mensagem'] }}</p>
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <a href="mailto:{{ $data['email'] }}?subject=Re: {{ $data['assunto'] }}"
                   style="display: inline-block; background: linear-gradient(135deg, #032d9e 0%, #14b33e 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;">
                    📧 Responder Email
                </a>
            </div>
        </div>

        <div class="footer">
            <p>Esta mensagem foi enviada através da página de contato do PetGre</p>
            <p>&copy; {{ date('Y') }} PetGre. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
