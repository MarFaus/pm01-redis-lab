@inject('health', 'App\Services\RedisHealth')
@php($redis = $health->info())
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Афиша') — Касса 6379</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cpath d='M3 7h26v6a3 3 0 0 0 0 6v6H3v-6a3 3 0 0 0 0-6z' fill='%23C8243B'/%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="@yield('body-class')">
<a class="skip" href="#main">К содержимому</a>

<header class="top">
    <div class="top__inner">
        <a class="brand" href="{{ route('events.index') }}" aria-label="Касса 6379, на главную">
            <span class="brand__word">Касса</span>
            <span class="brand__port">6379</span>
        </a>

        <nav class="nav" aria-label="Разделы">
            <a href="{{ route('events.index') }}" @class(['nav__link', 'is-active' => request()->routeIs('events.*')])>Афиша</a>
            <a href="{{ route('lab.index') }}" @class(['nav__link', 'is-active' => request()->routeIs('lab.*')])>Лаборатория</a>
        </nav>

        <div @class(['status', 'status--off' => ! $redis['online']]) role="status">
            <span class="status__dot" aria-hidden="true"></span>
            @if ($redis['online'])
                <span>Redis {{ $redis['version'] }}</span>
                <span class="status__meta">{{ $redis['mode'] }}, в памяти {{ $redis['memory'] }}</span>
            @else
                <span>Redis не отвечает</span>
            @endif
        </div>
    </div>
</header>

@unless ($redis['online'])
    <div class="offline" role="alert">
        <div class="offline__inner">
            <strong>Сайт работает без Redis.</strong>
            Афиша читается прямо из базы, но просмотры, рейтинг и консоль не работают.
            Запустите <code>start.bat</code>: он поднимет Redis в Docker или скачает переносную версию.
        </div>
    </div>
@endunless

<main id="main" class="page">
    @if (session('status'))
        <div class="notice" role="status">
            <p class="notice__title">{{ session('status')['title'] }}</p>
            <p class="notice__text">{{ session('status')['text'] }}</p>
        </div>
    @endif

    @yield('content')
</main>

<footer class="foot">
    <div class="foot__inner">
        <p>ПМ01, практическая работа «Знакомство с Redis». Laravel {{ app()->version() }}, PHP {{ PHP_VERSION }}.</p>
        <p>
            Маршруты из методички:
            <a href="{{ route('task.redis-test') }}">/redis-test</a>,
            <a href="{{ route('task.views', 1) }}">/events/1/views</a>,
            <a href="{{ route('task.events-cache') }}">/events-cache</a>
        </p>
    </div>
</footer>

@stack('scripts')
</body>
</html>
