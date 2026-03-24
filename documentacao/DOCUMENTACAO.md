# PetGre Backend - Documentação Completa

## 1. Visão Geral

### Para que serve este backend

O **petgre-backend** é a **API REST** do ecossistema PetGre. Ele é o backend único que atende:

- **Painel do Lojista** (petgre-lojista) — onde donos e funcionários de empresas pet gerenciam loja, produtos, pedidos, cupons, avaliações e configurações.
- **Site/App do Cliente** (petgre-cliente) — onde clientes finais buscam empresas, montam pedidos, acompanham entregas e avaliam.

Ou seja: um único backend serve as duas frentes (lojista e cliente), com autenticação e permissões que separam o que cada tipo de usuário pode fazer.

### Papel do PetGre (modelo de negócio)

O PetGre **não processa pagamentos** nem retém dinheiro. Ele atua como **intermediador digital**:

- Conecta clientes a empresas do nicho pet (petshops, agropecuárias, banho e tosa, veterinárias, etc.).
- Organiza catálogos, pedidos e histórico.
- Envia o pedido formatado para o WhatsApp da empresa.
- Controla status de pedidos e cadastros.

Pagamento e entrega ficam entre **cliente e empresa**; o backend só registra e estrutura as informações.

### Tecnologias

- **Laravel 11** (PHP 8.2+)
- **MySQL**
- **Laravel Sanctum** (API tokens)
- **Form Requests** e **API Resources** para validação e formatação de respostas
- **Middleware** de permissão (`check.permission`) para rotas do lojista
- Armazenamento de imagens (ex.: Cloudflare R2) configurável

---

## 2. Estrutura da API

### Padrão: novos controllers e services

Sempre que for criado um **novo controller** HTTP, criar também os **services** em `app/Services/{Dominio}/`, no mesmo estilo do projeto: **várias classes por tema** (listagem, CRUD, integrações, etc.), controller fino que só delega e responde em JSON. Exemplos de referência: `app/Services/Produto/`, `Empresa/`, `Pedido/`, `Usuario/`, `SiteCliente/`. Novas rotas devem entrar na coleção Postman do repositório quando fizer parte do escopo.

### 2.1. Controllers e funções

#### AuthController

| Método   | Função | Descrição |
|----------|--------|-----------|
| `login`  | POST /api/login | Login com email, senha e `tipo_login` (lojista ou cliente). Retorna token e dados do usuário (com menu, permissões e empresas para lojista). |
| `logout` | POST /api/logout | Revoga o token atual (requer auth). |
| `user`   | GET /api/user | Retorna dados do usuário autenticado (menu, permissões, empresas para lojista; endereços para cliente). |

#### UsuarioController

| Método | Função | Descrição |
|--------|--------|-----------|
| `store` | POST /api/usuarios | Cadastro de usuário: cliente (público) ou funcionário (com empresa_id e permissões; senha gerada e enviada por e-mail). |
| `index` | GET /api/usuarios | Lista usuários das empresas do lojista (filtros: empresa_id, ativo, is_master, nome, email). Permissão: usuarios.index. |
| `show` | GET /api/usuarios/{id} | Detalhes de um usuário (mesma empresa). Permissão: usuarios.show. |
| `update` | PUT /api/usuarios/{id} | Atualiza nome, email, telefone, ativo, senha e permissões. Permissão: usuarios.update. |
| `destroy` | DELETE /api/usuarios/{id} | Soft delete (não permite deletar a si mesmo nem master). Permissão: usuarios.destroy. |
| `alterarSenhaPrimeiroLogin` | PUT /api/usuarios/alterar-senha-primeiro-login | Troca de senha no primeiro login (funcionário). Requer auth. Após sucesso, envia email de confirmação (PasswordChangedMail) via EmailService — o email não contém a senha, apenas avisa que a troca foi concluída. |
| `enviarCodigoRecuperacao` | POST /api/change-password/send-code | Envia código de 6 dígitos por e-mail para recuperação de senha. |
| `verificarCodigoRecuperacao` | POST /api/change-password/verify-code | Valida o código antes de permitir alterar senha. |
| `alterarSenhaPublico` | POST /api/change-password | Altera senha usando e-mail + token de recuperação. |

A lógica deste controller está em `app/Services/Usuario/`: `UsuarioListagemPainelService` (listagem com `order_by` em whitelist e `per_page` 1–100), `UsuarioCadastroService` (cadastro cliente ou funcionário, vínculo empresa, endereço, e-mails de boas-vindas), `UsuarioPermissoesService` (sincronização de permissões e regra do dashboard para funcionários), `UsuarioConsultaPainelService`, `UsuarioAtualizacaoPainelService`, `UsuarioRemocaoPainelService`, `UsuarioRecuperacaoSenhaService` (envio/verificação de código e alteração de senha pública), `UsuarioSenhaPrimeiroLoginService` (troca no primeiro login e e-mail de confirmação com URL de login por `tipo_cadastro`).

#### EmpresaController

| Método | Função | Descrição |
|--------|--------|-----------|
| `index` | GET /api/empresa | Lista empresas vinculadas ao usuário autenticado (com filiais quando aplicável). Auth. |
| `store` | POST /api/empresa | Cria empresa: se body `is_filial` = true, exige auth + header x-empresa-id (matriz), permissão empresas.criar_filial ou master; cria só empresa (sem usuário), seta empresa_matriz_id e is_matriz=false, vincula master da matriz e usuário atual à filial. Após criar filial, chama FaturamentoService::recalcularValorAssinatura(master_id). Se is_filial = false (cadastro público), cria empresa matriz + usuário admin + endereço + configurações + horário padrão. |
| `show` | GET /api/empresa/{id} | Dados completos da empresa (com relacionamentos). Query `?basic=true` retorna só dados básicos. Permissão: empresas.show. |
| `update` | PUT /api/empresa/{id} | Atualiza empresa, configurações, horários, endereço, formas de pagamento, bairros. Permissão: empresas.update. |
| `destroy` | DELETE /api/empresa/{id} | Remove empresa. Permissão: empresas.destroy. |
| `uploadImage` | POST /api/empresa/{id}/upload-image | Upload de logo e/ou banner (query `tipo`: logo ou banner). Permissão: empresas.upload_image. |
| `verificarCadastro` | GET /api/empresa/{id}/verificar-cadastro | Retorna `cadastro_completo`, `percentual`, `itens_pendentes` (labels dos itens ainda não ok), `empresa_id`, `empresa_nome`. Permissão: empresas.verificar_cadastro. |
| `status` | GET /api/empresa/{id}/status | Retorna empresa_aberta, fechado_ate e fechada_manual (indicador no painel). Permissão: empresas.show. |
| `statusManual` | PUT /api/empresa/{id}/status-manual | Fecha ou abre loja manualmente (body: fechada_manual boolean). Permissão: empresas.update. |
| `bairrosDisponiveis` | GET /api/empresa/{empresaId}/bairros-disponiveis | Bairros da cidade da empresa para entrega. Permissão: empresas.show. |

A lógica de negócio deste controller está em `app/Services/Empresa/` (listagem, criação matriz/filial, imagens, consulta/atualização, progresso de cadastro, status da loja, verificação de cadastro e bairros).

- **Slug da empresa:** gerado automaticamente a partir do nome fantasia (ou razão social) em `store` e `update`. Se já existir empresa com o mesmo slug, é adicionado um sufixo aleatório de 8 caracteres (ex.: `lucas-steinbach` → `lucas-steinbach-a1b2c3d4`) para garantir unicidade.

#### EmpresaFaturamentoController

