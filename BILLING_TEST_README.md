# 🧪 Teste do Sistema de Faturamento - BillingTestController

Este documento explica como usar as rotas de teste para validar o sistema de cobrança automática e integração com Asaas.

📮 **Rotas disponíveis no Postman:** Todas as rotas abaixo estão incluídas na coleção `PetHub_API_Collection.postman_collection.json` na pasta "Testes e Desenvolvimento" (ícones 💰).

## 📋 Pré-requisitos

- **Autenticação:** Todas as rotas requerem Bearer token
- **Usuário Master:** Precisa de pelo menos um usuário master cadastrado
- **Configuração Asaas:** API key válida no `.env`

## 🚀 Rotas Disponíveis

### 1. Listar Usuários Masters
```bash
GET /api/test/masters
Authorization: Bearer {token}
```

**Resposta:**
```json
{
  "success": true,
  "data": {
    "masters": [
      {
        "id": 1,
        "nome": "João Silva",
        "email": "joao@email.com",
        "empresas_count": 2,
        "empresas": [
          {
            "id": 1,
            "nome_fantasia": "PetShop do João",
            "ativo": true
          }
        ],
        "created_at": "2024-01-15 10:30:00"
      }
    ],
    "total": 1
  }
}
```

### 2. Verificar Status de Faturamento
```bash
GET /api/test/billing-status?usuario_id=1
Authorization: Bearer {token}
```

**Resposta:**
```json
{
  "success": true,
  "data": {
    "usuario": {
      "id": 1,
      "nome": "João Silva",
      "email": "joao@email.com"
    },
    "registro_atual": {
      "mes_referencia": "2024-01",
      "total_pedidos": 5,
      "assinatura_disparada": false
    },
    "faturamento": {
      "nome_titular": "João Silva",
      "cpf_cnpj": "12345678901",
      "email": "joao@email.com",
      "asaas_customer_id": null,
      "assinatura_ativa": false,
      "valor_atual": null
    },
    "constante_limite": 30,
    "limite_atingido": false
  }
}
```

### 3. Simular Cobrança
```bash
POST /api/test/simulate-billing
Authorization: Bearer {token}
Content-Type: application/json

{
  "usuario_id": 1,
  "forcar_disparo": false
}
```

**Parâmetros:**
- `usuario_id` (obrigatório): ID do usuário master
- `forcar_disparo` (opcional): true = força disparo mesmo sem 30 pedidos

**Resposta de Sucesso:**
```json
{
  "success": true,
  "message": "Simulação de cobrança executada",
  "data": {
    "usuario": { "id": 1, "nome": "João Silva", "email": "joao@email.com" },
    "registro_pedidos": {
      "mes_referencia": "2024-01",
      "total_pedidos": 30,
      "assinatura_disparada": true
    },
    "faturamento": {
      "asaas_customer_id": "cus_123456789",
      "asaas_subscription_id": "sub_123456789",
      "assinatura_ativa": true,
      "valor_atual": 39.9
    },
    "disparo_tentado": true,
    "disparo_sucesso": true
  }
}
```

### 4. Resetar Contadores
```bash
POST /api/test/reset-billing
Authorization: Bearer {token}
Content-Type: application/json

{
  "usuario_id": 1,
  "mes_referencia": "2024-01"
}
```

**Parâmetros:**
- `usuario_id` (obrigatório): ID do usuário master
- `mes_referencia` (opcional): Mês no formato YYYY-MM (padrão: mês atual)

### 5. Verificar Configuração Asaas
```bash
GET /api/test/asaas-config
Authorization: Bearer {token}
```

**Resposta:**
```json
{
  "success": true,
  "data": {
    "asaas_configurado": true,
    "base_url": "https://sandbox.asaas.com/api/v3",
    "api_key_exists": true,
    "webhook_token_exists": true
  }
}
```

## 🧪 Fluxo de Teste Completo

### 1. Configurar Asaas
- Adicionar API key válida no `.env`
- Verificar configuração: `GET /api/test/asaas-config`

### 2. Identificar Usuário Master
- Listar masters: `GET /api/test/masters`
- Escolher um usuário com empresas ativas

### 3. Verificar Status Inicial
- `GET /api/test/billing-status?usuario_id={id}`

### 4. Simular Cobrança
- `POST /api/test/simulate-billing` com `usuario_id` e `forcar_disparo: true`

### 5. Verificar Resultado
- Status atualizado: `GET /api/test/billing-status?usuario_id={id}`
- Cliente criado no Asaas
- Assinatura ativa

### 6. Resetar para Novos Testes
- `POST /api/test/reset-billing` para limpar contadores

## ⚠️ Avisos Importantes

- **Apenas desenvolvimento:** Estas rotas são para teste e devem ser removidas em produção
- **Dados reais:** A simulação cria registros reais no Asaas (cliente e assinatura)
- **Custos:** Assinaturas criadas podem gerar cobranças reais
- **Limpeza:** Use sempre o reset após testes para evitar dados duplicados

## 🔧 Troubleshooting

### Erro: "Asaas não configurado"
- Verificar se ASAAS_API_KEY está definida no `.env`
- Usar chaves de sandbox para testes

### Erro: "dados incompletos"
- Verificar se o usuário master tem dados de faturamento completos
- Acessar painel lojista > Configurações > Dados de Faturamento

### Assinatura não criada
- Verificar logs do Laravel
- Confirmar que todos os dados obrigatórios estão preenchidos
- Verificar saldo/validade da API key do Asaas