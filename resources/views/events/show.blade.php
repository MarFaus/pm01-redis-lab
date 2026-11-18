@extends('layouts.app')
@section('title', $event['name'])

@php
    use App\Support\Fmt;
    $date = Fmt::date($event['starts_at']);
@endphp

@section('content')
<p class="back"><a href="{{ route('events.index') }}">Вся афиша</a></p>

<article class="bigticket ticket--{{ $event['category'] }}" aria-labelledby="event-title">
    <div class="bigticket__main">
        <p class="bigticket__cat">{{ \App\Models\Event::CATEGORIES[$event['category']] ?? '' }}, {{ $event['city'] }}</p>
        <h1 id="event-title" class="bigticket__name">{{ $event['name'] }}</h1>
        <dl class="bigticket__facts">
            <div><dt>Когда</dt><dd>{{ $date->translatedFormat('j F, l') }}, {{ $date->format('H:i') }}</dd></div>
            <div><dt>Где</dt><dd>{{ $event['venue'] }}</dd></div>
            <div><dt>Цена</dt><dd>{{ Fmt::money($event['price']) }}</dd></div>
            <div><dt>Мест</dt><dd>{{ number_format($event['tickets_total'], 0, '', ' ') }}</dd></div>
        </dl>
    </div>

    <div class="bigticket__stub">
        @if ($view)
            <p class="stamp" aria-label="Это {{ $view['views'] }}-й просмотр">
                <span class="stamp__label">просмотр</span>
                <span class="stamp__num">№ {{ $view['views'] }}</span>
            </p>
            <div class="bigticket__stats">
                <p>{{ $view['visitors'] }} {{ Fmt::plural($view['visitors'], 'уникальный зритель', 'уникальных зрителя', 'уникальных зрителей') }}</p>
                @if ($view['place'])
                    <p>{{ $view['place'] }}-е место в рейтинге</p>
                @endif
            </div>
        @else
            <p class="bigticket__stats">Просмотры не считаются: Redis недоступен.</p>
        @endif
    </div>
</article>

<div class="detail">
    <section class="detail__about">
        @if ($event['description'] !== '')
            <p class="lead">{{ $event['description'] }}</p>
        @endif

        <form class="price-form" method="post" action="{{ route('events.price', $event['id']) }}" novalidate>
            @csrf
            @method('PATCH')
            <h2 class="side-title">Изменить цену</h2>
            <p class="muted">
                Цена хранится в базе данных. После сохранения модель сама удалит из Redis
                <code>events:list</code> и <code>event:{{ $event['id'] }}</code>, иначе афиша ещё 5 минут показывала бы старую цену.
            </p>
            <div class="field">
                <label for="price">Цена билета, ₸</label>
                <div class="field__row">
                    <input id="price" name="price" type="number" inputmode="numeric" min="0" max="1000000" step="100"
                           value="{{ old('price', $event['price']) }}"
                           @error('price') aria-invalid="true" aria-describedby="price-error" @enderror>
                    <button class="btn" type="submit">Сохранить цену</button>
                </div>
                @error('price')
                    <p class="field__error" id="price-error">{{ $message }}</p>
                @enderror
            </div>
        </form>
    </section>

    <aside class="redislog" aria-labelledby="log-title">
        <h2 id="log-title" class="side-title">Что сделал Redis при открытии</h2>
        @if ($log)
            <ol class="redislog__list">
                @foreach ($log as $line)
                    <li>
                        <code class="redislog__cmd">{{ $line['cmd'] }}</code>
                        <code class="redislog__res">{{ $line['result'] }}</code>
                    </li>
                @endforeach
            </ol>
            <p class="muted small">
                @if ($cardHit)
                    Карточка взята из Hash <code>event:{{ $event['id'] }}</code>, база не трогалась.
                @else
                    Hash <code>event:{{ $event['id'] }}</code> не было: карточка прочитана из базы и сохранена в Redis на 5 минут.
                @endif
                @if ($view)
                    @if ($view['new_visitor'])
                        Вы здесь впервые: <code>SADD</code> вернул 1.
                    @else
                        Вы уже смотрели это мероприятие: <code>SADD</code> вернул 0, уникальных зрителей не прибавилось.
                    @endif
                @endif
            </p>
        @else
            <p class="muted">Redis недоступен, карточка прочитана из базы.</p>
        @endif
    </aside>
</div>
@endsection
