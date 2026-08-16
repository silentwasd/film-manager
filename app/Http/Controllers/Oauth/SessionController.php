<?php

namespace App\Http\Controllers\Oauth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Вход в сессию на самом API. Нужен ровно одному сценарию — экрану согласия
 * Passport на /oauth/authorize: коннектор приводит туда браузер пользователя,
 * а фронт живёт на другом домене и работает на токенах, а не на сессии.
 *
 * Остальному API эти маршруты не нужны: там Sanctum и Bearer.
 */
class SessionController extends Controller
{
    public function create(): View
    {
        return view('oauth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Без remember: в таблице `users` этого проекта нет колонки
        // remember_token, и «запомнить меня» уронил бы вход в 500.
        if (! Auth::guard('web')->attempt($data)) {
            throw ValidationException::withMessages([
                'email' => 'Неверная почта или пароль.',
            ]);
        }

        $request->session()->regenerate();

        // Passport сам положил сюда адрес /oauth/authorize перед тем,
        // как отправить гостя на форму входа.
        return redirect()->intended(config('app.frontend_url'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $redirect = $request->string('redirect_to')->toString();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Возвращаемся на тот же /oauth/authorize, чтобы пользователь
        // продолжил подключение уже под другим аккаунтом.
        return $this->isOwnUrl($redirect)
            ? redirect($redirect)
            : redirect()->route('login');
    }

    /**
     * Открытый редирект здесь был бы подарком фишингу: адрес приезжает
     * из формы, поэтому пускаем только на собственный домен.
     */
    private function isOwnUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host !== null && $host === parse_url((string) config('app.url'), PHP_URL_HOST);
    }
}
