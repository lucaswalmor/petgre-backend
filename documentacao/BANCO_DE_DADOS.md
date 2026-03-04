# Banco de Dados — PetGre Backend

## Visão geral

O banco é **MySQL**, gerenciado via **migrations** do Laravel. A estrutura é multiempresa: dados de lojistas e pedidos são isolados por empresa; clientes não são compartilhados entre empresas.

---

## Tabelas principais (por domínio)

### Usuários e autenticação

| Tabela | Descrição |
|--------|-----------|
| **usuarios** | Usuários do sistema (masters, funcionários, clientes). Campos: nome, email, password, telefone, ativo, is_master, tipo_cadastro (0=lojista, 1=cliente), primeiro_login (troca obrigatória de senha), email_verified_at, remember_token. Soft deletes. |
| **usuarios_empresas** | Vínculo N:N usuário ↔ empresa (quais empresas o usuário acessa). |
| **usuarios_enderecos** | Endereços do usuário (clientes e funcionários). CEP, rua, numero, complemento, bairro, cidade, estado, ponto_referencia, observacoes, ativo, endereco_padrao. Soft delete via ativo. |
| **usuarios_permissoes** | Permissões atribuídas ao usuário (N:N com tabela permissoes). |
| **personal_access_tokens** | Tokens da API (Laravel Sanctum). |
| **password_resets** | Código de recuperação de senha (token, expires_at, used_at). |
| **sessions** | Sessões web (se usado). |

### Empresas

| Tabela | Descrição |
|--------|-----------|
| **empresas** | Dados da empresa: razao_social, nome_fantasia, slug, email, telefone, cpf_cnpj, tipo_pessoa, path_logo, path_banner, nicho_id, cadastro_completo, ativo, fechada_manual (null = usa horário/pausas; true = fechada; false = aberta); empresa_matriz_id (nullable, FK empresas, onDelete restrict), is_matriz (boolean, default true). Matriz tem empresa_matriz_id null; filial aponta para a matriz. Soft deletes. |
| **empresa_endereco** | Endereço físico da empresa (um por empresa). |
| **empresa_configuracoes** | Configurações: faz_entrega, faz_retirada, whatsapp_pedidos, valor_entrega_padrao, valor_entrega_minimo, redes sociais, etc. |
| **empresa_horarios** | Horários de funcionamento por dia da semana (dia_semana, slug, horario_inicio, horario_fim, padrao). |
| **empresa_bairros_entregas** | Bairros atendidos com valor de entrega e valor mínimo (FK para bairros). |
| **empresa_formas_pagamentos** | Formas de pagamento aceitas pela empresa (FK para formas_pagamentos). |
| **empresa_pausas_agendadas** | Pausas agendadas (data_inicio, data_fim, motivo, recorrente). Consideradas em “loja aberta/fechada”. |

### Produtos

| Tabela | Descrição |
|--------|-----------|
| **produtos** | Catálogo: empresa_id, categoria_id, unidade_medida_id, tipo (produto/serviço), nome, slug, descricao, preco, estoque, estoque_minimo, imagem, destaque, ativo, marca, sku, preco_custo, peso, dimensões, ordem, preco_promocional, promocao_ate, tem_promocao, vende_granel. Soft deletes. |
| **categorias** | Categorias de produtos (ex.: Rações, Brinquedos, Serviços). |
| **unidades_medidas** | Unidade, Pacote, Quilo, Litro, Grama, etc. |

### Kits

| Tabela | Descrição |
|--------|-----------|
| **kits** | Kits de produtos: empresa_id (FK empresas), nome, descricao, imagem, preco (decimal 10,2), ativo (boolean). Soft deletes. |
| **kit_itens** | Itens do kit: kit_id (FK kits, cascade delete), produto_id (FK produtos), quantidade (integer, default 1). |

### Pedidos

| Tabela | Descrição |
|--------|-----------|
| **pedidos** | Cabeçalho: usuario_id, empresa_id, status_pedido_id, pagamento_id, subtotal, desconto, frete, total, observacoes, cupom_tipo, cupom_id, cupom_valor, ativo, foi_entrega. Soft deletes. |
| **pedido_items** | Itens do pedido: produto_id, kit_id (nullable, FK kits — preenchido quando o item veio da expansão de um KIT), quantidade, preco_unitario, preco_total, observacoes. |
| **pedido_endereco** | Snapshot do endereço no pedido (endereco_id referenciando usuarios_enderecos). |
| **pedido_forma_pagamento** | Forma de pagamento escolhida no pedido (relação com formas_pagamentos). |
| **pedido_historico_status** | Histórico de mudanças de status (status_pedido_id, observacoes). |
| **status_pedidos** | Status possíveis: pendente, confirmado, em_preparacao, em_entrega, entregue, cancelado. |

