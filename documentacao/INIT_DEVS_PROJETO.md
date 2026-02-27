# Inicialização do projeto (dev e produção)

## Comando obrigatório ao subir o projeto (produção ou ambiente limpo)

Para popular **permissões**, **menu do painel lojista**, **bairros**, **planos** e demais dados mestres, execute:

```bash
php artisan migrate:fresh --seed
```

- **migrate:fresh** recria todas as tabelas (apaga dados existentes). Use em ambiente novo ou quando puder zerar o banco.
- **--seed** executa o `DatabaseSeeder`, que chama o `SistemaSeeder`.

Se em produção você **não** quiser apagar os dados, rode apenas as migrations e depois só os seeders:

```bash
php artisan migrate
php artisan db:seed
```

Assim as tabelas são criadas/atualizadas e em seguida permissões, sidebar (menus), bairros e planos são populados. **Sem o `db:seed`, o menu do painel e as permissões ficam vazios** e o lojista não verá itens no menu.

---

## O que o DatabaseSeeder executa (obrigatório)

| Seeder | Descrição |
|--------|-----------|
| **SistemaSeeder** | Categorias, status de pedidos, nichos, formas de pagamento, unidades de medida |
| → **PermissoesSeeder** | Tabela `permissoes` (slugs usados nas rotas e no menu) |
| → **UberlandiaBairrosSeeder** | Bairros de Uberlândia-MG |
| → **SidebarMenuSeeder** | Tabela `sidebar_menu` (itens do menu do painel lojista) |
| → **PlanosSeeder** | Plano "Plano PetGre" na tabela `planos` (faturamento) |

O usuário de teste (`test@example.com`) só é criado em ambiente **local** (ou se `CREATE_TEST_USER=true` no `.env`). Em produção o `db:seed` não cria esse usuário.

---

## Seeders opcionais (rodar com db:seed --class=)

Use quando precisar apenas daquele conjunto de dados, sem rodar todo o `DatabaseSeeder`:

| Comando | Descrição |
|---------|-----------|
| `php artisan db:seed --class=FaqSeeder` | Perguntas frequentes (site/app) |
| `php artisan db:seed --class=PlanilhaTerceirosSeeder` | ERPs para importação de produtos (upgestao, bling, tiny) |
| `php artisan db:seed --class=CupomBoasVindasSeeder` | Cupom de boas-vindas do sistema (se existir) |
| `php artisan db:seed --class=CupomPersonalizadoSeeder` | Cupons personalizados do sistema (se existir) |
| `php artisan db:seed --class=EmpresaProdutosSeeder` | Dados de exemplo de produtos (desenvolvimento) |
| `php artisan db:seed --class=FilialSeeder` | Dados de exemplo de filiais (desenvolvimento) |

Exemplo para popular FAQs após o deploy:

```bash
php artisan db:seed --class=FaqSeeder
```
