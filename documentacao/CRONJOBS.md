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
- **Função:** Desativa empresas com faturas vencidas há 5+ dias
- **Arquivo:** `app/Console/Commands/DesativarEmpresasInadimplentes.php`
- **Ação:** Define `ativo = false` nas empresas + envia email de suspensão

### ⏰ **Diariamente às 02:00** - Backup do Banco
```bash
backup:database
```
- **Função:** Gera backup do banco e envia para R2/Cloudflare
- **Arquivo:** `app/Console/Commands/DatabaseBackup.php`
- **Destino:** `storage/app/backups/` (local) + R2 (nuvem)

## 🔧 Comandos Manuais para Teste

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
# Avisos de vencimento
php artisan faturamento:avisar-vencimento-proximo

# Desativação de empresas
php artisan faturamento:desativar-empresas-inadimplentes

# Backup
php artisan backup:database
```

## 📊 Monitoramento

### Verificar Logs:
```bash
# Logs do Laravel
tail -f storage/logs/laravel.log | grep -i "faturamento\|asaas"

# Logs específicos de comandos
grep "AvisarVencimentoProximo\|DesativarEmpresas" storage/logs/laravel.log
```

### Verificar Status dos Cron Jobs:
```bash
# Ver próximos agendamentos
php artisan schedule:list

# Executar scheduler manualmente
php artisan schedule:run
```

## ⚠️ Observações Importantes

1. **Horário do Servidor:** Certifique-se que o servidor está no horário correto (America/Sao_Paulo)
2. **Permissões:** O usuário que executa o cron deve ter permissões para:
   - Executar PHP
   - Escrever nos diretórios `storage/`
   - Conectar ao banco de dados
3. **Monitoramento:** Configure alertas para quando os crons falharem
4. **Backup:** Sempre teste o restore dos backups gerados

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

---

**📅 Última atualização:** Fevereiro 2026
**🔧 Configurado para:** HostGator/LK Software