Rotas sob `auth:sanctum`, `empresa.context` e `check.permission:sistema.acesso_total` (apenas master).

| Método | Função | Descrição |
|--------|--------|-----------|
| `show` | GET /api/faturamento | Retorna dados de faturamento do usuário master autenticado. Se não existir, retorna faturamento: null. Inclui asaas_customer_id, asaas_subscription_id, valor_atual, data_ativacao. |
| `store` | POST /api/faturamento | Cria registro de faturamento (usuario_id = auth). Apenas se ainda não existir para esse usuário. Body: nome_titular, tipo_documento_titular ('cpf' ou 'cnpj'; padrão 'cpf'), cpf_cnpj, email, telefone, chave_pix (opcional), tipo_chave_pix (opcional). |
| `update` | PUT /api/faturamento | Atualiza apenas email, telefone, chave_pix e tipo_chave_pix. nome_titular e cpf_cnpj são ignorados se enviados. Se existir asaas_customer_id, sincroniza email e telefone no Asaas (AsaasService::atualizarCliente). |
| `resumo` | GET /api/faturamento/resumo | **Modelo MVP Condicional:** Retorna dados em tempo real para o painel do lojista acompanhar volume de pedidos. Inclui: `modelo_cobranca` (condicional_mensal), `matriz_id`, `mes_referencia_atual`, `pedidos_mes_atual` (contagem em tempo real da matriz + filiais), `quantidade_filiais` (ativas), `limite_gratuito` (15), `pedidos_para_cobranca` (quanto falta para 16 ou 0 se atingiu), `vai_ser_cobrado` (true se >= 16 pedidos), `valor_base`, `valor_estimado_proxima_cobranca` (se aplicável), `proxima_avaliacao` (dia 01 do próximo mês), `assinatura_ativa`, `fatura_em_aberto` (detalhes se houver fatura pendente/vencida), `faturas` (histórico). A contagem de pedidos é feita em tempo real via SQL (não salva no banco continuamente). |

#### EmpresaFaturasController

Rotas sob `auth:sanctum`, `empresa.context` e `check.permission:sistema.acesso_total` (apenas master).

| Método | Função | Descrição |
|--------|--------|-----------|
| `index` | GET /api/faturas | Lista faturas do usuário master (ordenadas por vencimento DESC). Retorno sem pix_qrcode_base64 para não pesar a listagem. |
| `show` | GET /api/faturas/{id} | Detalhe de uma fatura (valida que pertence ao usuário). Inclui pix_qrcode_base64 e pix_copia_cola para pagamento PIX. |

#### AsaasWebhookController

Rota **pública** (sem auth). Validação pelo header `asaas-access-token` comparado com `ASAAS_WEBHOOK_TOKEN`.

| Método | Função | Descrição |
|--------|--------|-----------|
| `handle` | POST /api/webhooks/asaas | Recebe eventos do Asaas: PAYMENT_CREATED (cria empresa_faturas com PIX), PAYMENT_RECEIVED/PAYMENT_CONFIRMED (marca pago, ativa empresas e assinatura), PAYMENT_OVERDUE (marca vencido; se ≥5 dias desativa empresas e envia email), PAYMENT_DELETED/PAYMENT_REFUNDED (marca cancelado). Sempre responde 200; erros são logados. |

#### ProdutoController

| Método | Função | Descrição |
|--------|--------|-----------|
| `index` | GET /api/produtos | Lista produtos das empresas do usuário (busca, filtros, paginação). Permissão: produtos.index. |
| `store` | POST /api/produtos | Cria produto. Permissão: produtos.store. |
| `show` | GET /api/produtos/{id} | Detalhes do produto. Permissão: produtos.show. |
| `update` | PUT /api/produtos/{id} | Atualiza produto. Permissão: produtos.update. |
| `destroy` | DELETE /api/produtos/{id} | Soft delete (não permite se produto está em pedidos). Permissão: produtos.destroy. |
| `uploadImage` | POST /api/produtos/{id}/upload-image | Upload de imagem do produto. Permissão: produtos.upload_image. |
| `toggleDestaque` | PATCH /api/produtos/{id}/toggle-destaque | Alterna destaque. Permissão: produtos.update. |
| `toggleAtivo` | PATCH /api/produtos/{id}/toggle-ativo | Alterna ativo. Permissão: produtos.update. |
| `duplicar` | POST /api/produtos/{id}/duplicar | Duplica produto (nome + " - Cópia"). Permissão: produtos.store. |
| `search` | GET /api/produtos/search/buscar | Busca rápida por nome/descrição, categoria, tipo. Permissão: produtos.index. |
| `listarCategorias` | GET /api/produtos/categorias | Lista categorias. Permissão: produtos.index. |
| `listarUnidadesMedidas` | GET /api/produtos/unidades-medidas | Lista unidades de medida. Permissão: produtos.index. |
| `calcularPromocao` | POST /api/produtos/calcular-promocao | Calcula preço promocional ou percentual de desconto (body: preco_original, preco_promocional? ou percentual?). Retorna preco_promocional e percentual. Permissão: produtos.index. |
| `storeLote` | POST /api/produtos/lote | Cadastro em lote de produtos. Permissão: produtos.store. |
| `destroyLote` | DELETE /api/produtos/lote | Exclui produtos em lote (ids). Permissão: produtos.destroy. |
| `listarTerceiros` | GET /api/produtos/importar/terceiros/lista | Lista ERPs/planilhas de terceiros para importação. Permissão: produtos.store. |
| `importar` | POST /api/produtos/importar | Importa produtos via planilha (tipo petgre ou terceiros). Permissão: produtos.store. |
| `downloadModelo` | GET /api/produtos/importar/modelo | Download do modelo de planilha PetGre. Permissão: produtos.store. |
| `downloadPlanilhaErros` | GET /api/produtos/importar/erros/download | Download da planilha de erros da última importação. Permissão: produtos.store. |

A lógica de negócio deste controller está em `app/Services/Produto/`: `ProdutoListagemService`, `ProdutoPromocaoService`, `ProdutoCrudService`, `ProdutoOperacoesRapidasService`, `ProdutoImagemService`, `ProdutoLoteService`, `ProdutoCatalogoAuxiliarService`, `ProdutoImportacaoPlanilhaService` (o controller apenas delega e formata a resposta HTTP).

#### PedidoController

| Método | Função | Descrição |
|--------|--------|-----------|
| `estatisticas` | GET /api/pedidos/estatisticas | KPIs para cards (pedidos hoje, faturamento mês, pendentes, avaliação média). Permissão: pedidos.index. |
| `index` | GET /api/pedidos | Lista pedidos (filtros: status_id, usuario_id, data_inicio, data_fim, tipo produto/serviço/misto). Ordenação: `order_by` apenas em colunas permitidas; `per_page` limitado (1–100). Permissão: pedidos.index. |
| `store` | POST /api/pedidos | Cliente cria pedido (itens, endereço opcional se retirada, cupom, frete). Define `tipo_pedido` e `data_agendamento` (regex nas observações) quando aplicável. Baixa estoque, push para empresa, notificação de estoque mínimo. |
| `show` | GET /api/pedidos/{id} | Detalhes do pedido (quem criou ou empresa do pedido). |
| `update` | PUT /api/pedidos/{id} | Atualiza status e observações; ao confirmar/entregar/cancelar trata cupons e estoque. Permissão: pedidos.update. |
| `destroy` | DELETE /api/pedidos/{id} | Exclui apenas pedidos pendentes. Permissão: pedidos.destroy. |
| `validarCupom` | POST /api/pedidos/validar-cupom | Valida cupom (código, empresa_id, valor_compra); retorna desconto e total. |

