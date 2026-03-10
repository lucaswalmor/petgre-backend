# Regras de Negócio — PetGre Backend

Este documento reúne as regras de negócio implementadas no backend. Controllers, models e middlewares devem respeitá-las; ao alterar fluxos ou criar novos, manter esta documentação atualizada.

---

## 1. Autenticação e tipos de usuário

- **Login duplo:** o login exige `tipo_login` (lojista ou cliente). O backend valida que o usuário tenha `tipo_cadastro` correspondente (0 = lojista, 1 = cliente). Mesmo e-mail pode ter as duas contas; o token e os dados retornados dependem do tipo.
- **Lojista:** retorna user com permissoes, empresas e menu (sidebar filtrado por permissão). Cliente retorna user com enderecos.
- **Conta inativa:** usuário com ativo = false não pode fazer login (retorno 403).
- **Primeiro login (funcionário):** usuário criado como funcionário (empresa_id + permissoes) vem com primeiro_login = true e senha temporária por e-mail. No painel lojista, deve alterar a senha antes de acessar o restante; PUT /api/usuarios/alterar-senha-primeiro-login zera primeiro_login. Após trocar a senha com sucesso, o usuário recebe um email de confirmação (PasswordChangedMail via EmailService) apenas avisando que a troca foi concluída — a senha não é enviada no email.
- **Recuperação de senha:** código de 6 dígitos, expiração (ex.: 15 min), uso único. Fluxo: send-code → verify-code → change-password.

---

## 2. Multiempresa e isolamento

- **Lojistas:** só enxergam dados das **empresas às quais estão vinculados** (tabela usuarios_empresas). Todas as listagens e operações (usuários, produtos, pedidos, cupons, avaliações) devem filtrar por empresa(s) do usuário autenticado.
- **Contexto de empresa (x-empresa-id):** nas rotas do painel que dependem da "empresa atual", o frontend envia o header `x-empresa-id`. O middleware **EnsureEmpresaContext** valida se o usuário tem vínculo com essa empresa (usuarios_empresas) e faz `$request->merge(['empresa_id' => $id])`. Controllers usam `$request->empresa_id` (não ler o header diretamente). Rotas sem contexto (ex.: GET /empresa listagem, GET /user) não exigem o header.
- **Matriz e filiais:** empresa pode ser matriz (`is_matriz = true`, `empresa_matriz_id` null) ou filial (`is_matriz = false`, `empresa_matriz_id` = id da matriz). Filial só é criada via POST /api/empresa com `is_filial: true`, auth, header x-empresa-id com id da matriz, e permissão empresas.criar_filial (ou master). Não cria usuário; vincula o master da matriz e o usuário que criou à filial.
- **Clientes:** não têm empresa; não listam outros clientes. Só acessam seus próprios pedidos, endereços, favoritos e cupons atribuídos.
- **Verificação de acesso:** usar helper VerificaEmpresa (verificaEmpresaPertenceAoUsuario, verificaUsuariosMesmaEmpresa) antes de show/update/destroy de empresa, produto, pedido, etc.
- **Masters:** podem ter múltiplas empresas; funcionários pertencem a uma ou mais empresas. Listagem de usuários no painel: apenas usuários que compartilham pelo menos uma empresa com o usuário logado.

---

## 3. Empresa e cadastro completo

- **Cadastro completo:** a empresa só fica visível para clientes (listagem site) e “ativa” para receber pedidos quando cadastro_completo = true. Itens obrigatórios: (1) endereço da empresa, (2) configurações, (3) whatsapp_pedidos preenchido, (4) pelo menos uma forma de pagamento, (5) pelo menos um horário de funcionamento, (6) pelo menos um bairro de entrega. GET /api/empresa/:id/verificar-cadastro retorna percentual e itens_pendentes; ao salvar dados, o backend pode recalcular e atualizar cadastro_completo.
- **Itens pendentes com navegação:** O endpoint `verificar-cadastro` retorna itens pendentes como objetos estruturados: `{ titulo, navegacao, campo }`. Exemplo: `{ titulo: "Número do WhatsApp para receber pedidos", navegacao: "Configurações → Empresa → Aba 'Configurações'", campo: "Campo 'WhatsApp Pedidos' (ESSENCIAL para receber pedidos dos clientes)" }`. Isso permite o frontend mostrar ao usuário exatamente onde ir para completar cada item pendente.
- **WhatsApp para pedidos:** campo crítico em empresa_configuracoes. Sem ele o cadastro não é considerado completo.
- **Status da loja (aberta/fechada):** empresa_aberta considera horários, timezone America/Sao_Paulo, pausas agendadas e fechada_manual. fechada_manual: null = usa horário e pausas; true = fechada; false = aberta (força). getFechadoAte() retorna texto para exibição (ex.: "Abre às 14:00", "quando o lojista reabrir").

