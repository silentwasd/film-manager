<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

/**
 * Настройки OAuth-части MCP-сервера.
 *
 * Passport здесь работает не как «API на OAuth», а как authorization server
 * для внешних коннекторов (Claude, ChatGPT, Grok): они сами регистрируют клиента
 * по RFC 7591 и ведут пользователя на /oauth/authorize. Обычное API проекта
 * по-прежнему на Sanctum и Passport не касается.
 */
class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Device flow здесь не нужен: все три коннектора работают через
        // браузерный редирект. Выключать надо именно в register() — свои
        // маршруты Passport регистрирует в boot(), и позже флаг уже не читается.
        Passport::$deviceCodeGrantEnabled = false;
    }

    public function boot(): void
    {
        // Passport 13 своих шаблонов не везёт — экран согласия наш.
        Passport::authorizationView('oauth.authorize');

        // Токен коннектора живёт долго: пользователь подключает сервис один раз
        // и не ждёт, что через час всё отвалится. Refresh продлевает молча.
        Passport::tokensExpireIn(now()->addDays(30));
        Passport::refreshTokensExpireIn(now()->addDays(180));

        // Скоуп mcp:use объявляет сам laravel/mcp, здесь только его описание
        // для экрана согласия.
        Passport::tokensCan([
            'mcp:use' => 'Читать каталог, список просмотра, оценки и подборки — и менять их от вашего имени',
        ]);
    }
}