A lógica de negócio está em `app/Services/Pedido/` (`PedidoEstatisticasService`, `PedidoListagemPainelService`, `PedidoCriacaoClienteService`, `PedidoConsultaService`, `PedidoAtualizacaoPainelService`, `PedidoExclusaoService`, `PedidoCupomValidacaoService`, `PedidoDominioAuxiliarService`).

#### SiteClienteController

| Método | Função | Descrição |
|--------|--------|-----------|
| `getEmpresas` | GET /api/site/empresas | Público. Lista empresas ativas com cadastro completo (filtros: nicho, busca, cidade, bairro legado, abertas, avaliação, entrega/retirada, favoritos, ordenação). |
| `getEmpresa` | GET /api/site/empresa/{slug} | Público. Detalhes da empresa (produtos, kits, destaques, horários, avaliações, etc.). O array `destaques` contém até 12 produtos ativos com destaque=true (mesmo formato de ProdutoResource). Registra log de acesso se usuário logado. |
| `getProdutos` | GET /api/site/produtos | Público. Catálogo multi-loja com filtros (bairro, busca, categoria, promoção, ordenação). |
| `getOrdenacaoPublica` | GET /api/site/empresa/{empresaId}/ordenacao | Público. Ordem das seções da página da loja (serviços/produtos/kits). |
| `getPerfil` | GET /api/site/perfil | Perfil do cliente (auth). |
| `atualizarPerfil` | PUT /api/site/atualizar-perfil | Atualiza nome e telefone (auth). |
| `alterarSenha` | PUT /api/site/alterar-senha | Altera senha (senha_atual, senha_nova, confirmação) (auth). |
| `getPedidos` | GET /api/site/meus-pedidos | Histórico de pedidos do cliente (auth). |
| `getPedido` | GET /api/site/meu-pedido/{id} | Detalhes de um pedido do cliente (auth). |
| `getEnderecos` | GET /api/site/meus-enderecos | Endereços do cliente (auth). |
| `meusCupons` | GET /api/site/meus-cupons | Cupons do sistema atribuídos ao usuário não utilizados (auth). |

A lógica está em `app/Services/SiteCliente/` (`SiteClienteListagemEmpresasService`, `SiteClienteEmpresaPublicaService`, `SiteClientePedidoClienteService`, `SiteClientePerfilContaService`, `SiteClienteCatalogoProdutosService`).

#### UsuarioEnderecosController

| Método | Função | Descrição |
|--------|--------|-----------|
| `index` | GET /api/enderecos | Lista endereços ativos do usuário (auth). |
| `store` | POST /api/enderecos | Cria endereço; pode definir como padrão (auth). |
| `update` | PUT /api/enderecos/{id} | Atualiza endereço (auth). |
| `setPadrao` | PUT /api/enderecos/{id}/padrao | Define endereço como padrão (auth). |
| `destroy` | DELETE /api/enderecos/{id} | Desativa endereço (auth). |

#### EmpresaFavoritoController

| Método | Função | Descrição |
|--------|--------|-----------|
| `toggleFavorito` | POST /api/favoritos/toggle/{empresaId} | Adiciona ou remove empresa dos favoritos (auth). |
| `listarFavoritos` | GET /api/favoritos | Lista empresas favoritas do usuário (auth). |

#### KitController

Rotas sob `auth:sanctum`, `empresa.context` e permissões específicas. Header `x-empresa-id` obrigatório.

| Método | Função | Descrição |
|--------|--------|-----------|
| `estatisticas` | GET /api/kits/estatisticas | Total de kits, kits ativos, kits inativos, produto mais usado em kits. Permissão: kits.index. |
| `index` | GET /api/kits | Lista kits da empresa (paginação, filtros: ativo, q por nome). Permissão: kits.index. |
| `store` | POST /api/kits | Cria kit e itens em transação (nome, descricao, preco, ativo, itens[]). Permissão: kits.store. |
| `show` | GET /api/kits/{id} | Detalhe do kit com itens. Permissão: kits.show. |
| `update` | PUT /api/kits/{id} | Atualiza kit e recria itens (delete + insert). Permissão: kits.update. |
| `destroy` | DELETE /api/kits/{id} | Soft delete do kit. Permissão: kits.destroy. |
| `uploadImagem` | POST /api/kits/{id}/imagem | Upload de imagem do kit (multipart, campo imagem). Permissão: kits.upload_image. |
| `toggleAtivo` | PATCH /api/kits/{id}/toggle-ativo | Ativa/desativa kit. Permissão: kits.update. |

#### EmpresaCuponsController

| Método | Função | Descrição |
|--------|--------|-----------|
| `index` | GET /api/cupons | Lista cupons da empresa do usuário ativo (auth). |
| `store` | POST /api/cupons | Cria cupom da empresa (auth). |
| `show` | GET /api/cupons/{id} | Detalhes do cupom (auth). |
| `update` | PUT /api/cupons/{id} | Atualiza cupom (auth). |
| `destroy` | DELETE /api/cupons/{id} | Exclui cupom (não permite se já foi usado) (auth). |
| `toggleAtivo` | PUT /api/cupons/{id}/toggle-ativo | Ativa/desativa cupom (auth). |
| `usos` | GET /api/cupons/{id}/usos | Lista usos do cupom (auth). |
| `estatisticas` | GET /api/cupons/estatisticas/cupons | Estatísticas de cupons da empresa (auth). |

#### EmpresaAvaliacaoController

| Método | Função | Descrição |
|--------|--------|-----------|
| `avaliacoesPorEmpresa` | GET /api/avaliacoes/empresa/{empresaId} | Público. Lista avaliações da empresa (estatísticas, distribuição, paginação). |
| `index` | GET /api/avaliacoes | Lista avaliações das empresas do lojista (sem dados do cliente). Permissão: avaliacoes.index. |
| `store` | POST /api/avaliacoes | Cliente cria avaliação para pedido entregue (auth). |
| `show` | GET /api/avaliacoes/{id} | Detalhes da avaliação (sem dados do cliente). Permissão: avaliacoes.show. |
| `solicitarModeracao` | POST /api/avaliacoes/{id}/solicitar-moderacao | Lojista solicita moderação (motivo min 20 caracteres). Permissão: avaliacoes.index. |

#### DashboardController

| Método | Função | Descrição |
|--------|--------|-----------|
| `getDados` | GET /api/dashboard | Retorna KPIs, vendas 7 dias, últimos pedidos, avaliações recentes, produtos populares, horários de pico (auth). |

#### PausasAgendadasController

| Método | Função | Descrição |
|--------|--------|-----------|
| `index` | GET /api/pausas-agendadas | Lista pausas da empresa (query empresa_id). Permissão: pausas_agendadas.index ou sistema.acesso_total. |
| `store` | POST /api/pausas-agendadas | Cria pausa (data_inicio, data_fim, motivo, recorrente). Permissão: pausas_agendadas.store ou sistema.acesso_total. |
| `update` | PUT /api/pausas-agendadas/{id} | Atualiza pausa. Permissão: pausas_agendadas.update ou sistema.acesso_total. |
| `destroy` | DELETE /api/pausas-agendadas/{id} | Exclui pausa. Permissão: pausas_agendadas.destroy ou sistema.acesso_total. |

#### TicketController (Lojista — Chamados)

