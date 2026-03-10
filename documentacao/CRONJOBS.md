# 🕒 Configuração de Cron Jobs - PetGre

Este arquivo contém todos os comandos necessários para configurar os cron jobs no servidor de produção.

## 📋 Comandos para Crontab

Execute o comando abaixo no seu servidor para editar o crontab:

```bash
crontab -e
```

Adicione estas linhas no final do arquivo:

```bash
# Laravel Scheduler - Executa todos os comandos agendados
* * * * * php /home4/lksoft04/pet.lksoftware.com.br/artisan schedule:run >> /dev/null 2>&1

# Backup diário do banco (caso não esteja no scheduler)
0 2 * * * php /home4/lksoft04/pet.lksoftware.com.br/artisan backup:database >> /dev/null 2>&1
```

## 📅 Cron Jobs Agendados

### ⏰ **Dia 01 de cada mês às 08:00** - Geração de Cobranças Mensais (NOVO MVP)
```bash
faturamento:gerar-cobrancas-mensais
```
- **Função:** Gera cobranças únicas (não assinaturas) para empresas com 16+ pedidos no mês anterior
- **Arquivo:** `app/Console/Commands/GerarCobrancasMensais.php`
- **Regras:**
  - Verifica pedidos do **mês anterior** (matriz + todas as filiais)
  - 16 ou mais pedidos → gera cobrança no Asaas (cobrança única PIX, vencimento 5 dias)
  - 15 ou menos pedidos → mês gratuito, sem cobrança
  - Valor: plano base + 50% por filial ativa
- **Duplicidade:** Verifica se já existe cobrança para o mês/empresa antes de criar
- **Notificação:** Envia email ao master com link de pagamento

### ⏰ **Diariamente às 08:00** - Avisos de Vencimento Próximo
```bash
faturamento:avisar-vencimento-proximo
```
- **Função:** Envia emails para clientes com faturas que vencem em 3 dias
- **Arquivo:** `app/Console/Commands/AvisarVencimentoProximo.php`
- **Assunto do email:** "PetGre - Lembrete: Fatura Vence em 3 Dias"

### ⏰ **Diariamente às 09:00** - Desativação de Empresas Inadimplentes
```bash
faturamento:desativar-empresas-inadimplentes
```
- **Função:** Desativa matriz e todas as filiais com faturas vencidas há 5+ dias
- **Arquivo:** `app/Console/Commands/DesativarEmpresasInadimplentes.php`
- **Ação:** 
  - Define `ativo = false` na matriz (`empresa_faturas.empresa_id`)
  - Define `ativo = false` em todas as filiais (`empresas.empresa_matriz_id`)
  - Envia email de suspensão ao master
- **Ciclo de cobrança:** Dia 01 (gera) → Dia 06 (inativa se não pagou)

### ⏰ **Diariamente às 02:00** - Backup do Banco
```bash
backup:database
```
- **Função:** Gera backup do banco e envia para R2/Cloudflare
- **Arquivo:** `app/Console/Commands/DatabaseBackup.php`
- **Destino:** `storage/app/backups/` (local) + R2 (nuvem)

## 🔧 Comandos Manuais para Teste

### Teste de Geração de Cobranças (Modo Seguro):
```bash
# Simula a geração sem criar cobranças reais
php artisan faturamento:gerar-cobrancas-mensais --dry-run

# Gera cobrança para um mês específico (ex: fevereiro/2026)
php artisan faturamento:gerar-cobrancas-mensais --mes=2026-02 --dry-run
```

### Teste de Avisos de Vencimento (Modo Seguro):
```bash
php artisan faturamento:avisar-vencimento-proximo --dry-run
```

### Teste de Desativação de Empresas (Modo Seguro):
```bash
php artisan faturamento:desativar-empresas-inadimplentes --dry-run
```

### Execução Manual:
```bash
# Geração de cobranças (para o mês anterior)
php artisan faturamento:gerar-cobrancas-mensais

# Geração para mês específico
php artisan faturamento:gerar-cobrancas-mensais --mes=2026-02

# Avisos de vencimento
php artisan faturamento:avisar-vencimento-proximo

# Desativação de empresas
php artisan faturamento:desativar-empresas-inadimplentes

# Backup
php artisan backup:database
```

