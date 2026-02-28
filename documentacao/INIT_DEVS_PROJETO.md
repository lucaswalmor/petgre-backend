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
| → **FaqSeeder** | Perguntas frequentes (tabela `faqs`) |
| → **PlanilhaTerceirosSeeder** | ERPs para importação de produtos (upgestao, bling, tiny) |

O usuário de teste (`test@example.com`) só é criado em ambiente **local** (ou se `CREATE_TEST_USER=true` no `.env`). Em produção o `db:seed` não cria esse usuário.

---

## Seeders opcionais (rodar com db:seed --class=)

Use quando precisar apenas daquele conjunto de dados, sem rodar todo o `DatabaseSeeder`:

| Comando | Descrição |
|---------|-----------|
| `php artisan db:seed --class=CupomBoasVindasSeeder` | Cupom de boas-vindas do sistema (se existir) |
| `php artisan db:seed --class=CupomPersonalizadoSeeder` | Cupons personalizados do sistema (se existir) |
| `php artisan db:seed --class=EmpresaProdutosSeeder` | Dados de exemplo de produtos (desenvolvimento) |
| `php artisan db:seed --class=FilialSeeder` | Dados de exemplo de filiais (desenvolvimento) |
| `php artisan db:seed --class=UsuarioClientesSeeder` | Usuários clientes para testes (cliente1@email.com até cliente5@email.com) |
| `php artisan db:seed --class=CreatePedidosSeeder` | 29 pedidos para teste de cobrança automática (cliente ID 3 → empresa ID 1) |

---

## 🧪 Teste de Cobrança Automática

Para testar o sistema de cobrança automática do PetGre:

### 1. Criar dados de teste
```bash
# Criar usuários clientes
php artisan db:seed --class=UsuarioClientesSeeder

# Criar 29 pedidos (não chega ao limite de 30)
php artisan db:seed --class=CreatePedidosSeeder
```

### 2. Verificar contagem de pedidos
```bash
# Usar o endpoint de teste
GET /api/test/billing-status?usuario_id=2
Authorization: Bearer {token_master}
```

### 3. Forçar cobrança automática
```bash
# Simular o 30º pedido
POST /api/test/simulate-billing
Authorization: Bearer {token_master}
Content-Type: application/json

{
  "usuario_id": 2,
  "forcar_disparo": true
}
```

### 4. Verificar resultado
- ✅ Cliente criado no Asaas
- ✅ Assinatura PIX criada
- ✅ Dados salvos em `empresa_faturamento`
- ✅ Status muda para ativo no painel

**Nota:** O sistema dispara cobrança automática após 30 pedidos no mês. Use as rotas de teste em `BILLING_TEST_README.md` para validar todo o fluxo.
