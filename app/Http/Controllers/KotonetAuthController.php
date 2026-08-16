<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class KotonetAuthController extends Controller
{
    /**
     * `intent=session` включает второй сценарий: вместо токена для фронта
     * колбэк заводит обычную сессию на самом API. Так экран согласия Passport
     * (/oauth/authorize) работает и для тех, у кого пароля нет вовсе, —
     * а таких среди пришедших из Kotonet ID большинство.
     */
    public function redirect(Request $request): \Illuminate\Http\RedirectResponse
    {
        $state = Str::random(40);

        Cache::put("kotonet_state_{$state}", [
            'intent' => $request->query('intent') === 'session' ? 'session' : 'token',
        ], now()->addMinutes(5));

        $query = http_build_query([
            'client_id' => config('services.kotonet.client_id'),
            'redirect_uri' => config('services.kotonet.redirect'),
            'response_type' => 'code',
            'state' => $state,
        ]);

        return redirect(config('services.kotonet.base_url').'/oauth/authorize?'.$query);
    }

    public function callback(Request $request): \Illuminate\Http\RedirectResponse
    {
        if ($request->query('error')) {
            return redirect(config('app.frontend_url'));
        }

        $state = $request->query('state', '');
        $stored = Cache::pull("kotonet_state_{$state}");

        abort_unless($stored !== null, 422, 'Invalid state');

        $intent = is_array($stored) ? ($stored['intent'] ?? 'token') : 'token';

        $http = Http::asForm()->withOptions(['verify' => ! app()->isLocal()]);

        $tokenResponse = $http
            ->post(config('services.kotonet.base_url').'/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => config('services.kotonet.client_id'),
                'client_secret' => config('services.kotonet.client_secret'),
                'redirect_uri' => config('services.kotonet.redirect'),
                'code' => $request->query('code'),
            ])
            ->throw()
            ->json();

        $kotonetUser = Http::withToken($tokenResponse['access_token'])
            ->withOptions(['verify' => ! app()->isLocal()])
            ->get(config('services.kotonet.base_url').'/api/user')
            ->throw()
            ->json();

        $user = User::where('kotonet_id', $kotonetUser['id'])->first()
            ?? User::where('email', $kotonetUser['email'])->first()
            ?? new User;

        $user->kotonet_id = $kotonetUser['id'];
        $user->name = $kotonetUser['name'];
        $user->email = $kotonetUser['email'];
        $user->save();

        if ($intent === 'session') {
            // Без remember: колонки remember_token в таблице `users` нет.
            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            // Адрес /oauth/authorize Passport положил в сессию, когда увёл
            // гостя на форму входа.
            return redirect()->intended(config('app.frontend_url'));
        }

        $token = $user->createToken('kotonet')->plainTextToken;

        return redirect(config('app.frontend_url').'/auth/callback?token='.urlencode($token));
    }
}