---

## 4. Pedidos

- **Criação:** apenas cliente (ou lojista em nome do cliente) cria pedido. Campos obrigatórios: empresa_id, usuario_id, itens, totais, forma de pagamento. Se entrega, endereço (endereco_id do usuario). Cupom opcional (cupom_tipo, cupom_id, cupom_valor); uso registrado ao confirmar (não ao criar).
- **Baixa de estoque:** ao criar o pedido (store), o backend faz a baixa de estoque de cada produto dos itens: produtos do tipo "servico" são ignorados; para produtos com `vende_granel`, a quantidade do item é convertida de gramas para kg (÷ 1000) para atualizar o estoque. A validação de estoque suficiente é feita no PedidoStoreRequest antes de confirmar o pedido.
- **Itens com kit_id:** cada item pode ter `produto_id` (produto avulso) ou `kit_id` (kit). Quando `kit_id` é enviado, o backend carrega o kit (da mesma empresa), expande em itens do kit (produto_id + quantidade por componente) e cria um `pedido_items` por produto (para estoque e histórico). O subtotal/total do pedido já vêm calculados pelo frontend (preço do kit × quantidade).
- **Status:** pendente → confirmado → em_preparacao → em_entrega → entregue; pode passar a cancelado em qualquer momento. Ao confirmar: marca cupom como usado (empresa ou sistema). Ao cancelar: devolve cupom ao cliente (sistema/empresa), cancela resgate de cupom do sistema para a loja e **recompõe o estoque** dos produtos do pedido (mesma regra de granel; serviços ignorados). A reposição só ocorre quando o status muda para cancelado (não se o pedido já estava cancelado).
- **Exclusão:** apenas pedidos com status **pendente** podem ser excluídos (DELETE). Retornar 400 para os demais.
- **Histórico:** toda alteração de status gera registro em pedido_historico_status (status_pedido_id, observacoes).
- **Push:** ao criar pedido, backend envia notificação Web Push para subscriptions da empresa (PushNotificationService).

---

## 5. Cupons

- **Cupom da empresa:** criado pelo lojista; código único por empresa; qualquer cliente pode usar se souber o código (respeitando validade, valor mínimo, limite de uso). Uso registrado em empresa_cupons_usados. Ao cancelar pedido, o uso é removido (cliente pode usar de novo). Loja não é restituída (desconto é dela).
- **Cupom do sistema:** atribuído a usuários (usuarios_cupons); cliente vê em "Meus cupons". Só pode usar se a empresa tiver aceita_cupons_sistema = true. Uso em sistema_cupons_usados; ao confirmar pedido marca como usado; ao cancelar devolve ao cliente. Ao marcar pedido como entregue, pode criar registro em empresa_resgates_cupons para restituição à loja (fluxo de saque é futuro).
- **Validação:** POST /api/pedidos/validar-cupom (cupom_codigo, empresa_id, valor_compra) valida cupom da empresa ou do sistema; retorna desconto e total. Não consumir o cupom aqui; consumir só ao confirmar o pedido.
- **Exclusão de cupom da empresa:** não permitir excluir cupom que já tem usos (empresa_cupons_usados). Retornar 400 com mensagem clara.

---

## 6. Avaliações

