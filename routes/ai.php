<?php

use App\Mcp\Servers\FilmManagerServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Middleware\CheckToken;

/*
|--------------------------------------------------------------------------
| MCP
|--------------------------------------------------------------------------
|
| Единственная точка входа MCP-сервера: POST /mcp. Подключается как коннектор
| в Claude, ChatGPT и Grok — они сами находят OAuth по метаданным, которые
| регистрирует Mcp::oauthRoutes() ниже.
|
| Гвард `mcp` — Passport (config/auth.php), обычное API живёт на Sanctum
| и этих маршрутов не касается. CheckToken требует scope `mcp:use`: токен
| Passport, выданный не под MCP, сюда не пройдёт.
|
*/

// Throttle стоит именно после auth: до него ключом был бы IP, а у всех
// пользователей одного коннектора он общий — лимит делили бы на всех.
Mcp::web('mcp', FilmManagerServer::class)
    ->middleware(['auth:mcp', CheckToken::using('mcp:use'), 'throttle:180,1']);

/*
| Регистрирует /.well-known/oauth-protected-resource (RFC 9728),
| /.well-known/oauth-authorization-server (RFC 8414) и POST /oauth/register —
| динамическую регистрацию клиента (RFC 7591). Без последней коннектор
| пришлось бы заводить руками для каждого сервиса.
|
| Throttle здесь не про нагрузку: /oauth/register открыт всем без токена,
| и каждый вызов заводит строку в oauth_clients.
*/

Route::middleware('throttle:30,1')->group(function () {
    Mcp::oauthRoutes();
});