| Método | Função | Descrição |
|--------|--------|-----------|
| `index` | GET /api/tickets | Lista tickets da empresa do usuário (por empresa_id; qualquer usuário da empresa vê os chamados). Paginação: page, per_page. Auth. |
| `store` | POST /api/tickets | Abre novo ticket (subject, body). Cria primeira mensagem (tipo_remetente cliente), envia e-mail para usuários com desenvolvedor=1. Auth. |
| `show` | GET /api/tickets/{id} | Detalhes do ticket com mensagens. Valida por empresa_id. Auth. |
| `storeMessage` | POST /api/tickets/{id}/messages | Adiciona mensagem do cliente (body). Valida por empresa_id; status não fechado. Auth. |

#### ChamadosController (Admin / Desenvolvedor)

Acesso restrito a usuários com coluna `desenvolvedor = true`. Todas as ações validam essa condição no controller.

| Método | Função | Descrição |
|--------|--------|-----------|
| `index` | GET /api/chamados | Lista todos os tickets. Filtros: status, empresa (nome), data_inicio, data_fim. Paginação: page, per_page. |
| `show` | GET /api/chamados/{id} | Detalhes do ticket com mensagens. |
| `responder` | POST /api/chamados/{id}/responder | Responde ao ticket (mensagem). Atualiza status para respondido; envia e-mail ao cliente. |
| `concluir` | PATCH /api/chamados/{id}/concluir | Altera status do ticket para fechado. |
| `destroy` | DELETE /api/chamados/{id} | Exclui o ticket (e mensagens em cascade). |
| `concluirLote` | PATCH /api/chamados/concluir-lote | Conclui múltiplos tickets. Body: { ids: number[] }. |
| `excluirLote` | DELETE /api/chamados/excluir-lote | Exclui múltiplos tickets. Body: { ids: number[] }. |

#### UsuarioLogController

| Método | Função | Descrição |
|--------|--------|-----------|
| `getEstatisticasEmpresa` | GET /api/logs/estatisticas/empresa/{empresaId} | Estatísticas de logs da empresa para dashboard (auth). |
| `salvarLogAdicionarProdutoCarrinho` | POST /api/logs/adicionar-produto-carrinho | Registra ação "adicionar ao carrinho" (auth). |
| `salvarLogRemoverProdutoCarrinho` | POST /api/logs/remover-produto-carrinho | Registra ação "remover do carrinho" (auth). |
| `salvarLogTrocarLoja` | POST /api/logs/trocar-loja | Registra troca de loja (abandono de carrinho) (auth). |

#### PushSubscriptionController

| Método | Função | Descrição |
|--------|--------|-----------|
| `vapidPublicKey` | GET /api/push/vapid-public-key | Retorna chave pública VAPID para Web Push (auth). |
| `store` | POST /api/push/subscribe | Salva subscription do navegador para enviar notificação de novo pedido (auth). |

#### EvolutionMensagensService

Service dedicado ao disparo de mensagens via Evolution API (WhatsApp).

| Método | Descrição |
|--------|-----------|
| `enviarMensagemTexto(string $instanceName, string $numero, string $mensagem)` | Envia mensagem de texto para um número via WhatsApp. Formata o número (remove não numéricos), adiciona delay e presence. Retorna `['success' => bool, 'message' => string]`. |
| `formatarNumeroInternacional(string $telefone)` | Formata telefone brasileiro para formato internacional (55 + DDD + número). Retorna string ou null se inválido. |

**Endpoint Evolution utilizado:** `POST /message/sendText/{instance}`

#### MensagemPedidoHelper

Helper para geração de mensagens amigáveis de pedido para WhatsApp.

| Método | Descrição |
|--------|-----------|
| `gerarMensagemStatus(Pedido $pedido, string $statusSlug, ?string $observacao)` | Gera mensagem formatada com emojis quando o status do pedido é alterado. Mensagens adaptadas para contexto pet (sem referências a cozinheiro). Inclui nome da empresa, código do pedido, valor total e mensagem específica por status. |
| `gerarMensagemNovoPedido(Pedido $pedido)` | Gera mensagem de confirmação quando um novo pedido é criado. Lista os itens do pedido. |

**Fluxo de notificação:** O `PedidoController@update` detecta quando o status é alterado e chama `notificarClienteStatusAlterado()`, que verifica se a empresa tem instância Evolution conectada, formata o telefone do cliente e envia a mensagem via `EvolutionMensagensService`.

#### EmpresaEvolutionWhatsappController

Rotas sob `auth:sanctum` e `empresa.context`. Uma instância WhatsApp por empresa (Evolution API); nome da instância: `empresa_{empresa_id}`.

| Método | Função | Descrição |
|--------|--------|-----------|
| `index` | GET /api/evolution/whatsapp | Retorna dados da instância da empresa (ou null). Inclui status atualizado consultando a Evolution API. |
| `criar` | POST /api/evolution/whatsapp | Cria a instância na Evolution API e salva em empresa_evolution_whatsapp. Só permite se a empresa ainda não tem instância. |
| `buscarQrCode` | GET /api/evolution/whatsapp/qrcode | Retorna QR Code (base64) e/ou pairing_code para conexão. Disponível apenas se instância existir e status ≠ open. |
| `atualizarStatus` | GET /api/evolution/whatsapp/status | Consulta o status na Evolution API, atualiza a tabela e retorna o status (open, connecting, close). |
| `desconectar` | POST /api/evolution/whatsapp/desconectar | Chama logout na Evolution API e atualiza status para close na tabela. |
| `deletar` | DELETE /api/evolution/whatsapp | Deleta a instância na Evolution API e remove o registro da tabela. |

#### PermissaoController

| Método | Função | Descrição |
|--------|--------|-----------|
| `index` | GET /api/permissoes | Lista todas as permissões do sistema (auth). |

#### FaqController

| Método | Função | Descrição |
|--------|--------|-----------|
| `index` | GET /api/faqs | Lista FAQs (público). |
| `buscar` | GET /api/faqs/buscar | Busca em FAQs (público). |

#### EmailTestController (desenvolvimento)

| Método | Função | Descrição |
|--------|--------|-----------|
| `testBemVindo` | GET /api/email/test-bem-vindo | Teste e-mail boas-vindas lojista. |
| `testBemVindoFuncionario` | GET /api/email/test-bem-vindo-funcionario | Teste e-mail boas-vindas funcionário. |
| `testBemVindoCliente` | GET /api/email/test-bem-vindo-cliente | Teste e-mail boas-vindas cliente. |

---

## 3. Form Requests (validação)

- **Auth:** LoginRequest
- **Empresa:** EmpresaStoreRequest, EmpresaUpdateRequest, EmpresaUploadImageRequest
- **Usuários:** UsuarioStoreRequest, UsuarioUpdateRequest
- **Produto:** ProdutoStoreRequest, ProdutoUpdateRequest, ProdutoLoteRequest, ProdutoUploadImageRequest
- **Pedido:** PedidoStoreRequest, PedidoUpdateRequest
- **Kit:** StoreKitRequest, UpdateKitRequest, KitUploadImageRequest
- **EmpresaCupom:** EmpresaCupomStoreRequest, EmpresaCupomUpdateRequest
- **EmpresaAvaliacao:** EmpresaAvaliacaoStoreRequest
- **PausaAgendada:** PausaAgendadaStoreRequest, PausaAgendadaUpdateRequest

---

## 4. API Resources (formatação de resposta)

- ApiResourceCollection (paginação padrão)
- EmpresaResource, SiteEmpresaResource
- UsuarioResource, UsuarioLoginResource
- ProdutoResource
- PedidoResource
- KitResource, KitCollection
- EmpresaCupomResource, EmpresaCupomCollection
- EmpresaAvaliacaoResource
- PausaAgendadaResource
- EmpresaEvolutionWhatsappResource