## 📊 Fluxo Completo do Faturamento

```
DIA 01 (08:00) - faturamento:gerar-cobrancas-mensais
├── Para cada matriz ativa:
│   ├── Conta pedidos do mês anterior (matriz + filiais)
│   ├── Se >= 16 pedidos → cria cobrança única no Asaas
│   ├── Se <= 15 pedidos → mês gratuito
│   └── Envia email com link de pagamento
│
DIA 02-05 - Período de pagamento
├── Cliente pode pagar via PIX pelo link do Asaas
├── Webhook atualiza status automaticamente
│
DIA 06 (09:00) - faturamento:desativar-empresas-inadimplentes
├── Verifica faturas vencidas há 5+ dias
├── Inativa matriz + todas as filiais
└── Envia email de suspensão
│
APÓS PAGAMENTO - Webhook Asaas
├── PAYMENT_RECEIVED/CONFIRMED
├── Marca fatura como 'pago'
└── Reativa matriz + todas as filiais automaticamente
```

## 📊 Monitoramento

### Verificar Logs:
```bash
# Logs do Laravel
 tail -f storage/logs/laravel.log | grep -i "faturamento\|asaas\|cobranca"

# Logs específicos de comandos
grep "GerarCobrancasMensais\|DesativarEmpresas\|AvisarVencimento" storage/logs/laravel.log
```

### Verificar Status dos Cron Jobs:
```bash
# Ver próximos agendamentos
php artisan schedule:list

# Executar scheduler manualmente
php artisan schedule:run
```

### Verificar Cobranças Geradas:
```bash
# Listar faturas do mês atual
php artisan tinker
>>> \App\Models\EmpresaFatura::where('mes_referencia', date('Y-m'))->get();
```

## ⚠️ Observações Importantes

1. **Horário do Servidor:** Certifique-se que o servidor está no horário correto (America/Sao_Paulo)
2. **Permissões:** O usuário que executa o cron deve ter permissões para:
   - Executar PHP
   - Escrever nos diretórios `storage/`
   - Conectar ao banco de dados
3. **Monitoramento:** Configure alertas para quando os crons falharem
4. **Backup:** Sempre teste o restore dos backups gerados
5. **Webhook Asaas:** Certifique-se de que a URL `/api/webhooks/asaas` está acessível publicamente para receber confirmações de pagamento

## 🚨 Troubleshooting

### Cron não executa:
```bash
# Verificar se cron está rodando
service cron status

# Verificar logs do sistema
grep CRON /var/log/syslog

# Testar comando manualmente
php /home4/lksoft04/pet.lksoftware.com.br/artisan schedule:run
```

### Erros de permissão:
```bash
# Ajustar permissões
chown -R www-data:www-data /home4/lksoft04/pet.lksoftware.com.br
chmod -R 755 /home4/lksoft04/pet.lksoftware.com.br/storage
```

### Emails não são enviados:
- Verificar configuração SMTP em `.env`
- Verificar logs: `storage/logs/laravel.log`
- Testar envio manual: `php artisan tinker` → `app(EmailService::class)->sendTestEmail()`

### Webhook não funciona:
- Verificar se a rota `/api/webhooks/asaas` está acessível
- Confirmar `ASAAS_WEBHOOK_TOKEN` no `.env` corresponde ao configurado no Asaas
- Verificar logs: `storage/logs/laravel.log | grep -i webhook`

### Cobranças não geradas:
- Verificar se Asaas está configurado (`ASAAS_API_KEY` no `.env`)
- Verificar se existem empresas matriz ativas: `Empresa::where('is_matriz', true)->where('ativo', true)->count()`
- Verificar logs: `storage/logs/laravel.log | grep -i "gerar.*cobranca"`

---

**📅 Última atualização:** Março 2026
**🔧 Configurado para:** HostGator/LK Software
**📝 Versão:** MVP - Cobrança Condicional Mensal
