@extends('oauth.layout')

@section('title', 'Вход — Кинокот')

@section('content')
    <h1>Вход в Кинокот</h1>
    <p class="lead">Чтобы подключить коннектор, войдите в свой аккаунт каталога.</p>

    @if ($errors->any())
        <div class="errors">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('oauth.login.store') }}">
        @csrf

        <label>
            Почта
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </label>

        <label>
            Пароль
            <input type="password" name="password" required autocomplete="current-password">
        </label>

        <button type="submit" class="primary">Войти</button>
    </form>

    <div class="divider">или</div>

    <a class="button-link" href="{{ url('/auth/kotonet?intent=session') }}">Войти через Kotonet ID</a>
@endsection
