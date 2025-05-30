<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CadastroController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Rotas Públicas (sem autenticação)
|--------------------------------------------------------------------------
*/

// Rota principal (redireciona para login)
Route::get('/', fn() => Inertia::render('login'));

// Página de login
Route::get('/login', fn() => Inertia::render('login'))->name('login');

// Envio do formulário de login
Route::post('/login', [LoginController::class, 'login'])->name('login.post');


/*
|--------------------------------------------------------------------------
| Rotas Protegidas (autenticadas com 'auth:oracle')
|--------------------------------------------------------------------------
*/

Route::middleware('auth:oracle')->group(function () {

    // Home
    Route::get('/home', function () {
        return Inertia::render('dashboard', [
            'usuario' => Auth::guard('oracle')->user(),
        ]);
    })->name('home');

    // API interna (prefixada com /api)
    Route::prefix('api')->group(function () {
        // Buscar produto
        Route::get('/buscar', [CadastroController::class, 'buscarProduto']);

        // Atualiza o endereço (com validação de regras)
        Route::post('/endereco/validar', [CadastroController::class, 'validarEndereco']);

        // Confirma a sobrescrita e executa alteração
        Route::post('/endereco/sobrescrever', [CadastroController::class, 'sobrescreverEndereco']);

        // ---- Novas rotas para dados de preços e ofertas ----
        // Lista todas as filiais ativas para preenchimento de select
        Route::get('/filiais', [\App\Http\Controllers\PrecoController::class, 'listarFiliais']);
        // Consulta dados de preço de um produto por filial e EAN
        Route::get('/produto', [\App\Http\Controllers\PrecoController::class, 'dadosProduto']);
    });

    // Logout
    Route::get('/logout', [LoginController::class, 'logout'])->name('logout');

    /*
    |--------------------------------------------------------------------------
    | Novas rotas de interface para precificação
    |--------------------------------------------------------------------------
    |
    | A rota '/precos' renderiza uma página Inertia para consulta de preços de
    | produtos por filial e EAN. A tela permite ao usuário pesquisar e
    | visualizar valores de venda, ofertas, estoque e outras informações.
    */
    Route::get('/precos', function () {
        return Inertia::render('precos', [
            'usuario' => Auth::guard('oracle')->user(),
        ]);
    })->name('precos');
});
