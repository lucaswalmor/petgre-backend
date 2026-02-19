<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\PermissaoController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\EmpresaAvaliacaoController;
use App\Http\Controllers\EmpresaCuponsController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\SiteClienteController;
use App\Http\Controllers\UsuarioEnderecosController;
use App\Http\Controllers\EmpresaFavoritoController;
use App\Http\Controllers\UsuarioLogController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailTestController;

// Rotas de autenticação (não precisam de middleware)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

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
});

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
    
    // Dashboard lojista — retorna todos os dados em uma única requisição
    Route::get('/dashboard', [DashboardController::class, 'getDados'])->middleware('check.permission:pedidos.index');

    // Rota para listar permissões
    Route::get('/permissoes', [PermissaoController::class, 'index']);

    // Rotas de avaliações protegidas
    Route::controller(EmpresaAvaliacaoController::class)->prefix('avaliacoes')->group(function () {
        Route::get('/', 'index')->middleware('check.permission:avaliacoes.index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show')->middleware('check.permission:avaliacoes.show');
        Route::post('/{id}/solicitar-moderacao', 'solicitarModeracao')->middleware('check.permission:avaliacoes.index');
    });

    // Rotas de pedidos
    Route::controller(PedidoController::class)->prefix('pedidos')->group(function () {
        Route::get('/estatisticas', 'estatisticas')->middleware('check.permission:pedidos.index');
        Route::get('/', 'index')->middleware('check.permission:pedidos.index'); // Dashboard empresa
        Route::post('/', 'store'); // Usuários criam pedidos
        Route::get('/{id}', 'show'); // Usuários/empresas veem pedidos específicos
        Route::put('/{id}', 'update')->middleware('check.permission:pedidos.update'); // Empresa altera status
        Route::delete('/{id}', 'destroy')->middleware('check.permission:pedidos.destroy'); // Empresa exclui (apenas pendentes)
        Route::post('/validar-cupom', 'validarCupom'); // Validar cupom antes do pedido
    });

    // Rotas de usuários
    Route::controller(UsuarioController::class)->prefix('usuarios')->group(function () {
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

    // Rotas de empresas
    Route::controller(EmpresaController::class)->prefix('empresa')->group(function () {
        Route::get('/{id}/verificar-cadastro', 'verificarCadastro')->middleware('check.permission:empresas.verificar_cadastro');
        Route::get('/{empresaId}/bairros-disponiveis', 'bairrosDisponiveis')->middleware('check.permission:empresas.show');
        Route::put('/{id}', 'update')->middleware('check.permission:empresas.update');
        Route::get('/{id}', 'show')->middleware('check.permission:empresas.show');
        Route::post('/{id}/upload-image', 'uploadImage')->middleware('check.permission:empresas.upload_image');
        Route::delete('/{id}', 'destroy')->middleware('check.permission:empresas.destroy');
    });

    // Rotas de produtos
    Route::controller(ProdutoController::class)->prefix('produtos')->group(function () {
        Route::get('/', 'index')->middleware('check.permission:produtos.index');
        Route::get('/categorias', 'listarCategorias')->middleware('check.permission:produtos.index');
        Route::get('/unidades-medidas', 'listarUnidadesMedidas')->middleware('check.permission:produtos.index');
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
        Route::patch('/{id}/toggle-destaque', 'toggleDestaque')->middleware('check.permission:produtos.update');
        Route::patch('/{id}/toggle-ativo', 'toggleAtivo')->middleware('check.permission:produtos.update');
        Route::post('/{id}/upload-image', 'uploadImage')->middleware('check.permission:produtos.upload_image');
        Route::get('/search/buscar', 'search')->middleware('check.permission:produtos.index');
    });

    // Rotas de cupons da empresa
    Route::controller(EmpresaCuponsController::class)->prefix('cupons')->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');

        // Rotas especiais
        Route::patch('/{id}/toggle-ativo', 'toggleAtivo');
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

    // Teste de Email (desenvolvimento)
    Route::controller(EmailTestController::class)->prefix('email')->group(function () {
        Route::get('/test', 'test');
        Route::get('/test-bem-vindo', 'testBemVindo');
        Route::get('/status', 'status');
    });
});