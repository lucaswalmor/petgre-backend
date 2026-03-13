<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\PermissaoController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\EmpresaAvaliacaoController;
use App\Http\Controllers\BillingTestController;
use App\Http\Controllers\EmpresaCuponsController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\SiteClienteController;
use App\Http\Controllers\UsuarioEnderecosController;
use App\Http\Controllers\EmpresaFavoritoController;
use App\Http\Controllers\UsuarioLogController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailTestController;
use App\Http\Controllers\PausasAgendadasController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\ChamadosController;
use App\Http\Controllers\EmpresaFaturamentoController;
use App\Http\Controllers\EmpresaFaturasController;
use App\Http\Controllers\AsaasWebhookController;
use App\Http\Controllers\KitController;
use App\Http\Controllers\EmpresaEvolutionWhatsappController;
use App\Http\Controllers\LeadController;

Route::get('/', function () {
    return response()->json(['message' => 'Hello World']);
});

// Webhook Asaas (público, validado por token no header)
Route::post('/webhooks/asaas', [AsaasWebhookController::class, 'handle']);

// Rotas de autenticação (não precisam de middleware)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/reativar-conta', [AuthController::class, 'reativarConta']);

// Rota para obter informações do usuário autenticado
Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');

// Rota para cadastro de usuários (não precisa de autenticação)
Route::controller(UsuarioController::class)->prefix('usuarios')->group(function () {
    Route::post('/', 'store');
});

Route::controller(EmpresaController::class)->prefix('empresa')->group(function () {
    Route::post('/', 'store');
});

Route::controller(SiteClienteController::class)->prefix('site')->group(function () {
    Route::get('/empresas', 'getEmpresas');
    Route::get('/empresa/{slug}', 'getEmpresa');
    Route::get('/produtos', 'getProdutos');
});

// Rota pública para captura de leads (landing page)
Route::post('/leads', [LeadController::class, 'store']);
Route::post('/contato', [LeadController::class, 'contato']);

// Rotas públicas de FAQ
Route::controller(FaqController::class)->prefix('faqs')->group(function () {
    Route::get('/', 'index');
    Route::get('/buscar', 'buscar');
});

// Rotas públicas de avaliações
Route::controller(EmpresaAvaliacaoController::class)->prefix('avaliacoes')->group(function () {
    Route::get('/empresa/{empresaId}', 'avaliacoesPorEmpresa'); // Público - ver avaliações da empresa
});


