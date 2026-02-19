# 📧 Sistema de Email - PetGre

## 🎯 Visão Geral

O sistema de email do PetGre utiliza **Resend** para envio de emails transacionais com 3.000 emails/mês gratuitos.

## 📋 Funcionalidades

### ✅ Implementado
- **EmailService**: Serviço central para envio de emails
- **EmailTestController**: Controller para testes e status
- **Rotas de teste**: `/api/email/test` e `/api/email/status`
- **Configuração dual**: Desenvolvimento (log) + Produção (SMTP)

### 🚧 Em Desenvolvimento
- **NovoLojistaMail**: Email de boas-vindas para novos cadastros
- **SenhaGeradaMail**: Email com senha temporária para funcionários
- **Integração nos controllers**: EmpresaController e UsuarioController

## ⚙️ Configuração

### Desenvolvimento (Recomendado)
```env
MAIL_MAILER=log
```
- **Vantagem**: Emails vão para log, sem envio real
- **Debug**: Ver conteúdo completo em `storage/logs/laravel.log`

### Produção (Resend)
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_USERNAME=resend
MAIL_PASSWORD=sua_api_key_resend
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=petgre@resend.dev
MAIL_FROM_NAME=PetGre
```

## 🧪 Testes

### Verificar Status
```bash
GET /api/email/status
```

### Enviar Email de Teste
```bash
GET /api/email/test?to=seuemail@exemplo.com
```

### Testar Email de Boas-Vindas
```bash
GET /api/email/test-bem-vindo?to=seuemail@exemplo.com
```
Simula o email enviado quando uma empresa é cadastrada com sucesso.

### Testar via Postman
Importe a coleção `PetHub_API_Collection.postman_collection.json` e use os endpoints:

1. **Email - Status da Configuração**
   - Verifica se o mailer está configurado corretamente

2. **Email - Teste Simples**
   - Configure a variável `{{email_teste}}` com seu email
   - Envia email de teste básico

3. **Email - Teste Boas-Vindas**
   - Simula o email completo de boas-vindas
   - Use autenticação com `{{token_lojista}}`

### Ver Logs (Desenvolvimento)
```bash
tail -f storage/logs/laravel.log
```

## 📧 Templates de Email

### Novo Lojista
- **Assunto**: Bem-vindo ao PetGre!
- **Conteúdo**: Boas-vindas, link do painel, próximos passos
- **Destinatário**: Email do master da empresa
- **Template**: `NovoLojistaMail`
- **View**: `emails.novo-lojista`

### Senha de Funcionário
- **Assunto**: Sua conta foi criada - PetGre
- **Conteúdo**: Senha temporária, link de login, instruções
- **Destinatário**: Email do funcionário criado

## 🚀 Como Usar

### 1. Instalar Dependências
```bash
composer require resend/resend-laravel
```

### 2. Configurar .env
- Copiar configurações do `.env.example`
- Para produção: obter API key no [Resend Dashboard](https://resend.com)

### 3. Integração Automática
O email de boas-vindas é enviado **automaticamente** quando:
- Uma empresa é cadastrada via `EmpresaController@store`
- O email é enviado para o endereço do usuário administrador
- Em caso de erro no email, a empresa ainda é criada (não falha a operação)

### 4. Usar o Service Manualmente
```php
use App\Services\EmailService;

$emailService = app(EmailService::class);

// Email simples
$emailService->sendEmail('user@example.com', 'Assunto', 'Corpo do email');

// Com Mailable
$emailService->sendMailable('user@example.com', new NovoLojistaMail($empresa, $usuario));
```

## 📊 Limites Resend (Plano Gratuito)

- **3.000 emails/mês**
- **Domínios personalizados**: `petgre@resend.dev`
- **Templates HTML**: Suportado
- **Anexos**: Até 10MB
- **Rastreamento**: Aberturas e cliques

## 🔧 Próximos Passos

1. **Criar Mailables** (`NovoLojistaMail`, `SenhaGeradaMail`)
2. **Integrar nos controllers** (`EmpresaController`, `UsuarioController`)
3. **Templates HTML** para emails bonitos
4. **Testes automatizados** para emails
5. **Monitoramento** de entregas

## 🆘 Troubleshooting

### Email não chega
1. Verificar se `MAIL_MAILER=smtp` (não log)
2. Confirmar API key válida no Resend
3. Verificar domínio verificado no Resend
4. Checar logs do Laravel

### Erro de autenticação
1. Confirmar `MAIL_USERNAME=resend`
2. Verificar `MAIL_PASSWORD` com API key correta
3. Checar se API key tem permissões de envio

---

*PetGre — Conectando você ao mundo pet 🐕🐱*