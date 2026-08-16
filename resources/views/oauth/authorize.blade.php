@extends('oauth.layout')

@section('title', 'Доступ к каталогу — Кинокот')

@section('content')
    <h1>{{ $client->name }}</h1>
    <p class="lead">
        Приложение просит доступ к каталогу Кинокот от имени
        <strong>{{ $user->name }}</strong>{{ $user->email ? ' ('.$user->email.')' : '' }}.
    </p>

    <ul class="scopes">
        @forelse ($scopes as $scope)
            <li>{{ $scope->description }}</li>
        @empty
            <li>Доступ к вашему каталогу и списку просмотра.</li>
        @endforelse
    </ul>

    <p class="muted" style="margin-bottom: 18px;">
        Приложение сможет читать каталог, ваш список просмотра, оценки и подборки,
        а также менять их — включая публичные отзывы. Если вы этого не начинали,
        нажмите «Отклонить».
    </p>

    <div class="row">
        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="primary">Разрешить</button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="ghost">Отклонить</button>
        </form>
    </div>

    <div class="divider">не тот аккаунт?</div>

    <form method="POST" action="{{ route('oauth.logout') }}">
        @csrf
        <input type="hidden" name="redirect_to" value="{{ $request->fullUrl() }}">
        <button type="submit" class="ghost">Войти под другим</button>
    </form>
@endsection