// Rotas protegidas (precisam de autenticação)
Route::middleware('auth:sanctum')->group(function () {

    // Dashboard lojista — exige x-empresa-id
    Route::get('/dashboard', [DashboardController::class, 'getDados'])->middleware('empresa.context');

    // Rota para listar permissões (não exige empresa)
    Route::get('/permissoes', [PermissaoController::class, 'index']);

    // Rota para cliente criar avaliação (autenticado, sem x-empresa-id)
    Route::post('/avaliacoes', [EmpresaAvaliacaoController::class, 'store']);

    // Rotas de avaliações protegidas — exige x-empresa-id (painel do lojista)
    Route::controller(EmpresaAvaliacaoController::class)->prefix('avaliacoes')->middleware('empresa.context')->group(function () {
        Route::get('/', 'index')->middleware('check.permission:avaliacoes.index');
        Route::get('/{id}', 'show')->middleware('check.permission:avaliacoes.show');
        Route::post('/{id}/solicitar-moderacao', 'solicitarModeracao')->middleware('check.permission:avaliacoes.index');
    });

    // Rotas de pedidos — exige x-empresa-id (painel do lojista)
    Route::controller(PedidoController::class)->prefix('pedidos')->middleware('empresa.context')->group(function () {
        Route::get('/estatisticas', 'estatisticas')->middleware('check.permission:pedidos.index');
        Route::get('/', 'index')->middleware('check.permission:pedidos.index'); // Dashboard empresa
        Route::get('/{id}', 'show'); // Usuários/empresas veem pedidos específicos
        Route::put('/{id}', 'update')->middleware('check.permission:pedidos.update'); // Empresa altera status
        Route::delete('/{id}', 'destroy')->middleware('check.permission:pedidos.destroy'); // Empresa exclui (apenas pendentes)
    });

    // Validação de cupom para checkout do cliente (não exige x-empresa-id, apenas auth)
    Route::post('/pedidos/validar-cupom', [PedidoController::class, 'validarCupom']);

    // Rota de criação de pedidos (clientes autenticados)
    Route::post('/pedidos', [PedidoController::class, 'store'])->middleware('auth:sanctum');

    // Rotas de teste de faturamento (apenas para desenvolvimento/teste)
    Route::prefix('test')->middleware('auth:sanctum')->group(function () {
        Route::get('/masters', [BillingTestController::class, 'listMasters']);
        Route::get('/billing-status', [BillingTestController::class, 'checkBillingStatus']);
        Route::post('/simulate-billing', [BillingTestController::class, 'simulateBilling']);
        Route::post('/reset-billing', [BillingTestController::class, 'resetBillingCounters']);
        Route::get('/asaas-config', [BillingTestController::class, 'testAsaasConfig']);
    });

    // Rotas de usuários — exige x-empresa-id
    Route::controller(UsuarioController::class)->prefix('usuarios')->middleware('empresa.context')->group(function () {
        Route::put('/alterar-senha-primeiro-login', 'alterarSenhaPrimeiroLogin');
        Route::get('/', 'index')->middleware('check.permission:usuarios.index');
        Route::post('/criar-funcionario', 'store')->middleware('check.permission:usuarios.store');
        Route::get('/{id}', 'show')->middleware('check.permission:usuarios.show');
        Route::put('/{id}', 'update')->middleware('check.permission:usuarios.update');
        Route::delete('/{id}', 'destroy')->middleware('check.permission:usuarios.destroy');
    });

    // Rotas do Site Cliente (Privadas)
    Route::controller(SiteClienteController::class)->prefix('site')->group(function () {
        Route::get('/perfil', 'getPerfil');
        Route::put('/atualizar-perfil', 'atualizarPerfil');
        Route::put('/alterar-senha', 'alterarSenha');
        Route::delete('/excluir-conta', 'excluirConta');
        Route::get('/meus-pedidos', 'getPedidos');
        Route::get('/meu-pedido/{id}', 'getPedido');
        Route::get('/meus-enderecos', 'getEnderecos');
        Route::get('/meus-cupons', 'meusCupons');
    });


    // Gestão de Endereços do Cliente
    Route::controller(UsuarioEnderecosController::class)->prefix('enderecos')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('/{id}', 'update');
        Route::put('/{id}/padrao', 'setPadrao');
        Route::delete('/{id}', 'destroy');
    });

    // Gestão de Favoritos
    Route::controller(EmpresaFavoritoController::class)->prefix('favoritos')->group(function () {
        Route::post('/toggle/{empresaId}', 'toggleFavorito');
        Route::get('/', 'listarFavoritos');
    });

    // Estatísticas de Logs (Protegidas - apenas lojistas)
    Route::controller(UsuarioLogController::class)->prefix('logs')->group(function () {
        Route::get('/estatisticas/empresa/{empresaId}', 'getEstatisticasEmpresa');
    });

    // Listar empresas do usuário (não exige x-empresa-id)
    Route::get('/empresa', [EmpresaController::class, 'index']);

    // Rotas de empresas (com id) — exige x-empresa-id
    Route::controller(EmpresaController::class)->prefix('empresa')->middleware('empresa.context')->group(function () {
        Route::get('/{id}/verificar-cadastro', 'verificarCadastro')->middleware('check.permission:empresas.verificar_cadastro');
        Route::get('/{id}/status', 'status')->middleware('check.permission:empresas.show');
        Route::put('/{id}/status-manual', 'statusManual')->middleware('check.permission:empresas.update');
        Route::get('/{empresaId}/bairros-disponiveis', 'bairrosDisponiveis')->middleware('check.permission:empresas.show');
        Route::put('/{id}', 'update')->middleware('check.permission:empresas.update');
        Route::get('/{id}', 'show')->middleware('check.permission:empresas.show');
        Route::post('/{id}/upload-image', 'uploadImage')->middleware('check.permission:empresas.upload_image');
        Route::delete('/{id}', 'destroy')->middleware('check.permission:empresas.destroy');
    });

    // Push notifications (lojista — novo pedido)
    Route::controller(PushSubscriptionController::class)->prefix('push')->group(function () {
        Route::get('/vapid-public-key', 'vapidPublicKey');
        Route::post('/subscribe', 'store');
    });

    // Pausas agendadas (Configurações) — exige x-empresa-id
    Route::controller(PausasAgendadasController::class)->prefix('pausas-agendadas')->middleware('empresa.context')->group(function () {
        Route::get('/', 'index')->middleware('check.permission:pausas_agendadas.index,sistema.acesso_total');
        Route::post('/', 'store')->middleware('check.permission:pausas_agendadas.store,sistema.acesso_total');
        Route::put('/{id}', 'update')->middleware('check.permission:pausas_agendadas.update,sistema.acesso_total');
        Route::delete('/{id}', 'destroy')->middleware('check.permission:pausas_agendadas.destroy,sistema.acesso_total');
    });

    // Faturamento (apenas master) — exige x-empresa-id
    Route::controller(EmpresaFaturamentoController::class)->prefix('faturamento')->middleware('empresa.context')->middleware('check.permission:sistema.acesso_total')->group(function () {
        Route::get('/', 'show');
        Route::post('/', 'store');
        Route::put('/', 'update');
        Route::get('/resumo', 'resumo');
    });

    // Faturas (lista e detalhe com PIX) — apenas master
    Route::controller(EmpresaFaturasController::class)->prefix('faturas')->middleware('empresa.context')->middleware('check.permission:sistema.acesso_total')->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
    });

    // Tickets (lojista - qualquer usuário autenticado acessa por empresa)
    Route::controller(TicketController::class)->prefix('tickets')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::post('/{id}/messages', 'storeMessage');
    });

    // Chamados (admin/desenvolvedor - validação desenvolvedor no controller)
    Route::controller(ChamadosController::class)->prefix('chamados')->group(function () {
        Route::get('/', 'index');
        Route::put('/concluir-lote', 'concluirLote');
        Route::delete('/excluir-lote', 'excluirLote');
        Route::get('/{id}', 'show');
        Route::post('/{id}/responder', 'responder');
        Route::put('/{id}/concluir', 'concluir');
        Route::delete('/{id}', 'destroy');
    });

    // Rotas de produtos — exige x-empresa-id
    Route::controller(ProdutoController::class)->prefix('produtos')->middleware('empresa.context')->group(function () {
        Route::get('/', 'index')->middleware('check.permission:produtos.index');
        Route::get('/categorias', 'listarCategorias')->middleware('check.permission:produtos.index');
        Route::get('/unidades-medidas', 'listarUnidadesMedidas')->middleware('check.permission:produtos.index');
        Route::post('/calcular-promocao', 'calcularPromocao')->middleware('check.permission:produtos.index');
        Route::post('/', 'store')->middleware('check.permission:produtos.store');
        Route::post('/lote', 'storeLote')->middleware('check.permission:produtos.store');
        Route::delete('/lote', 'destroyLote')->middleware('check.permission:produtos.destroy');

        // Rotas especiais
        Route::get('/importar/terceiros/lista', 'listarTerceiros')->middleware('check.permission:produtos.store');
        Route::post('/importar', 'importar')->middleware('check.permission:produtos.store');
        Route::get('/importar/modelo', 'downloadModelo')->middleware('check.permission:produtos.store');
        Route::get('/importar/erros/download', 'downloadPlanilhaErros')->middleware('check.permission:produtos.store');

        Route::get('/{id}', 'show')->middleware('check.permission:produtos.show');
        Route::post('/{id}/duplicar', 'duplicar')->middleware('check.permission:produtos.store');
        Route::put('/{id}', 'update')->middleware('check.permission:produtos.update');
        Route::delete('/{id}', 'destroy')->middleware('check.permission:produtos.destroy');

        // Rotas especiais
        Route::put('/{id}/toggle-destaque', 'toggleDestaque')->middleware('check.permission:produtos.update');
        Route::put('/{id}/toggle-ativo', 'toggleAtivo')->middleware('check.permission:produtos.update');
        Route::post('/{id}/upload-image', 'uploadImage')->middleware('check.permission:produtos.upload_image');
        Route::get('/search/buscar', 'search')->middleware('check.permission:produtos.index');
    });

    // Rotas de kits — exige x-empresa-id
    Route::controller(KitController::class)->prefix('kits')->middleware('empresa.context')->group(function () {
        Route::get('/estatisticas', 'estatisticas')->middleware('check.permission:kits.index');
        Route::get('/', 'index')->middleware('check.permission:kits.index');
        Route::post('/', 'store')->middleware('check.permission:kits.store');
        Route::get('/{id}', 'show')->middleware('check.permission:kits.show');
        Route::put('/{id}', 'update')->middleware('check.permission:kits.update');
        Route::delete('/{id}', 'destroy')->middleware('check.permission:kits.destroy');
        Route::post('/{id}/imagem', 'uploadImagem')->middleware('check.permission:kits.upload_image');
        Route::put('/{id}/toggle-ativo', 'toggleAtivo')->middleware('check.permission:kits.update');
    });

    // Evolution WhatsApp — exige x-empresa-id
    Route::prefix('evolution')->middleware(['auth:sanctum', 'empresa.context'])->group(function () {
        Route::get('whatsapp', [EmpresaEvolutionWhatsappController::class, 'index']);
        Route::post('whatsapp', [EmpresaEvolutionWhatsappController::class, 'criar']);
        Route::get('whatsapp/qrcode', [EmpresaEvolutionWhatsappController::class, 'buscarQrCode']);
        Route::get('whatsapp/status', [EmpresaEvolutionWhatsappController::class, 'atualizarStatus']);
        Route::post('whatsapp/desconectar', [EmpresaEvolutionWhatsappController::class, 'desconectar']);
        Route::delete('whatsapp', [EmpresaEvolutionWhatsappController::class, 'deletar']);
    });

    // Rotas de cupons da empresa — exige x-empresa-id
    Route::controller(EmpresaCuponsController::class)->prefix('cupons')->middleware('empresa.context')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');

        // Rotas especiais
        Route::put('/{id}/toggle-ativo', 'toggleAtivo');
        Route::get('/{id}/usos', 'usos');
        Route::get('/estatisticas/cupons', 'estatisticas');
    });

    // Gestão de Logs (Autenticação automática via Sanctum)
    Route::controller(UsuarioLogController::class)->prefix('logs')->group(function () {
        Route::get('/estatisticas/empresa/{empresaId}', 'getEstatisticasEmpresa');
        Route::post('/adicionar-produto-carrinho', 'salvarLogAdicionarProdutoCarrinho');
        Route::post('/remover-produto-carrinho', 'salvarLogRemoverProdutoCarrinho');
        Route::post('/trocar-loja', 'salvarLogTrocarLoja');
    });
});

// Rotas Públicas (sem autenticação)
Route::middleware([])->group(function () {
    // Recuperação de Senha
    Route::post('/change-password', [UsuarioController::class, 'alterarSenhaPublico']);
    Route::post('/change-password/send-code', [UsuarioController::class, 'enviarCodigoRecuperacao']);
    Route::post('/change-password/verify-code', [UsuarioController::class, 'verificarCodigoRecuperacao']);

    // Teste de Email (desenvolvimento)
    Route::controller(EmailTestController::class)->prefix('email')->group(function () {
        Route::get('/test-bem-vindo', 'testBemVindo');
        Route::get('/test-bem-vindo-funcionario', 'testBemVindoFuncionario');
        Route::get('/test-bem-vindo-cliente', 'testBemVindoCliente');
    });
});