### Cupons

| Tabela | Descrição |
|--------|-----------|
| **empresa_cupons** | Cupons criados pela empresa: codigo, tipo (percentual/valor), valor, valor_minimo, data_inicio, data_fim, limite_uso, ativo. |
| **empresa_cupons_usados** | Registro de uso de cupom da empresa (usuario_id, pedido_id). |
| **sistema_cupons** | Cupons da plataforma (marketing). |
| **sistema_cupons_usados** | Uso de cupom do sistema (usuario_id, pedido_id). |
| **usuarios_cupons** | Cupons do sistema atribuídos a usuários (usado_em, pedido_id quando utilizado). |
| **empresa_resgates_cupons** | Resgates/restituição de cupom do sistema para a empresa (pedido_id, valor, status, data_solicitacao). |

### Avaliações

| Tabela | Descrição |
|--------|-----------|
| **empresa_avaliacoes** | Avaliações da empresa: usuario_id, pedido_id, nota (1.0–5.0), descricao (comentário). |
| **avaliacoes_moderacao** | Solicitações de moderação (avaliacao_id, empresa_id, motivo, status: pendente, em_analise, aprovado, rejeitado, observacao_moderador). |

### Outros

| Tabela | Descrição |
|--------|-----------|
| **empresa_favoritos** | Favoritos do cliente (usuario_id, empresa_id). Soft deletes. |
| **usuario_logs** | Logs de comportamento: usuario_id, empresa_id, produto_id, acao (ex.: adicionar_carrinho, remover_carrinho, trocar_loja, acesso_loja_aberta, acesso_loja_fechada), dados_adicionais, ip_address, user_agent. |
| **push_subscriptions** | Subscriptions Web Push para notificação de novo pedido (usuario_id, endpoint, public_key, auth_token). |
| **sidebar_menu** | Itens do menu do painel lojista (parent_id, chave, label, path, icon, permission_slug, ordem). Filtrado por permissão no login. |
| **faqs** | Perguntas frequentes (público). |
| **planilhas_terceiros** | Cadastro de ERPs/planilhas para importação de produtos. |
| **empresa_faturamento** | Dados de faturamento do master: usuario_id (FK usuarios), nome_titular, cpf_cnpj, email, telefone, chave_pix (nullable), tipo_chave_pix (enum: cpf, cnpj, email, telefone, aleatoria; nullable), assinatura_ativa (boolean, default false), asaas_customer_id (nullable), asaas_subscription_id (nullable), valor_atual (decimal 8,2 nullable), data_ativacao (timestamp nullable). nome_titular e cpf_cnpj não são atualizáveis via API. |
| **empresa_faturas** | Histórico de faturas por usuário master: usuario_id, asaas_payment_id (unique nullable), mes_referencia (YYYY-MM), valor, status (pendente, pago, vencido, cancelado), vencimento (date), pago_em (date nullable), pix_qrcode_base64 (text nullable), pix_copia_cola (text nullable), link_fatura (string nullable). |
| **usuario_faturamento_pedidos** | Contagem de pedidos por mês para disparo de assinatura: usuario_id (FK usuarios), mes_referencia (YYYY-MM), total_pedidos (default 0), assinatura_disparada (boolean default false). Unique (usuario_id, mes_referencia). |

### Dados mestres

| Tabela | Descrição |
|--------|-----------|
| **nichos_empresa** | Tipos de negócio: Petshop, Agropecuária, Veterinária, Banho e Tosa, Caça e Pesca. |
| **bairros** | Bairros por cidade/estado (ex.: Uberlândia-MG). |
| **formas_pagamentos** | Dinheiro, PIX, Cartão Crédito/Débito, Transferência. |
| **planos** | Planos de assinatura (nome, slug, valor, ativo). PlanosSeeder insere "Plano PetGre" (valor 39,90) se não existir. |
| **permissoes** | Permissões do sistema (slug: pedidos.index, produtos.store, empresas.criar_filial, etc.). Inclui "Criar Filial" (empresas.criar_filial) no PermissoesSeeder. |

### Infraestrutura Laravel

| Tabela | Descrição |
|--------|-----------|
| **cache** / **cache_locks** | Cache. |
| **jobs** / **job_batches** / **failed_jobs** | Filas. |

---

## Migrations (ordem de execução)

As migrations estão em `database/migrations/`. Ordem lógica (dependências):