---

## 5. Helpers

- **VerificaEmpresa:** verifica se usuário tem acesso à empresa; obter empresas do usuário; verificar se dois usuários são da mesma empresa.
- **FormatHelper:** formatSlug, formatOnlyNumbers (e similares).

---

## 6. Comandos Artisan

| Comando | Descrição |
|--------|-----------|
| **backup:database** | Gera dump MySQL (mysqldump), envia o arquivo .sql para o disco R2 em `backups/{APP_NAME_slug}/{database}_{date}.sql` (a pasta usa o nome da aplicação do `.env` para diferenciar projetos). Remove o arquivo local após envio. Agendado diariamente às 02:00 em `routes/console.php`. Em produção é necessário configurar o cron: `* * * * * php /caminho/artisan schedule:run`. No Windows com Laragon, o comando detecta automaticamente o `mysqldump` em `C:\laragon\bin\mysql\*`. |

---

## 7. Middleware

- **auth:sanctum** — Exige token válido.
- **check.permission:slug** — Exige que o usuário tenha a permissão (ou seja master). Usado nas rotas do painel lojista.
- **empresa.context** (EnsureEmpresaContext) — Exige header `x-empresa-id` com id de empresa; valida se o usuário tem vínculo em usuarios_empresas; em sucesso faz `$request->merge(['empresa_id' => $id])`. Aplicado nas rotas do painel que dependem da empresa atual (dashboard, empresa por id, produtos, pedidos, cupons, avaliações, usuarios, pausas-agendadas).

As rotas exatas estão em `routes/api.php`; para testes manuais use a coleção Postman do projeto. Testes automatizados de API (Feature): `AuthControllerTest`, `UsuarioControllerTest`, `EmpresaControllerTest`, `ProdutoControllerTest`, `PausasAgendadasControllerTest`, `PedidoControllerTest`, `PermissaoControllerTest`, `FaqControllerTest`, `DashboardControllerTest`, `UsuarioEnderecosControllerTest`, `EmpresaFavoritoControllerTest`, `EmpresaAvaliacaoControllerTest`, `EmpresaCuponsControllerTest`, `KitControllerTest`, `UsuarioLogControllerTest`, `SiteClienteControllerTest`, `PushSubscriptionControllerTest`.

---

## 8. Regras de Negócio

### 8.1. Autenticação e tipos de usuário

- **Login duplo:** o login exige `tipo_login` (lojista ou cliente). O backend valida que o usuário tenha `tipo_cadastro` correspondente (0 = lojista, 1 = cliente). Mesmo e-mail pode ter as duas contas; o token e os dados retornados dependem do tipo.
- **Lojista:** retorna user com permissoes, empresas e menu (sidebar filtrado por permissão). Cliente retorna user com enderecos.
- **Conta inativa:** usuário com ativo = false não pode fazer login (retorno 403).
- **Primeiro login (funcionário):** usuário criado como funcionário (empresa_id + permissoes) vem com primeiro_login = true e senha temporária por e-mail. No painel lojista, deve alterar a senha antes de acessar o restante; PUT /api/usuarios/alterar-senha-primeiro-login zera primeiro_login. Após trocar a senha com sucesso, o usuário recebe um email de confirmação (PasswordChangedMail via EmailService) apenas avisando que a troca foi concluída — a senha não é enviada no email.
- **Recuperação de senha:** código de 6 dígitos, expiração (ex.: 15 min), uso único. Fluxo: send-code → verify-code → change-password.

### 8.2. Multiempresa e isolamento

- **Lojistas:** só enxergam dados das **empresas às quais estão vinculados** (tabela usuarios_empresas). Todas as listagens e operações (usuários, produtos, pedidos, cupons, avaliações) devem filtrar por empresa(s) do usuário autenticado.
- **Contexto de empresa (x-empresa-id):** nas rotas do painel que dependem da "empresa atual", o frontend envia o header `x-empresa-id`. O middleware **EnsureEmpresaContext** valida se o usuário tem vínculo com essa empresa (usuarios_empresas) e faz `$request->merge(['empresa_id' => $id])`. Controllers usam `$request->empresa_id` (não ler o header diretamente). Rotas sem contexto (ex.: GET /empresa listagem, GET /user) não exigem o header.
- **Matriz e filiais:** empresa pode ser matriz (`is_matriz = true`, `empresa_matriz_id` null) ou filial (`is_matriz = false`, `empresa_matriz_id` = id da matriz). Filial só é criada via POST /api/empresa com `is_filial: true`, auth, header x-empresa-id com id da matriz, e permissão empresas.criar_filial (ou master). Não cria usuário; vincula o master da matriz e o usuário que criou à filial.
- **Clientes:** não têm empresa; não listam outros clientes. Só acessam seus próprios pedidos, endereços, favoritos e cupons atribuídos.
- **Verificação de acesso:** usar helper VerificaEmpresa (verificaEmpresaPertenceAoUsuario, verificaUsuariosMesmaEmpresa) antes de show/update/destroy de empresa, produto, pedido, etc.
- **Masters:** podem ter múltiplas empresas; funcionários pertencem a uma ou mais empresas. Listagem de usuários no painel: apenas usuários que compartilham pelo menos uma empresa com o usuário logado.

### 8.3. Empresa e cadastro completo

- **Cadastro completo:** a empresa só fica visível para clientes (listagem site) e "ativa" para receber pedidos quando cadastro_completo = true. Itens obrigatórios: (1) endereço da empresa, (2) configurações, (3) whatsapp_pedidos preenchido, (4) pelo menos uma forma de pagamento, (5) pelo menos um horário de funcionamento, (6) pelo menos um bairro de entrega. GET /api/empresa/:id/verificar-cadastro retorna percentual e itens_pendentes; ao salvar dados, o backend pode recalcular e atualizar cadastro_completo.
- **Itens pendentes com navegação:** O endpoint `verificar-cadastro` retorna itens pendentes como objetos estruturados: `{ titulo, navegacao, campo }`. Exemplo: `{ titulo: "Número do WhatsApp para receber pedidos", navegacao: "Configurações → Empresa → Aba 'Configurações'", campo: "Campo 'WhatsApp Pedidos' (ESSENCIAL para receber pedidos dos clientes)" }`. Isso permite o frontend mostrar ao usuário exatamente onde ir para completar cada item pendente.
- **WhatsApp para pedidos:** campo crítico em empresa_configuracoes. Sem ele o cadastro não é considerado completo.
- **Status da loja (aberta/fechada):** empresa_aberta considera horários, timezone America/Sao_Paulo, pausas agendadas e fechada_manual. fechada_manual: null = usa horário e pausas; true = fechada; false = aberta (força). getFechadoAte() retorna texto para exibição (ex.: "Abre às 14:00", "quando o lojista reabrir").

### 8.4. Pedidos

