# Documentação do Projeto — PetGre Backend

## Visão geral

API REST Laravel 11 que atende o painel lojista e o site/app do cliente. Autenticação via Sanctum; autorização por permissões nas rotas do lojista.

---

## Controllers e funções

### AuthController

| Método   | Função | Descrição |
|----------|--------|-----------|
| `login`  | POST /api/login | Login com email, senha e `tipo_login` (lojista ou cliente). Retorna token e dados do usuário (com menu, permissões e empresas para lojista). |
| `logout` | POST /api/logout | Revoga o token atual (requer auth). |
| `user`   | GET /api/user | Retorna dados do usuário autenticado (menu, permissões, empresas para lojista; endereços para cliente). |

---

### UsuarioController

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

---

### EmpresaController

| Método | Função | Descrição |
|--------|--------|-----------|
| `store` | POST /api/empresa | Cria empresa + usuário admin + endereço + configurações + horário padrão. |
| `show` | GET /api/empresa/{id} | Dados completos da empresa (com relacionamentos). Query `?basic=true` retorna só dados básicos. Permissão: empresas.show. |
| `update` | PUT /api/empresa/{id} | Atualiza empresa, configurações, horários, endereço, formas de pagamento, bairros. Permissão: empresas.update. |
| `destroy` | DELETE /api/empresa/{id} | Remove empresa. Permissão: empresas.destroy. |
| `uploadImage` | POST /api/empresa/{id}/upload-image | Upload de logo e/ou banner (query `tipo`: logo ou banner). Permissão: empresas.upload_image. |
| `verificarCadastro` | GET /api/empresa/{id}/verificar-cadastro | Retorna se cadastro está completo, percentual e itens pendentes. Permissão: empresas.verificar_cadastro. |
| `status` | GET /api/empresa/{id}/status | Retorna empresa_aberta, fechado_ate e fechada_manual (indicador no painel). Permissão: empresas.show. |
| `statusManual` | PUT /api/empresa/{id}/status-manual | Fecha ou abre loja manualmente (body: fechada_manual boolean). Permissão: empresas.update. |
| `bairrosDisponiveis` | GET /api/empresa/{empresaId}/bairros-disponiveis | Bairros da cidade da empresa para entrega. Permissão: empresas.show. |

---

### ProdutoController

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
| `storeLote` | POST /api/produtos/lote | Cadastro em lote de produtos. Permissão: produtos.store. |
| `destroyLote` | DELETE /api/produtos/lote | Exclui produtos em lote (ids). Permissão: produtos.destroy. |
| `listarTerceiros` | GET /api/produtos/importar/terceiros/lista | Lista ERPs/planilhas de terceiros para importação. Permissão: produtos.store. |
| `importar` | POST /api/produtos/importar | Importa produtos via planilha (tipo petgre ou terceiros). Permissão: produtos.store. |
| `downloadModelo` | GET /api/produtos/importar/modelo | Download do modelo de planilha PetGre. Permissão: produtos.store. |
| `downloadPlanilhaErros` | GET /api/produtos/importar/erros/download | Download da planilha de erros da última importação. Permissão: produtos.store. |

---

### PedidoController

| Método | Função | Descrição |
|--------|--------|-----------|
| `estatisticas` | GET /api/pedidos/estatisticas | KPIs para cards (pedidos hoje, faturamento mês, pendentes, avaliação média). Permissão: pedidos.index. |
| `index` | GET /api/pedidos | Lista pedidos (filtros: empresa_id, status_id, usuario_id, data_inicio, data_fim). Permissão: pedidos.index. |
| `store` | POST /api/pedidos | Cliente/lojista cria pedido (itens, endereço, cupom, frete). Dispara push para empresa. |
| `show` | GET /api/pedidos/{id} | Detalhes do pedido (quem criou ou empresa do pedido). |
| `update` | PUT /api/pedidos/{id} | Atualiza status e observações; ao confirmar/entregar/cancelar trata cupons. Permissão: pedidos.update. |
| `destroy` | DELETE /api/pedidos/{id} | Exclui apenas pedidos pendentes. Permissão: pedidos.destroy. |
| `validarCupom` | POST /api/pedidos/validar-cupom | Valida cupom (código, empresa_id, valor_compra); retorna desconto e total. |

---

### SiteClienteController

