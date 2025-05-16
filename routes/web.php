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
    });

    // Logout
    Route::get('/logout', [LoginController::class, 'logout'])->name('logout');
});