- **Criação:** apenas para pedido **entregue** e pelo **usuário** que fez o pedido. Uma avaliação por pedido (validar no store). Nota 1.0 a 5.0 (incrementos 0.5); comentário opcional (até 1000 caracteres).
- **Privacidade:** na API do painel lojista (GET /api/avaliacoes, show), **não** retornar usuario_id nem dados do cliente (nome, email). Retornar apenas nota, descrição, código do pedido, data. Resource e controllers já devem omitir esses campos.
- **Moderação:** lojista pode solicitar moderação (POST /api/avaliacoes/:id/solicitar-moderacao) com motivo (mín. 20 caracteres). Uma avaliação só pode ter uma solicitação (avaliacoes_moderacao). Status: pendente, em_analise, aprovado, rejeitado. Revisão do comentário é feita fora do sistema (admin/developer).

---

## 7. Usuários (funcionários e clientes)

- **Funcionário:** criado com empresa_id e permissoes; senha gerada e enviada por e-mail; primeiro_login = true. Endereço inicial pode ser o da empresa. Não pode deletar usuário master (is_master = true) nem a si mesmo.
- **Cliente:** criado sem empresa; pode ter endereço no cadastro. Clientes não são listados por lojistas (privacidade).
- **Soft delete:** usuários e endereços usam soft delete onde aplicável (deleted_at ou ativo = false).

---

## 8. Kits

- **Isolamento:** kits pertencem a uma empresa; listagens e operações filtram por empresa (header x-empresa-id).
- **Preço manual:** o preço do kit é definido pelo lojista (não é obrigatório ser a soma dos itens; pode ser promocional).
- **Itens obrigatórios:** todo kit deve ter pelo menos um produto (validado em StoreKitRequest e UpdateKitRequest).
- **Produtos da empresa:** os produtos que compõem o kit devem pertencer à mesma empresa do kit (validado nos requests).
- **Upload de imagem:** após criar o kit, o lojista pode enviar imagem via POST /api/kits/{id}/imagem (mesmo padrão de produtos: R2, formatos e tamanho máx. conforme request).

---

## 9. Produtos

- **Isolamento:** produtos pertencem a uma empresa; listagens e operações devem filtrar por empresa do usuário (lojista).
- **Estoque:** a baixa de estoque é feita ao criar o pedido (PedidoController::store). A reposição é feita ao cancelar o pedido (PedidoController::update quando status passa a cancelado). Produtos do tipo "servico" não têm movimentação de estoque. Produtos com `vende_granel` usam quantidade em kg no campo estoque (itens do pedido em gramas são convertidos).
- **Estoque mínimo:** o produto pode ter o toggle "Ativar Estoque Mínimo" (coluna `ativar_estoque_minimo`) e o valor em `estoque_minimo`. Após a baixa de estoque na criação do pedido, se o produto tiver ativar_estoque_minimo = true e estoque atual < estoque_minimo, o sistema envia um email (template emails/estoque-minimo) para todos os usuários vinculados à empresa, notificando que o produto X atingiu o estoque mínimo.
- **Promoção:** produto pode ter `tem_promocao`, `preco_promocional`, `preco_promocional_percentual` e `promocao_ate`. O preço efetivo (a cobrar) é calculado por `CalculosService::getPrecoEfetivo(Produto)`: se tem_promocao, preco_promocional preenchido e promocao_ate null ou >= hoje → preco_promocional; senão → preco. Ao salvar itens do pedido (PedidoController e SiteClienteController), o preço unitário dos itens é definido via `getPrecoEfetivo`. POST /api/produtos/calcular-promocao (preco_original, preco_promocional ou percentual) retorna o par preco_promocional/percentual para o frontend.
- **Site cliente (página da loja):** na resposta da empresa (SiteEmpresaResource), só são enviados produtos com estoque (tipo "servico" ou estoque > 0) e kits cujos itens tenham todos estoque suficiente para pelo menos 1 unidade do kit. Cada produto e cada kit expõe `quantidade_maxima` (máximo que o cliente pode pedir) para o frontend limitar a quantidade no carrinho. ProdutoResource inclui `quantidade_maxima` (serviço = null; granel = estoque em gramas; outro = estoque em unidades).
- **Exclusão:** não permitir excluir produto que possui itens em pedidos (pedido_items). Retornar 400 com mensagem adequada.
- **Nome único:** nome do produto deve ser único por empresa (validar no store/update quando aplicável).

---

## 10. Permissões e menu

