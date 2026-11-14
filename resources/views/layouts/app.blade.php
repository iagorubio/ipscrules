<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Reglamento IPSC') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body>
<header class="top">
    <div class="in">
        <strong>{{ config('app.name') }}</strong>
        @auth
            <nav>
                <a href="{{ route('ask') }}">Consultas</a>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.users') }}">Usuarios</a>
                    <a href="{{ route('admin.rulebook') }}">Reglamento</a>
                @endif
            </nav>
            <form method="post" action="{{ route('logout') }}">@csrf
                <button class="link" type="submit">Salir ({{ auth()->user()->name }})</button>
            </form>
        @endauth
    </div>
</header>
<main>
    @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
    @yield('content')
</main>
<footer>
    El texto oficial del reglamento es el inglés (IPSC Combined Competition Rules, January 2026). Esta herramienta es una ayuda: ante la duda, consulta el reglamento.
</footer>
</body>
</html>
