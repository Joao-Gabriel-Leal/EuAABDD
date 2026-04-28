<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'AABB Brasília' }}</title>
    <meta name="description" content="Sistema e clube digital da AABB Brasília.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="club-body">
    <header class="club-topbar">
        <a href="{{ route('home') }}" class="club-brand" aria-label="AABB Brasília">
            <span class="club-brand__mark">AABB</span>
            <span>
                <strong>AABB Brasília</strong>
                <small>Clube, reservas e associado</small>
            </span>
        </a>

        <nav class="club-nav" aria-label="Menu principal">
            <a href="{{ route('home') }}#estrutura">Estrutura</a>
            <a href="{{ route('home') }}#planos">Planos</a>
            <a href="{{ route('home') }}#comunicados">Comunicados</a>
            @auth
                @if(auth()->user()->role === 'member')
                    <a href="{{ route('portal.dashboard') }}">Portal</a>
                @else
                    <a href="{{ route('team.dashboard') }}">Equipe</a>
                @endif
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="link-button" type="submit">Sair</button>
                </form>
            @else
                <a class="club-nav__login" href="{{ route('login') }}">Entrar</a>
            @endauth
        </nav>
    </header>

    <x-app-messages />

    <main>
        @yield('content')
    </main>
</body>
</html>