- **Criação:** apenas cliente (ou lojista em nome do cliente) cria pedido. Campos obrigatórios: empresa_id, usuario_id, itens, totais, forma de pagamento. Se entrega, endereço (endereco_id do usuario). Cupom opcional (cupom_tipo, cupom_id, cupom_valor); uso registrado ao confirmar (não ao criar).
- **Baixa de estoque:** ao criar o pedido (store), o backend faz a baixa de estoque de cada produto dos itens: produtos do tipo "servico" são ignorados; para produtos com `vende_granel`, a quantidade do item é convertida de gramas para kg (÷ 1000) para atualizar o estoque. A validação de estoque suficiente é feita no PedidoStoreRequest antes de confirmar o pedido.
- **Itens com kit_id:** cada item pode ter `produto_id` (produto avulso) ou `kit_id` (kit). Quando `kit_id` é enviado, o backend carrega o kit (da mesma empresa), expande em itens do kit (produto_id + quantidade por componente) e cria um `pedido_items` por produto (para estoque e histórico). O subtotal/total do pedido já vêm calculados pelo frontend (preço do kit × quantidade).
- **Status:** pendente → confirmado → em_preparacao → em_entrega → entregue; pode passar a cancelado em qualquer momento. Ao confirmar: marca cupom como usado (empresa ou sistema). Ao cancelar: devolve cupom ao cliente (sistema/empresa), cancela resgate de cupom do sistema para a loja e **recompõe o estoque** dos produtos do pedido (mesma regra de granel; serviços ignorados). A reposição só ocorre quando o status muda para cancelado (não se o pedido já estava cancelado).
- **Exclusão:** apenas pedidos com status **pendente** podem ser excluídos (DELETE). Retornar 400 para os demais.
- **Histórico:** toda alteração de status gera registro em pedido_historico_status (status_pedido_id, observacoes).
- **Push:** ao criar pedido, backend envia notificação Web Push para subscriptions da empresa (PushNotificationService).

### 8.5. Cupons

- **Cupom da empresa:** criado pelo lojista; código único por empresa; qualquer cliente pode usar se souber o código (respeitando validade, valor mínimo, limite de uso). Uso registrado em empresa_cupons_usados. Ao cancelar pedido, o uso é removido (cliente pode usar de novo). Loja não é restituída (desconto é dela).
- **Cupom do sistema:** atribuído a usuários (usuarios_cupons); cliente vê em "Meus cupons". Só pode usar se a empresa tiver aceita_cupons_sistema = true. Uso em sistema_cupons_usados; ao confirmar pedido marca como usado; ao cancelar devolve ao cliente. Ao marcar pedido como entregue, pode criar registro em empresa_resgates_cupons para restituição à loja (fluxo de saque é futuro).
- **Validação:** POST /api/pedidos/validar-cupom (cupom_codigo, empresa_id, valor_compra) valida cupom da empresa ou do sistema; retorna desconto e total. Não consumir o cupom aqui; consumir só ao confirmar o pedido.
- **Exclusão de cupom da empresa:** não permitir excluir cupom que já tem usos (empresa_cupons_usados). Retornar 400 com mensagem clara.

### 8.6. Avaliações

- **Criação:** apenas para pedido **entregue** e pelo **usuário** que fez o pedido. Uma avaliação por pedido (validar no store). Nota 1.0 a 5.0 (incrementos 0.5); comentário opcional (até 1000 caracteres).
- **Privacidade:** na API do painel lojista (GET /api/avaliacoes, show), **não** retornar usuario_id nem dados do cliente (nome, email). Retornar apenas nota, descrição, código do pedido, data. Resource e controllers já devem omitir esses campos.
- **Moderação:** lojista pode solicitar moderação (POST /api/avaliacoes/:id/solicitar-moderacao) com motivo (mín. 20 caracteres). Uma avaliação só pode ter uma solicitação (avaliacoes_moderacao). Status: pendente, em_analise, aprovado, rejeitado. Revisão do comentário é feita fora do sistema (admin/developer).

### 8.7. Usuários (funcionários e clientes)

- **Funcionário:** criado com empresa_id e permissoes; senha gerada e enviada por e-mail; primeiro_login = true. Endereço inicial pode ser o da empresa. Não pode deletar usuário master (is_master = true) nem a si mesmo.
- **Cliente:** criado sem empresa; pode ter endereço no cadastro. Clientes não são listados por lojistas (privacidade).
- **Soft delete:** usuários e endereços usam soft delete onde aplicável (deleted_at ou ativo = false).

### 8.8. Kits

- **Isolamento:** kits pertencem a uma empresa; listagens e operações filtram por empresa (header x-empresa-id).
- **Preço manual:** o preço do kit é definido pelo lojista (não é obrigatório ser a soma dos itens; pode ser promocional).
- **Itens obrigatórios:** todo kit deve ter pelo menos um produto (validado em StoreKitRequest e UpdateKitRequest).
- **Produtos da empresa:** os produtos que compõem o kit devem pertencer à mesma empresa do kit (validado nos requests).
- **Upload de imagem:** após criar o kit, o lojista pode enviar imagem via POST /api/kits/{id}/imagem (mesmo padrão de produtos: R2, formatos e tamanho máx. conforme request).

### 8.9. Produtos

- **Isolamento:** produtos pertencem a uma empresa; listagens e operações devem filtrar por empresa do usuário (lojista).
- **Estoque:** a baixa de estoque é feita ao criar o pedido (PedidoController::store). A reposição é feita ao cancelar o pedido (PedidoController::update quando status passa a cancelado). Produtos do tipo "servico" não têm movimentação de estoque. Produtos com `vende_granel` usam quantidade em kg no campo estoque (itens do pedido em gramas são convertidos).
- **Estoque mínimo:** o produto pode ter o toggle "Ativar Estoque Mínimo" (coluna `ativar_estoque_minimo`) e o valor em `estoque_minimo`. Após a baixa de estoque na criação do pedido, se o produto tiver ativar_estoque_minimo = true e estoque atual < estoque_minimo, o sistema envia um email (template emails/estoque-minimo) para todos os usuários vinculados à empresa, notificando que o produto X atingiu o estoque mínimo.
- **Promoção:** produto pode ter `tem_promocao`, `preco_promocional`, `preco_promocional_percentual` e `promocao_ate`. O preço efetivo (a cobrar) é calculado por `CalculosService::getPrecoEfetivo(Produto)`: se tem_promocao, preco_promocional preenchido e promocao_ate null ou >= hoje → preco_promocional; senão → preco. Ao salvar itens do pedido (PedidoController e SiteClienteController), o preço unitário dos itens é definido via `getPrecoEfetivo`. POST /api/produtos/calcular-promocao (preco_original, preco_promocional ou percentual) retorna o par preco_promocional/percentual para o frontend.
- **Site cliente (página da loja):** na resposta da empresa (SiteEmpresaResource), só são enviados produtos com estoque (tipo "servico" ou estoque > 0) e kits cujos itens tenham todos estoque suficiente para pelo menos 1 unidade do kit. Cada produto e cada kit expõe `quantidade_maxima` (máximo que o cliente pode pedir) para o frontend limitar a quantidade no carrinho. ProdutoResource inclui `quantidade_maxima` (serviço = null; granel = estoque em gramas; outro = estoque em unidades).
- **Exclusão:** não permitir excluir produto que possui itens em pedidos (pedido_items). Retornar 400 com mensagem adequada.
- **Nome único:** nome do produto deve ser único por empresa (validar no store/update quando aplicável).

### 8.10. Permissões e menu

- **Permissões:** middleware check.permission exige que o usuário tenha pelo menos uma das permissões informadas (ou seja master). Rotas do painel lojista devem usar requiresPermission no front e middleware no back.
- **Menu (sidebar):** itens na tabela sidebar_menu; filtrados por permissão no login (e em GET /api/user). Retornar em user_data.menu para o frontend renderizar. Novos itens via SidebarMenuSeeder (chave única para não duplicar).
- **Menu reativo por cadastro:** O frontend (AppSidebar.vue) verifica periodicamente (a cada 30 segundos) o endpoint `GET /api/empresa/{id}/verificar-cadastro`. Se o cadastro estiver incompleto, o menu é filtrado para mostrar apenas Dashboard e Configurações da Empresa; itens como Usuários, Produtos, Pedidos, Cupons, Avaliações, Chamados são ocultados até o cadastro ficar 100% completo. Isso garante que o lojista complete as informações essenciais antes de operar.

