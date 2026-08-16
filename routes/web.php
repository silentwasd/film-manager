<?php

use App\Http\Controllers\KotonetAuthController;
use App\Http\Controllers\Oauth\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('auth/kotonet', [KotonetAuthController::class, 'redirect']);
Route::get('auth/callback', [KotonetAuthController::class, 'callback']);

/*
| Вход в сессию на самом API — только для экрана согласия Passport
| (/oauth/authorize), куда коннектор приводит браузер. Имя `login`
| обязательно: на него Laravel уводит гостя из Passport.
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [SessionController::class, 'create'])->name('login');

    Route::post('login', [SessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('oauth.login.store');
});

Route::post('logout', [SessionController::class, 'destroy'])->name('oauth.logout');
