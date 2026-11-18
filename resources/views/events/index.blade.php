@extends('layouts.app')
@section('title', 'Афиша')

@php
    use App\Support\Fmt;
    $sourceText = [
        'redis' => 'кеш Redis',
        'db' => 'база данных',
        'offline' => 'база данных',
    ][$cache['source']];
@endphp

@section('content')
<div class="afisha">
    <section class="afisha__list" aria-labelledby="afisha-title">
        <div class="afisha__head">
            <h1 id="afisha-title" class="display">Афиша</h1>
            <nav class="cities" aria-label="Город">
                <a href="{{ route('events.index') }}" @class(['cities__item', 'is-active' => $city === ''])>Все города</a>
                @foreach ($cities as $c)
                    <a href="{{ route('events.index', ['city' => $c]) }}" @class(['cities__item', 'is-active' => $city === $c])>{{ $c }}</a>
                @endforeach
            </nav>
        </div>

        @if ($events->isEmpty())
            <div class="empty">
                <p class="empty__title">Мероприятий нет.</p>
                <p>Заполните базу: <code>php artisan migrate --seed</code>, или просто перезапустите <code>start.bat</code>.</p>
            </div>
        @else
            <ol class="tickets">
                @foreach ($events as $event)
                    <li>@include('events._ticket', ['event' => $event, 'stat' => $stats[$event['id']] ?? null])</li>
                @endforeach
            </ol>
        @endif
    </section>

    <aside class="afisha__side">
        <section class="receipt" aria-labelledby="receipt-title">
            <h2 id="receipt-title" class="receipt__title">Чек запроса</h2>
            <p class="receipt__lead">
                @if ($cache['source'] === 'redis')
                    Список пришёл из кеша. База данных не понадобилась.
                @elseif ($cache['source'] === 'db')
                    Кеша не было: список взят из базы и положен в Redis на {{ \App\Services\EventCatalog::LIST_TTL / 60 }} минут. Обновите страницу.
                @else
                    Redis недоступен, список взят из базы напрямую.
                @endif
            </p>
            <dl class="receipt__rows">
                <div><dt>Источник</dt><dd @class(['is-hit' => $cache['source'] === 'redis'])>{{ $sourceText }}</dd></div>
                <div><dt>Время</dt><dd>{{ number_format($cache['ms'], 2, ',', '') }} мс</dd></div>
                <div><dt>SQL-запросов</dt><dd>{{ $cache['queries'] }}</dd></div>
                <div><dt>Ключ</dt><dd><code>events:list</code></dd></div>
                <div><dt>Живёт ещё</dt><dd data-countdown="{{ $cache['ttl'] ?? '' }}">{{ Fmt::clock($cache['ttl']) }}</dd></div>
            </dl>
            @if ($cache['source'] !== 'offline')
                <form method="post" action="{{ route('cache.forget') }}">
                    @csrf
                    <button class="btn btn--line" type="submit">Сбросить кеш</button>
                </form>
            @endif
        </section>

        <section class="top5" aria-labelledby="top-title">
            <h2 id="top-title" class="side-title">Чаще всего смотрят</h2>
            @if ($top->isEmpty())
                <p class="muted">Рейтинг пуст. Откройте любое мероприятие: Redis выполнит <code>ZINCRBY events:rating</code>, и оно появится здесь.</p>
            @else
                <ol class="top5__list">
                    @foreach ($top as $row)
                        <li>
                            <a href="{{ route('events.show', $row['id']) }}">{{ $row['name'] }}</a>
                            <span class="top5__score">{{ $row['views'] }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="muted small">Sorted Set <code>events:rating</code>, порядок по убыванию.</p>
            @endif
        </section>
    </aside>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/countdown.js') }}?v={{ filemtime(public_path('js/countdown.js')) }}" defer></script>
@endpush
