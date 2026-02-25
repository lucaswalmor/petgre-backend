# HostGator — Acesso e backup

## Acesso SSH

```bash
ssh -o PreferredAuthentications=password -o PubkeyAuthentication=no lksoft04@162.241.63.76 -p 2222
```

## Backup do banco de dados

No servidor (após acessar via SSH e estar na pasta do projeto):

```bash
php artisan backup:database
```

O comando gera o dump do MySQL e envia o arquivo para o R2 (conforme configurado no disco `r2`).
