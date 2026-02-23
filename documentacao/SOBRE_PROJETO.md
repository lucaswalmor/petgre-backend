# Sobre o PetGre Backend

## Para que serve este backend

O **petgre-backend** é a **API REST** do ecossistema PetGre. Ele é o backend único que atende:

- **Painel do Lojista** (petgre-lojista) — onde donos e funcionários de empresas pet gerenciam loja, produtos, pedidos, cupons, avaliações e configurações.
- **Site/App do Cliente** (petgre-cliente) — onde clientes finais buscam empresas, montam pedidos, acompanham entregas e avaliam.

Ou seja: um único backend serve as duas frentes (lojista e cliente), com autenticação e permissões que separam o que cada tipo de usuário pode fazer.

## Papel do PetGre (modelo de negócio)

O PetGre **não processa pagamentos** nem retém dinheiro. Ele atua como **intermediador digital**:

- Conecta clientes a empresas do nicho pet (petshops, agropecuárias, banho e tosa, veterinárias, etc.).
- Organiza catálogos, pedidos e histórico.
- Envia o pedido formatado para o WhatsApp da empresa.
- Controla status de pedidos e cadastros.

Pagamento e entrega ficam entre **cliente e empresa**; o backend só registra e estrutura as informações.

## O que este backend faz

- **Autenticação:** Login duplo (lojista vs cliente) via Laravel Sanctum (tokens).
- **Multiempresa:** Isolamento total por empresa; lojistas só veem dados das suas empresas.
- **Gestão de empresas:** CRUD, configurações, horários, bairros de entrega, formas de pagamento, logo/banner, verificação de cadastro completo.
- **Gestão de produtos:** CRUD, categorias, unidades de medida, upload de imagem, importação por planilha, duplicar, toggle destaque/ativo.
- **Pedidos:** Criação pelo cliente, atualização de status pelo lojista, histórico, cupons (empresa e sistema), validação de cupom, push para lojista em novo pedido.
- **Cupons:** Cupons da empresa (criados pelo lojista) e cupons do sistema (atribuídos a clientes); validação e rastreamento de uso.
- **Avaliações:** Clientes avaliam pedidos entregues; lojista vê avaliações sem dados do cliente; solicitação de moderação para comentários ofensivos.
- **Site cliente:** Listagem e detalhes de empresas (público), perfil, endereços, favoritos, meus pedidos, meus cupons (área autenticada).
- **Dashboard lojista:** KPIs, vendas 7 dias, últimos pedidos, avaliações recentes, produtos populares, horários de pico.
- **Pausas agendadas:** Períodos em que a loja fica fechada (considerados em “loja aberta/fechada”).
- **Fechar/abrir loja manual:** Override rápido via `fechada_manual` (sem usar pausas).
- **Logs de comportamento:** Adicionar/remover do carrinho, trocar de loja, acesso à loja (aberta/fechada) para analytics.
- **Push (Web Push):** Notificação de novo pedido para o lojista no navegador (VAPID).
- **Recuperação de senha:** Envio de código por e-mail, verificação e alteração de senha.
- **Primeiro login (funcionário):** Troca obrigatória de senha no primeiro acesso ao painel lojista.

## Tecnologias

- **Laravel 11** (PHP 8.2+)
- **MySQL**
- **Laravel Sanctum** (API tokens)
- **Form Requests** e **API Resources** para validação e formatação de respostas
- **Middleware** de permissão (`check.permission`) para rotas do lojista
- Armazenamento de imagens (ex.: Cloudflare R2) configurável

## Quem consome esta API

| Consumidor            | Uso principal                                                |
|----------------------|-------------------------------------------------------------|
| petgre-lojista       | Painel administrativo (dashboard, pedidos, produtos, cupons, configurações, usuários, avaliações). |
| petgre-cliente       | Site/app do cliente (listar empresas, fazer pedidos, perfil, endereços, favoritos, histórico).   |

A documentação detalhada das funcionalidades e rotas está em **PROJETO.md**; a estrutura do banco de dados está em **BANCO_DE_DADOS.md**; as regras de negócio do backend estão em **REGRAS_DE_NEGOCIO.md**.
