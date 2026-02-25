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

## Baixar banco de produção no ambiente local

No **seu computador** (não no servidor), na pasta do projeto, com as variáveis `PROD_DB_*` configuradas no `.env`:

```bash
php artisan db:pull-production
```

O comando baixa o dump do banco de produção (`lksoft04_pet`) e importa em um banco com o mesmo nome no localhost. O banco de desenvolvimento (`petgre`) não é alterado.

- `--dump-only` — apenas gera o arquivo .sql, sem importar
- `--no-clean` — mantém o arquivo de dump após importar
