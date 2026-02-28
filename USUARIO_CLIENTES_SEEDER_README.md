# UsuarioClientesSeeder

Esta seeder cria usuários do tipo cliente (tipo_cadastro = 1) para testes do sistema PetGre.

## Clientes Criados

A seeder cria 5 usuários clientes com dados completos:

| Nome | Email | Senha | Telefone |
|------|-------|-------|----------|
| João Silva | cliente1@email.com | senha123 | (34) 99999-9991 |
| Maria Santos | cliente2@email.com | senha123 | (34) 99999-9992 |
| Pedro Oliveira | cliente3@email.com | senha123 | (34) 99999-9993 |
| Ana Costa | cliente4@email.com | senha123 | (34) 99999-9994 |
| Carlos Rodrigues | cliente5@email.com | senha123 | (34) 99999-9995 |

## Dados Incluídos

Cada cliente criado inclui:
- ✅ Dados pessoais (nome, email, telefone)
- ✅ Senha padrão: `senha123`
- ✅ Status ativo
- ✅ Um endereço padrão em Uberlândia/MG
- ✅ Endereços em bairros diferentes para testes de entrega

## Como Executar

### Executar apenas esta seeder:
```bash
php artisan db:seed --class=UsuarioClientesSeeder
```

### Executar com o DatabaseSeeder (inclui todas as seeders):
```bash
php artisan db:seed
```

### Forçar execução (mesmo em produção):
```bash
php artisan db:seed --class=UsuarioClientesSeeder --force
```

## Funcionalidades

- ✅ **Verificação de duplicatas**: Não cria usuários com emails já existentes
- ✅ **Endereços completos**: Cada cliente tem pelo menos um endereço cadastrado
- ✅ **Dados realistas**: Endereços em bairros reais de Uberlândia/MG
- ✅ **Feedback detalhado**: Mostra quantos clientes foram criados
- ✅ **Idempotente**: Pode ser executada múltiplas vezes sem problemas

## Uso nos Testes

Estes clientes podem ser usados para:

- ✅ **Testes de login cliente** (`/login` com tipo_login=cliente)
- ✅ **Testes de pedidos** (simular compras no carrinho)
- ✅ **Testes de endereços** (seleção de endereços de entrega)
- ✅ **Testes de faturamento** (atingir limite de 30 pedidos)
- ✅ **Testes de avaliações** (avaliar pedidos após entrega)

## Limpeza (se necessário)

Para remover estes usuários de teste:
```sql
DELETE FROM usuarios_enderecos WHERE usuario_id IN (
    SELECT id FROM usuarios WHERE email LIKE 'cliente%@email.com'
);
DELETE FROM usuarios WHERE email LIKE 'cliente%@email.com';
```

## Integração

Esta seeder é automaticamente executada quando você roda `php artisan db:seed`, pois está incluída no `DatabaseSeeder`.