- **Permissões:** middleware check.permission exige que o usuário tenha pelo menos uma das permissões informadas (ou seja master). Rotas do painel lojista devem usar requiresPermission no front e middleware no back.
- **Menu (sidebar):** itens na tabela sidebar_menu; filtrados por permissão no login (e em GET /api/user). Retornar em user_data.menu para o frontend renderizar. Novos itens via SidebarMenuSeeder (chave única para não duplicar).
- **Menu reativo por cadastro:** O frontend (AppSidebar.vue) verifica periodicamente (a cada 30 segundos) o endpoint `GET /api/empresa/{id}/verificar-cadastro`. Se o cadastro estiver incompleto, o menu é filtrado para mostrar apenas Dashboard e Configurações da Empresa; itens como Usuários, Produtos, Pedidos, Cupons, Avaliações, Chamados são ocultados até o cadastro ficar 100% completo. Isso garante que o lojista complete as informações essenciais antes de operar.

---

## 11. Outros

- **Favoritos:** cliente pode favoritar/desfavoritar empresa (toggle). Listagem de favoritos só retorna empresas ativas e com cadastro completo.
- **Endereços do cliente:** CRUD apenas do próprio usuario_id; endereço padrão único por usuário; soft delete (ativo = false).
- **Logs de comportamento:** registrar ações como adicionar_carrinho, remover_carrinho, trocar_loja, acesso_loja_aberta, acesso_loja_fechada (usuario_id, empresa_id, produto_id quando aplicável, ip, user_agent) para analytics do dashboard lojista.
- **Pausas agendadas:** datas em horário local (America/Sao_Paulo); considerar em Empresa::isAberta() e getFechadoAte().
- **Imagens:** upload para storage configurado (ex.: R2); tamanho e formatos conforme EmpresaUploadImageRequest / ProdutoUploadImageRequest (ex.: até 15MB, JPEG/PNG/GIF/WebP para empresa).
- **Faturamento:** apenas usuário master (sistema.acesso_total) acessa GET/POST/PUT /api/faturamento, GET /api/faturamento/resumo e GET /api/faturas (lista e show). Um único registro por usuario_id em empresa_faturamento; nome_titular e cpf_cnpj definidos apenas no store e nunca alterados via API. **Modelo MVP de Cobrança Condicional Mensal:** não há mais assinatura recorrente automática. A cobrança é gerada mensalmente com base no volume de pedidos do mês anterior.
- **Tipo de documento do titular:** O faturamento suporta `tipo_documento_titular` (enum: 'cpf', 'cnpj'; padrão 'cpf') na tabela `empresa_faturamento`. O campo determina a máscara de input no frontend (CPF: 000.000.000-00; CNPJ: 00.000.000/0000-00).

---

## 12. Faturamento e integração Asaas (Cobrança Condicional Mensal - MVP)

### 12.1 Modelo de Cobrança Condicional Mensal

O sistema utiliza um modelo **pay-as-you-go** (pague conforme usa) ao invés de assinatura recorrente fixa:

- **Regra de cobrança:** Todo dia 01 às 08:00, o sistema verifica os pedidos do **mês anterior** de cada empresa matriz ativa.
- **Limite para cobrança:** Se o total de pedidos (matriz + todas as filiais) foi **16 ou mais** → gera cobrança única no Asaas. Se foi **15 ou menos** → mês é **gratuito**, sem cobrança.
- **Cálculo do valor:** `valor_base + (quantidade_filiais_ativas × valor_base × 0.5)`
  - Valor base vem da tabela `planos` (campo `valor`)
  - Cada filial ativa adiciona 50% do valor base
  - Exemplo: R$39,90 base + 2 filiais = R$39,90 + R$39,90 = R$79,80

### 12.2 Geração de Cobranças (Cron Job)

- **Command:** `faturamento:gerar-cobrancas-mensais` (roda mensalmente no dia 01 às 08:00 via Schedule)
- **Processo:**
  1. Percorre todas as empresas matriz ativas (`is_matriz = true`, `ativo = true`)
  2. Conta pedidos do mês anterior (matriz + filiais ativas)
  3. Se ≥ 16 pedidos e não existe cobrança para o mês: calcula valor, cria cliente Asaas (se necessário), cria **cobrança única** (não assinatura) via PIX, vencimento em 5 dias
  4. Salva em `empresa_faturas`: empresa_id, mes_referencia, quantidade_pedidos, quantidade_filiais, asaas_payment_id, valor, status='pendente'
  5. Envia email de notificação ao master

