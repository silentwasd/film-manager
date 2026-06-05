<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class KotonetAuthController extends Controller
{
    public function redirect(): \Illuminate\Http\RedirectResponse
    {
        $state = Str::random(40);
        Cache::put("kotonet_state_{$state}", true, now()->addMinutes(5));

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

        abort_unless(
            Cache::pull("kotonet_state_{$state}") === true,
            422,
            'Invalid state'
        );

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

        $token = $user->createToken('kotonet')->plainTextToken;

        return redirect(config('app.frontend_url').'/auth/callback?token='.urlencode($token));
    }
}
