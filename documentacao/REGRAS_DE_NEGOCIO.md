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
- **WhatsApp para pedidos:** campo crítico em empresa_configuracoes. Sem ele o cadastro não é considerado completo.
- **Status da loja (aberta/fechada):** empresa_aberta considera horários, timezone America/Sao_Paulo, pausas agendadas e fechada_manual. fechada_manual: null = usa horário e pausas; true = fechada; false = aberta (força). getFechadoAte() retorna texto para exibição (ex.: "Abre às 14:00", "quando o lojista reabrir").

---

## 4. Pedidos

- **Criação:** apenas cliente (ou lojista em nome do cliente) cria pedido. Campos obrigatórios: empresa_id, usuario_id, itens, totais, forma de pagamento. Se entrega, endereço (endereco_id do usuario). Cupom opcional (cupom_tipo, cupom_id, cupom_valor); uso registrado ao confirmar (não ao criar).
- **Status:** pendente → confirmado → em_preparacao → em_entrega → entregue; pode passar a cancelado em qualquer momento. Ao confirmar: marca cupom como usado (empresa ou sistema). Ao cancelar: devolve cupom ao cliente (sistema/empresa) e cancela resgate de cupom do sistema para a loja.
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

## 8. Produtos

- **Isolamento:** produtos pertencem a uma empresa; listagens e operações devem filtrar por empresa do usuário (lojista).
- **Exclusão:** não permitir excluir produto que possui itens em pedidos (pedido_items). Retornar 400 com mensagem adequada.
- **Nome único:** nome do produto deve ser único por empresa (validar no store/update quando aplicável).

---

## 9. Permissões e menu

- **Permissões:** middleware check.permission exige que o usuário tenha pelo menos uma das permissões informadas (ou seja master). Rotas do painel lojista devem usar requiresPermission no front e middleware no back.
- **Menu (sidebar):** itens na tabela sidebar_menu; filtrados por permissão no login (e em GET /api/user). Retornar em user_data.menu para o frontend renderizar. Novos itens via SidebarMenuSeeder (chave única para não duplicar).

---

## 10. Outros

- **Favoritos:** cliente pode favoritar/desfavoritar empresa (toggle). Listagem de favoritos só retorna empresas ativas e com cadastro completo.
- **Endereços do cliente:** CRUD apenas do próprio usuario_id; endereço padrão único por usuário; soft delete (ativo = false).
- **Logs de comportamento:** registrar ações como adicionar_carrinho, remover_carrinho, trocar_loja, acesso_loja_aberta, acesso_loja_fechada (usuario_id, empresa_id, produto_id quando aplicável, ip, user_agent) para analytics do dashboard lojista.
- **Pausas agendadas:** datas em horário local (America/Sao_Paulo); considerar em Empresa::isAberta() e getFechadoAte().
- **Imagens:** upload para storage configurado (ex.: R2); tamanho e formatos conforme EmpresaUploadImageRequest / ProdutoUploadImageRequest (ex.: até 15MB, JPEG/PNG/GIF/WebP para empresa).
- **Faturamento:** apenas usuário master (sistema.acesso_total) acessa GET/POST/PUT /api/faturamento e GET /api/faturamento/resumo. Um único registro por usuario_id em empresa_faturamento; nome_titular e cpf_cnpj definidos apenas no store e nunca alterados via API. Resumo: plano gratuito até 30 pedidos/mês (todas as empresas do master); valor_plano fixo 39,90; faturas vêm de empresa_faturas.