### 12.3 Inadimplência e Bloqueio

- **Prazo:** 5 dias de vencimento para pagamento
- **Cron job:** `faturamento:desativar-empresas-inadimplentes` (diário às 09:00)
- **Processo:**
  1. Busca faturas com status 'vencido' há 5+ dias
  2. Para cada fatura: inativa a **matriz** (`empresa_faturas.empresa_id`) e **todas as suas filiais** (`empresas.empresa_matriz_id`)
  3. Envia email de suspensão ao master
  4. Empresa inativa não aparece no site para clientes

### 12.4 Reativação após Pagamento

- **Webhook Asaas:** `POST /api/webhooks/asaas` recebe eventos do Asaas
- **PAYMENT_RECEIVED / PAYMENT_CONFIRMED:**
  1. Marca fatura como 'pago' em `empresa_faturas`
  2. Reativa a matriz e todas as filiais automaticamente (`ativo = true`)
  3. Atualiza `assinatura_ativa = true` em `empresa_faturamento`

### 12.5 Webhook Asaas - Eventos Tratados

Rota pública; valida header `asaas-access-token` = `ASAAS_WEBHOOK_TOKEN`:

- **PAYMENT_CREATED:** Cria registro em `empresa_faturas` (caso não exista) com PIX (qrcode e copia-cola)
- **PAYMENT_RECEIVED/PAYMENT_CONFIRMED:** Marca fatura como 'pago', ativa matriz + filiais
- **PAYMENT_OVERDUE:** Marca fatura como 'vencido', envia email de notificação; se 5+ dias de atraso no webhook, desativa empresas
- **PAYMENT_DELETED/PAYMENT_REFUNDED:** Marca fatura como 'cancelado'

Resposta sempre HTTP 200; erros apenas logados.

### 12.6 API de Resumo para o Painel (Em Tempo Real)

**Endpoint:** `GET /api/faturamento/resumo`

Permite ao lojista acompanhar em tempo real o volume de pedidos e projeção de cobrança:

- **Contagem de pedidos:** Soma pedidos do mês atual (matriz + filiais ativas) em tempo real via consulta SQL
- **Limite gratuito:** Retorna `limite_gratuito: 15` e `pedidos_para_cobranca` (quantos faltam para 16 ou 0 se já atingiu)
- **Projeção de cobrança:** Se já atingiu 16+ pedidos, retorna `vai_ser_cobrado: true` e `valor_estimado_proxima_cobranca` calculado
- **Valor base:** Vem da tabela `planos` (campo `valor`)
- **Filiais:** Retorna `quantidade_filiais` ativas no momento
- **Próxima avaliação:** Dia 01 do próximo mês (quando a cobrança será gerada, se aplicável)
- **Fatura em aberto:** Se houver fatura pendente ou vencida, retorna detalhes com link de pagamento
- **Histórico:** Lista de faturas anteriores (mes_referencia, valor, status, quantidade_pedidos, quantidade_filiais)

**Importante:** A contagem é feita em tempo real a cada requisição (não é salva no banco continuamente). O lojista pode atualizar a tela para ver o número atualizado de pedidos a qualquer momento.

### 12.7 Tabela `empresa_faturas` (Campos Principais)

| Campo | Descrição |
|-------|-----------|
| `empresa_id` | ID da matriz (FK empresas) |
| `usuario_id` | ID do master (FK usuarios) |
| `mes_referencia` | Mês cobrado (YYYY-MM, ex: 2026-02) |
| `quantidade_pedidos` | Total de pedidos contados (matriz + filiais) |
| `quantidade_filiais` | Quantidade de filiais ativas consideradas |
| `valor` | Valor total da cobrança |
| `asaas_payment_id` | ID do pagamento no Asaas |
| `status` | pendente / pago / vencido / cancelado |
| `vencimento` | Data de vencimento (5 dias após geração) |
| `pix_qrcode_base64` | QR Code PIX em base64 |
| `pix_copia_cola` | Código PIX copia-e-cola |
| `link_fatura` | Link direto de pagamento Asaas |