### 8.11. Outros

- **Favoritos:** cliente pode favoritar/desfavoritar empresa (toggle). Listagem de favoritos só retorna empresas ativas e com cadastro completo.
- **Endereços do cliente:** CRUD apenas do próprio usuario_id; endereço padrão único por usuário; soft delete (ativo = false).
- **Logs de comportamento:** registrar ações como adicionar_carrinho, remover_carrinho, trocar_loja, acesso_loja_aberta, acesso_loja_fechada (usuario_id, empresa_id, produto_id quando aplicável, ip, user_agent) para analytics do dashboard lojista.
- **Pausas agendadas:** datas em horário local (America/Sao_Paulo); considerar em Empresa::isAberta() e getFechadoAte().
- **Imagens:** upload para storage configurado (ex.: R2); tamanho e formatos conforme EmpresaUploadImageRequest / ProdutoUploadImageRequest (ex.: até 15MB, JPEG/PNG/GIF/WebP para empresa).
- **Faturamento:** apenas usuário master (sistema.acesso_total) acessa GET/POST/PUT /api/faturamento, GET /api/faturamento/resumo e GET /api/faturas (lista e show). Um único registro por usuario_id em empresa_faturamento; nome_titular e cpf_cnpj definidos apenas no store e nunca alterados via API. **Modelo MVP de Cobrança Condicional Mensal:** não há mais assinatura recorrente automática. A cobrança é gerada mensalmente com base no volume de pedidos do mês anterior.
- **Tipo de documento do titular:** O faturamento suporta `tipo_documento_titular` (enum: 'cpf', 'cnpj'; padrão 'cpf') na tabela `empresa_faturamento`. O campo determina a máscara de input no frontend (CPF: 000.000.000-00; CNPJ: 00.000.000/0000-00).

---

## 9. Faturamento e integração Asaas (Cobrança Condicional Mensal - MVP)

### 9.1. Modelo de Cobrança Condicional Mensal

O sistema utiliza um modelo **pay-as-you-go** (pague conforme usa) ao invés de assinatura recorrente fixa:

- **Regra de cobrança:** Todo dia 01 às 08:00, o sistema verifica os pedidos do **mês anterior** de cada empresa matriz ativa.
- **Limite para cobrança:** Se o total de pedidos (matriz + todas as filiais) foi **16 ou mais** → gera cobrança única no Asaas. Se foi **15 ou menos** → mês é **gratuito**, sem cobrança.
- **Cálculo do valor:** `valor_base + (quantidade_filiais_ativas × valor_base × 0.5)`
  - Valor base vem da tabela `planos` (campo `valor`)
  - Cada filial ativa adiciona 50% do valor base
  - Exemplo: R$39,90 base + 2 filiais = R$39,90 + R$39,90 = R$79,80

### 9.2. Geração de Cobranças (Cron Job)

- **Command:** `faturamento:gerar-cobrancas-mensais` (roda mensalmente no dia 01 às 08:00 via Schedule)
- **Processo:**
  1. Percorre todas as empresas matriz ativas (`is_matriz = true`, `ativo = true`)
  2. Conta pedidos do mês anterior (matriz + filiais ativas)
  3. Se ≥ 16 pedidos e não existe cobrança para o mês: calcula valor, cria cliente Asaas (se necessário), cria **cobrança única** (não assinatura) via PIX, vencimento em 5 dias
  4. Salva em `empresa_faturas`: empresa_id, mes_referencia, quantidade_pedidos, quantidade_filiais, asaas_payment_id, valor, status='pendente'
  5. Envia email de notificação ao master

### 9.3. Inadimplência e Bloqueio

- **Prazo:** 5 dias de vencimento para pagamento
- **Cron job:** `faturamento:desativar-empresas-inadimplentes` (diário às 09:00)
- **Processo:**
  1. Busca faturas com status 'vencido' há 5+ dias
  2. Para cada fatura: inativa a **matriz** (`empresa_faturas.empresa_id`) e **todas as suas filiais** (`empresas.empresa_matriz_id`)
  3. Envia email de suspensão ao master
  4. Empresa inativa não aparece no site para clientes

### 9.4. Reativação após Pagamento

- **Webhook Asaas:** `POST /api/webhooks/asaas` recebe eventos do Asaas
- **PAYMENT_RECEIVED / PAYMENT_CONFIRMED:**
  1. Marca fatura como 'pago' em `empresa_faturas`
  2. Reativa a matriz e todas as filiais automaticamente (`ativo = true`)
  3. Atualiza `assinatura_ativa = true` em `empresa_faturamento`

### 9.5. Webhook Asaas - Eventos Tratados

Rota pública; valida header `asaas-access-token` = `ASAAS_WEBHOOK_TOKEN`:

- **PAYMENT_CREATED:** Cria registro em `empresa_faturas` (caso não exista) com PIX (qrcode e copia-cola)
- **PAYMENT_RECEIVED/PAYMENT_CONFIRMED:** Marca fatura como 'pago', ativa matriz + filiais
- **PAYMENT_OVERDUE:** Marca fatura como 'vencido', envia email de notificação; se 5+ dias de atraso no webhook, desativa empresas
- **PAYMENT_DELETED/PAYMENT_REFUNDED:** Marca fatura como 'cancelado'

Resposta sempre HTTP 200; erros apenas logados.

### 9.6. API de Resumo para o Painel (Em Tempo Real)

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

### 9.7. Tabela `empresa_faturas` (Campos Principais)

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

### 9.8. Email de Notificação de Cobrança

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

## 10. Integração Evolution API (WhatsApp)

- **Uma instância por empresa:** cada empresa pode ter no máximo um registro em `empresa_evolution_whatsapp`. O nome da instância na Evolution API é único e segue o padrão `empresa_{empresa_id}`.
- **Status da instância:** vindo da Evolution API: `open` (conectado), `connecting` (aguardando QR/conectando), `close` (desconectado). O backend atualiza o campo `status` na tabela ao consultar a API e ao desconectar; ao conectar (status open), preenche `conectado_em` se ainda vazio.
- **Criar instância:** só é permitido se a empresa ainda não possui registro. Cria na Evolution API (POST /instance/create) e insere em `empresa_evolution_whatsapp` com status inicial `close`.
- **QR Code:** endpoint GET /api/evolution/whatsapp/qrcode só responde se existir instância e status ≠ open. Retorna base64 e/ou pairing_code conforme a Evolution API.
- **Desconectar:** chama DELETE /instance/logout na Evolution API e atualiza status para `close` e `conectado_em` = null. A instância continua cadastrada; o lojista pode gerar novo QR e reconectar.
- **Deletar:** chama DELETE /instance/delete na Evolution API e remove o registro da tabela. Após deletar, o lojista pode criar uma nova instância.
- **Variáveis de ambiente:** EVOLUTION_API_URL e EVOLUTION_API_KEY (config em config/services.php). Header `apikey` em todas as requisições à Evolution API.

---

## 11. Autenticação e Tokens (Multi-Dispositivo)

### 11.1. Múltiplas Sessões Simultâneas

**Regra:** O sistema permite que um usuário tenha múltiplas sessões ativas simultaneamente (vários dispositivos/computadores).

**Implementação:**
- Ao fazer login, um novo token é criado sem deletar tokens existentes
- Cada dispositivo possui seu próprio token independente
- O logout revoga apenas o token do dispositivo atual (`$request->user()->currentAccessToken()->delete()`)
- Tokens expiram automaticamente após período de inatividade (configurado no Sanctum)