| Método | Função | Descrição |
|--------|--------|-----------|
| `getEmpresas` | GET /api/site/empresas | Público. Lista empresas ativas com cadastro completo (filtros: nicho, busca, bairro, abertas, avaliação, entrega/retirada, favoritos). |
| `getEmpresa` | GET /api/site/empresa/{slug} | Público. Detalhes da empresa (produtos, horários, avaliações, etc.). Registra log de acesso se usuário logado. |
| `getPerfil` | GET /api/site/perfil | Perfil do cliente (auth). |
| `atualizarPerfil` | PUT /api/site/atualizar-perfil | Atualiza nome e telefone (auth). |
| `alterarSenha` | PUT /api/site/alterar-senha | Altera senha (senha_atual, senha_nova, confirmação) (auth). |
| `getPedidos` | GET /api/site/meus-pedidos | Histórico de pedidos do cliente (auth). |
| `getPedido` | GET /api/site/meu-pedido/{id} | Detalhes de um pedido do cliente (auth). |
| `getEnderecos` | GET /api/site/meus-enderecos | Endereços do cliente (auth). |
| `meusCupons` | GET /api/site/meus-cupons | Cupons do sistema atribuídos ao usuário não utilizados (auth). |

---

### UsuarioEnderecosController

| Método | Função | Descrição |
|--------|--------|-----------|
| `index` | GET /api/enderecos | Lista endereços ativos do usuário (auth). |
| `store` | POST /api/enderecos | Cria endereço; pode definir como padrão (auth). |
| `update` | PUT /api/enderecos/{id} | Atualiza endereço (auth). |
| `setPadrao` | PUT /api/enderecos/{id}/padrao | Define endereço como padrão (auth). |
| `destroy` | DELETE /api/enderecos/{id} | Desativa endereço (auth). |

---

### EmpresaFavoritoController

| Método | Função | Descrição |
|--------|--------|-----------|
| `toggleFavorito` | POST /api/favoritos/toggle/{empresaId} | Adiciona ou remove empresa dos favoritos (auth). |
| `listarFavoritos` | GET /api/favoritos | Lista empresas favoritas do usuário (auth). |

---

### EmpresaCuponsController

| Método | Função | Descrição |
|--------|--------|-----------|
| `index` | GET /api/cupons | Lista cupons da empresa do usuário ativo (auth). |
| `store` | POST /api/cupons | Cria cupom da empresa (auth). |
| `show` | GET /api/cupons/{id} | Detalhes do cupom (auth). |
| `update` | PUT /api/cupons/{id} | Atualiza cupom (auth). |
| `destroy` | DELETE /api/cupons/{id} | Exclui cupom (não permite se já foi usado) (auth). |
| `toggleAtivo` | PATCH /api/cupons/{id}/toggle-ativo | Ativa/desativa cupom (auth). |
| `usos` | GET /api/cupons/{id}/usos | Lista usos do cupom (auth). |
| `estatisticas` | GET /api/cupons/estatisticas/cupons | Estatísticas de cupons da empresa (auth). |

---

### EmpresaAvaliacaoController

| Método | Função | Descrição |
|--------|--------|-----------|
| `avaliacoesPorEmpresa` | GET /api/avaliacoes/empresa/{empresaId} | Público. Lista avaliações da empresa (estatísticas, distribuição, paginação). |
| `index` | GET /api/avaliacoes | Lista avaliações das empresas do lojista (sem dados do cliente). Permissão: avaliacoes.index. |
| `store` | POST /api/avaliacoes | Cliente cria avaliação para pedido entregue (auth). |
| `show` | GET /api/avaliacoes/{id} | Detalhes da avaliação (sem dados do cliente). Permissão: avaliacoes.show. |
| `solicitarModeracao` | POST /api/avaliacoes/{id}/solicitar-moderacao | Lojista solicita moderação (motivo min 20 caracteres). Permissão: avaliacoes.index. |

---

### DashboardController

| Método | Função | Descrição |
|--------|--------|-----------|
| `getDados` | GET /api/dashboard | Retorna KPIs, vendas 7 dias, últimos pedidos, avaliações recentes, produtos populares, horários de pico (auth). |

---

### PausasAgendadasController

| Método | Função | Descrição |
|--------|--------|-----------|
| `index` | GET /api/pausas-agendadas | Lista pausas da empresa (query empresa_id). Permissão: pausas_agendadas.index ou sistema.acesso_total. |
| `store` | POST /api/pausas-agendadas | Cria pausa (data_inicio, data_fim, motivo, recorrente). Permissão: pausas_agendadas.store ou sistema.acesso_total. |
| `update` | PUT /api/pausas-agendadas/{id} | Atualiza pausa. Permissão: pausas_agendadas.update ou sistema.acesso_total. |
| `destroy` | DELETE /api/pausas-agendadas/{id} | Exclui pausa. Permissão: pausas_agendadas.destroy ou sistema.acesso_total. |

---

### UsuarioLogController