1. permissoes, unidades_medidas, bairros, nichos_empresa, formas_pagamentos, planos, status_pedidos, categorias  
2. personal_access_tokens  
3. usuarios, password_reset_tokens, sessions  
4. usuarios_enderecos, cache, jobs  
5. empresas  
6. empresa_endereco, empresa_configuracoes, empresa_horarios, empresa_bairros_entregas, empresa_formas_pagamentos  
7. usuarios_empresas, usuarios_permissoes  
8. produtos  
9. kits, kit_itens  
10. pedidos, pedido_items, pedido_endereco, pedido_historico_status, pedido_forma_pagamento  
11. empresa_avaliacoes, empresa_cupons, empresa_cupons_usados, sistema_cupons, sistema_cupons_usados, usuarios_cupons  
12. add_cupom_fields_to_pedidos, empresa_resgates_cupons  
13. usuario_logs, empresa_favoritos  
14. faqs  
15. planilhas_terceiros, avaliacoes_moderacao  
15. add_primeiro_login_to_usuarios  
16. password_resets (nova tabela), sidebar_menu  
17. empresa_pausas_agendadas  
18. push_subscriptions  
19. add_fechada_manual_to_empresas, make_fechada_manual_nullable  
20. add_tipo_cadastro_to_usuarios, add_tipo_pessoa_to_empresas, add_foi_entrega_to_pedidos  
21. add_matriz_filial_to_empresas (empresa_matriz_id, is_matriz)
22. create_empresa_faturamento_table
23. create_empresa_faturas_table
24. add_asaas_fields_to_empresa_faturamento_table (asaas_customer_id, asaas_subscription_id, valor_atual, data_ativacao)
25. add_asaas_fields_to_empresa_faturas_table (asaas_payment_id, pix_qrcode_base64, pix_copia_cola, link_fatura; status com cancelado)
26. create_usuario_faturamento_pedidos_table

(Os nomes exatos dos arquivos podem variar; o importante é rodar `php artisan migrate` na ordem padrão do Laravel.)

---

## Seeders

| Seeder | Descrição |
|--------|-----------|
| **DatabaseSeeder** | Ponto de entrada. Chama SistemaSeeder e opcionalmente cria usuário de teste. |
| **SistemaSeeder** | Dados iniciais: categorias, unidades_medidas, nichos_empresa, bairros, formas_pagamentos, planos, status_pedidos, permissoes. Chama PermissoesSeeder, UberlandiaBairrosSeeder, SidebarMenuSeeder, PlanosSeeder, etc. |
| **PermissoesSeeder** | Popula tabela **permissoes** (não duplica por slug; remove da tabela se removido do seeder). |
| **UberlandiaBairrosSeeder** | Popula **bairros** para Uberlândia-MG. |
| **SidebarMenuSeeder** | Popula **sidebar_menu** (itens e subitens do painel lojista). Não duplica por `chave`. |
| **PlanosSeeder** | Insere plano "Plano PetGre" (slug plano-petgre, valor 39,90, ativo) na tabela **planos** se ainda não existir. Chamado pelo SistemaSeeder. |
| **FaqSeeder** | Popula **faqs** (se existir). |
| **PlanilhaTerceirosSeeder** | Popula **planilhas_terceiros** (ERPs para importação). |
| **CupomBoasVindasSeeder** / **CupomPersonalizadoSeeder** | Cupons do sistema / personalizados (se usados). |
| **EmpresaProdutosSeeder** | Dados de exemplo de produtos (se usado em desenvolvimento). |

Para rodar todos: `php artisan db:seed`. Para um seeder: `php artisan db:seed --class=NomeDoSeeder`.

---

## Relacionamentos principais (Eloquent)

- **User (usuarios):** empresas (N:N), enderecos (hasMany), permissoes (N:N).  
- **Empresa:** nicho, endereco (hasOne), configuracoes (hasOne), horarios, bairrosEntregas, formasPagamentos, usuarios (N:N), produtos, pedidos, avaliacoes, cupons, pausasAgendadas, empresaFavoritos.  
- **Empresa::isAberta()** e **getFechadoAte()** consideram horários, pausas agendadas e fechada_manual (timezone America/Sao_Paulo).  
- **Pedido:** usuario, empresa, statusPedido, itens (pedido_items), endereco (pedido_endereco), formaPagamento, historicoStatus, avaliacao.  
- **Produto:** empresa, categoria, unidadeMedida, itens (pedido_items).

---

## Observações

- **Cadastro completo da empresa:** Verificado por endpoint e helpers (endereço, configurações, whatsapp_pedidos, formas de pagamento, horários, bairros de entrega). Quando 100%, `cadastro_completo` é atualizado.  
- **Privacidade em avaliações:** O lojista não vê dados do cliente (nome, e-mail, usuario_id); apenas nota, comentário, código do pedido e data.  
- **Soft deletes:** Usados em usuarios, empresas, produtos, pedidos, empresa_favoritos (e outros conforme definido no model).