**Motivo:** Permitir que lojistas acessem a conta de diferentes dispositivos (computador do escritório, notebook, celular) sem serem desconectados.

### 11.2. Segurança de Tokens

- Tokens são únicos por sessão (dispositivo/navegador)
- Logout em um dispositivo não afeta outros dispositivos
- Tokens podem ser revogados individualmente via API (logout)
- Revogação em massa só ocorre manualmente ou em casos específicos de segurança

---

## 12. CORS e Upload de Imagens

### 12.1. CORS (Cross-Origin Resource Sharing)

**Middleware:** `App\Http\Middleware\CorsMiddleware` - aplicado globalmente em todas as requisições via `bootstrap/app.php`.

**Headers configurados:**
- `Access-Control-Allow-Origin: *`
- `Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS`
- `Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Empresa-Id, Accept`
- `Access-Control-Allow-Credentials: true`
- `Access-Control-Max-Age: 86400`

**Objetivo:** Garantir que requisições cross-origin funcionem corretamente, especialmente para uploads de arquivos e requisições autenticadas do painel lojista.

### 12.2. Limites de Upload

**Tamanho máximo:** 5MB (5120 KB) para todas as imagens
- Logo da empresa: 5MB
- Banner da empresa: 5MB
- Imagens de produtos: 5MB
- Imagens de kits: 5MB

**Formatos aceitos:** jpeg, png, jpg, gif, webp

**Razão:** O limite de 5MB é definido para compatibilidade com a configuração padrão do nginx (client_max_body_size). Uploads maiores resultam em erro 413 (Content Too Large) do servidor.

**Mensagem de erro:** "A imagem não pode ter mais que 5MB. Reduza o tamanho da imagem."

---

## 13. Validações de Campos de Redes Sociais

### 13.1. Instagram e TikTok

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

## 14. Validações de Dados de Faturamento

### 14.1. CPF/CNPJ do Titular (Não Obrigatório)

**Regra:** No cadastro de dados de faturamento (`EmpresaFaturamentoRequest`), os campos são **opcionais** para permitir que o usuário salve parcialmente:
- `nome_titular`: nullable|string|max:255
- `tipo_documento_titular`: nullable|in:cpf,cnpj
- `cpf_cnpj`: nullable|string|max:20
- `email`: nullable|email|max:255
- `telefone`: nullable|string|max:20

**Objetivo:** Permitir que o lojista preencha as informações de faturamento de forma gradual, sem impedir o salvamento de outras abas quando ainda não tiver todos os dados do titular.

**Observação:** Os dados completos são necessários apenas quando houver geração de fatura (cobrança mensal).

---

## 15. O que este backend faz (resumo)

- **Autenticação:** Login duplo (lojista vs cliente) via Laravel Sanctum (tokens).
- **Multiempresa:** Isolamento total por empresa; lojistas só veem dados das suas empresas.
- **Gestão de empresas:** CRUD, configurações, horários, bairros de entrega, formas de pagamento, logo/banner, verificação de cadastro completo com navegação guiada (itens pendentes incluem caminho no menu para o usuário saber onde preencher).
- **Gestão de produtos:** CRUD, categorias, unidades de medida, upload de imagem, importação por planilha, duplicar, toggle destaque/ativo.
- **Pedidos:** Criação pelo cliente, atualização de status pelo lojista, histórico, cupons (empresa e sistema), validação de cupom, push para lojista em novo pedido.
- **Cupons:** Cupons da empresa (criados pelo lojista) e cupons do sistema (atribuídos a clientes); validação e rastreamento de uso.
- **Avaliações:** Clientes avaliam pedidos entregues; lojista vê avaliações sem dados do cliente; solicitação de moderação para comentários ofensivos.
- **Site cliente:** Listagem e detalhes de empresas (público), perfil, endereços, favoritos, meus pedidos, meus cupons (área autenticada).
- **Dashboard lojista:** KPIs, vendas 7 dias, últimos pedidos, avaliações recentes, produtos populares, horários de pico.
- **Pausas agendadas:** Períodos em que a loja fica fechada (considerados em "loja aberta/fechada").
- **Fechar/abrir loja manual:** Override rápido via `fechada_manual` (sem usar pausas).
- **Logs de comportamento:** Adicionar/remover do carrinho, trocar de loja, acesso à loja (aberta/fechada) para analytics.
- **Push (Web Push):** Notificação de novo pedido para o lojista no navegador (VAPID).
- **Menu reativo no painel:** O sidebar do lojista verifica periodicamente o status do cadastro; se incompleto, oculta menus secundários (produtos, pedidos, etc.) até que o cadastro esteja 100% completo.
- **Máscaras dinâmicas de documento:** No faturamento, o usuário seleciona CPF ou CNPJ e o campo de input se adapta automaticamente com a máscara correta.
- **Recuperação de senha:** Envio de código por e-mail, verificação e alteração de senha.
- **Primeiro login (funcionário):** Troca obrigatória de senha no primeiro acesso ao painel lojista.
- **Faturamento Condicional Mensal (MVP):** Sistema de cobrança baseado em volume (pay-as-you-go) integrado com Asaas:
  - Dia 01 às 08:00: gera cobranças automaticamente para matrizes com 16+ pedidos no mês anterior
  - 15 ou menos pedidos = mês gratuito
  - Valor: plano base + 50% por filial ativa
  - Cobrança única (não assinatura) via PIX, vencimento em 5 dias
  - Dia 06 às 09:00: desativa matriz + filiais automaticamente se não pagou
  - Webhook Asaas reativa empresas automaticamente após pagamento

---

## 16. Quem consome esta API

| Consumidor            | Uso principal                                                |
|----------------------|-------------------------------------------------------------|
| petgre-lojista       | Painel administrativo (dashboard, pedidos, produtos, cupons, configurações, usuários, avaliações). |
| petgre-cliente       | Site/app do cliente (listar empresas, fazer pedidos, perfil, endereços, favoritos, histórico).   |

A estrutura do banco de dados está em **BANCO_DE_DADOS.md**.

---

## 17. Notas sobre integração com Frontend

### 17.1. Verificação de cadastro e menu reativo

O painel lojista (`petgre-lojista`) implementa verificação reativa do status do cadastro:
- O componente `AppSidebar.vue` consome `GET /api/empresa/{id}/verificar-cadastro` a cada 30 segundos (polling)
- Se `cadastro_completo = false`, o menu é filtrado automaticamente para mostrar apenas:
  - Dashboard
  - Configurações da Empresa (Informações Gerais, Endereço, Configurações, Horários & Pagamento, Entregas, Dados de Faturamento, WhatsApp)
- Menus ocultados até cadastro completo: Usuários, Pausas Agendadas, Dados da Conta, Faturamento (menu), Cadastros (Produtos, Categorias, Kits, Cupons), Pedidos, Avaliações, Chamados
- O frontend exibe um alerta visual destacado quando o cadastro está incompleto, com link direto para completar

### 17.2. Máscaras dinâmicas de documento (CPF/CNPJ)

O formulário de Dados de Faturamento (`DadosFaturamento.vue`) implementa máscaras dinâmicas baseadas no `tipo_documento_titular`:
- Select "Tipo de Documento": CPF (padrão) ou CNPJ
- CPF: máscara `000.000.000-00`, placeholder `000.000.000-00`
- CNPJ: máscara `00.000.000/0000-00`, placeholder `00.000.000/0000-00`
- O valor é salvo no backend no campo `tipo_documento_titular` (enum: 'cpf', 'cnpj')