### 12.8 Email de Notificação de Cobrança

**Template:** `emails.faturamento-ativado` (Blade)

**Classe:** `FaturamentoAtivadoMail`

**Quando é enviado:** Após a geração da cobrança mensal (`gerarCobrancaMensal`)

**Dados incluídos:**
- Mês de referência (ex: 2026-02)
- Quantidade de pedidos no período
- Quantidade de filiais ativas
- Valor total da cobrança
- Data de vencimento (5 dias após geração)
- Instruções de pagamento (acessar painel, link PIX)
- Aviso de inativação após 5 dias de atraso

**Destinatários:**
- Email do usuário master
- Email do titular do faturamento (se diferente do master)

**Assunto:** `Cobrança PetGre - {mes_referencia}`

---

## 13. Integração Evolution API (WhatsApp)

- **Uma instância por empresa:** cada empresa pode ter no máximo um registro em `empresa_evolution_whatsapp`. O nome da instância na Evolution API é único e segue o padrão `empresa_{empresa_id}`.
- **Status da instância:** vindo da Evolution API: `open` (conectado), `connecting` (aguardando QR/conectando), `close` (desconectado). O backend atualiza o campo `status` na tabela ao consultar a API e ao desconectar; ao conectar (status open), preenche `conectado_em` se ainda vazio.
- **Criar instância:** só é permitido se a empresa ainda não possui registro. Cria na Evolution API (POST /instance/create) e insere em `empresa_evolution_whatsapp` com status inicial `close`.
- **QR Code:** endpoint GET /api/evolution/whatsapp/qrcode só responde se existir instância e status ≠ open. Retorna base64 e/ou pairing_code conforme a Evolution API.
- **Desconectar:** chama DELETE /instance/logout na Evolution API e atualiza status para `close` e `conectado_em` = null. A instância continua cadastrada; o lojista pode gerar novo QR e reconectar.
- **Deletar:** chama DELETE /instance/delete na Evolution API e remove o registro da tabela. Após deletar, o lojista pode criar uma nova instância.
- **Variáveis de ambiente:** EVOLUTION_API_URL e EVOLUTION_API_KEY (config em config/services.php). Header `apikey` em todas as requisições à Evolution API.

---

## 14. Validações de Campos de Redes Sociais

### 14.1 Instagram e TikTok

**Regra:** Os campos `configuracoes.instagram` e `configuracoes.tiktok` aceitam dois formatos:
1. **Handle/Username:** `@usuario` (ex: `@rbshop`, `@Rbshop`)
2. **URL completa:** `https://instagram.com/usuario` ou `https://tiktok.com/@usuario`

**Implementação:** Validação customizada no `EmpresaUpdateRequest.php` usando closure:
- Aceita padrão `@[a-zA-Z0-9_.]+` para handles
- Aceita URLs válidas via `filter_var()`
- Aceita URLs específicas do domínio (instagram.com, tiktok.com)

**Mensagens de erro:**
- Instagram: "O Instagram deve ser um link válido ou @usuario."
- TikTok: "O TikTok deve ser um link válido ou @usuario."

**Campos relacionados:**
- Facebook: aceita qualquer string (não valida URL estritamente)
- YouTube: aceita qualquer string (não valida URL estritamente)
- LinkedIn: mantém validação de URL completa

---

## 15. Validações de Dados de Faturamento

### 15.1 CPF/CNPJ do Titular (Não Obrigatório)

**Regra:** No cadastro de dados de faturamento (`EmpresaFaturamentoRequest`), os campos são **opcionais** para permitir que o usuário salve parcialmente:
- `nome_titular`: nullable|string|max:255
- `tipo_documento_titular`: nullable|in:cpf,cnpj
- `cpf_cnpj`: nullable|string|max:20
- `email`: nullable|email|max:255
- `telefone`: nullable|string|max:20

**Objetivo:** Permitir que o lojista preencha as informações de faturamento de forma gradual, sem impedir o salvamento de outras abas quando ainda não tiver todos os dados do titular.

**Observação:** Os dados completos são necessários apenas quando houver geração de fatura (cobrança mensal).