| Método | Função | Descrição |
|--------|--------|-----------|
| `getEstatisticasEmpresa` | GET /api/logs/estatisticas/empresa/{empresaId} | Estatísticas de logs da empresa para dashboard (auth). |
| `salvarLogAdicionarProdutoCarrinho` | POST /api/logs/adicionar-produto-carrinho | Registra ação "adicionar ao carrinho" (auth). |
| `salvarLogRemoverProdutoCarrinho` | POST /api/logs/remover-produto-carrinho | Registra ação "remover do carrinho" (auth). |
| `salvarLogTrocarLoja` | POST /api/logs/trocar-loja | Registra troca de loja (abandono de carrinho) (auth). |

---

### PushSubscriptionController

| Método | Função | Descrição |
|--------|--------|-----------|
| `vapidPublicKey` | GET /api/push/vapid-public-key | Retorna chave pública VAPID para Web Push (auth). |
| `store` | POST /api/push/subscribe | Salva subscription do navegador para enviar notificação de novo pedido (auth). |

---

### PermissaoController

| Método | Função | Descrição |
|--------|--------|-----------|
| `index` | GET /api/permissoes | Lista todas as permissões do sistema (auth). |

---

### FaqController

| Método | Função | Descrição |
|--------|--------|-----------|
| `index` | GET /api/faqs | Lista FAQs (público). |
| `buscar` | GET /api/faqs/buscar | Busca em FAQs (público). |

---

### EmailTestController (desenvolvimento)

| Método | Função | Descrição |
|--------|--------|-----------|
| `testBemVindo` | GET /api/email/test-bem-vindo | Teste e-mail boas-vindas lojista. |
| `testBemVindoFuncionario` | GET /api/email/test-bem-vindo-funcionario | Teste e-mail boas-vindas funcionário. |
| `testBemVindoCliente` | GET /api/email/test-bem-vindo-cliente | Teste e-mail boas-vindas cliente. |

---

## Form Requests (validação)

- **Auth:** LoginRequest
- **Empresa:** EmpresaStoreRequest, EmpresaUpdateRequest, EmpresaUploadImageRequest
- **Usuários:** UsuarioStoreRequest, UsuarioUpdateRequest
- **Produto:** ProdutoStoreRequest, ProdutoUpdateRequest, ProdutoLoteRequest, ProdutoUploadImageRequest
- **Pedido:** PedidoStoreRequest, PedidoUpdateRequest
- **EmpresaCupom:** EmpresaCupomStoreRequest, EmpresaCupomUpdateRequest
- **EmpresaAvaliacao:** EmpresaAvaliacaoStoreRequest
- **PausaAgendada:** PausaAgendadaStoreRequest, PausaAgendadaUpdateRequest

---

## API Resources (formatação de resposta)

- ApiResourceCollection (paginação padrão)
- EmpresaResource, SiteEmpresaResource
- UsuarioResource, UsuarioLoginResource
- ProdutoResource
- PedidoResource
- EmpresaCupomResource, EmpresaCupomCollection
- EmpresaAvaliacaoResource
- PausaAgendadaResource

---

## Helpers

- **VerificaEmpresa:** verifica se usuário tem acesso à empresa; obter empresas do usuário; verificar se dois usuários são da mesma empresa.
- **FormatHelper:** formatSlug, formatOnlyNumbers (e similares).

---

## Comandos Artisan

| Comando | Descrição |
|--------|-----------|
| **backup:database** | Gera dump MySQL (mysqldump), envia o arquivo .sql para o disco R2 em `backups/{APP_NAME_slug}/{database}_{date}.sql` (a pasta usa o nome da aplicação do `.env` para diferenciar projetos). Remove o arquivo local após envio. Agendado diariamente às 02:00 em `routes/console.php`. Em produção é necessário configurar o cron: `* * * * * php /caminho/artisan schedule:run`. No Windows com Laragon, o comando detecta automaticamente o `mysqldump` em `C:\laragon\bin\mysql\*`. |

---

## Middleware

- **auth:sanctum** — Exige token válido.
- **check.permission:slug** — Exige que o usuário tenha a permissão (ou seja master). Usado nas rotas do painel lojista.

As rotas exatas estão em `routes/api.php`; para testes manuais use a coleção Postman do projeto. Testes automatizados de API (Feature): `AuthControllerTest`, `UsuarioControllerTest`, `EmpresaControllerTest`, `ProdutoControllerTest`, `PausasAgendadasControllerTest`, `PedidoControllerTest`, `PermissaoControllerTest`, `FaqControllerTest`, `DashboardControllerTest`, `UsuarioEnderecosControllerTest`, `EmpresaFavoritoControllerTest`, `EmpresaAvaliacaoControllerTest`, `EmpresaCuponsControllerTest`, `UsuarioLogControllerTest`, `SiteClienteControllerTest`, `PushSubscriptionControllerTest`.
