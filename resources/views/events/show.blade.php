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

        <form class="order-form" method="post" action="{{ route('orders.store') }}" novalidate>
            @csrf
            <input type="hidden" name="event_id" value="{{ $event['id'] }}">
            <h2 class="side-title">Купить билет</h2>
            <p class="muted">
                Свободно {{ number_format($available, 0, '', ' ') }} из {{ number_format($event['tickets_total'], 0, '', ' ') }}.
                Заказ сохранится в базе, а сообщение <code>order.created</code> уйдёт в RabbitMQ.
                Consumer сделает PDF-билет, и он появится в разделе «Заказы».
            </p>
            <div class="order-form__grid">
                <div class="field">
                    <label for="customer_name">Имя</label>
                    <input id="customer_name" name="customer_name" type="text" autocomplete="name" maxlength="255"
                           value="{{ old('customer_name') }}" @error('customer_name') aria-invalid="true" @enderror>
                    @error('customer_name') <p class="field__error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="customer_email">Почта</label>
                    <input id="customer_email" name="customer_email" type="email" autocomplete="email" maxlength="255"
                           value="{{ old('customer_email') }}" @error('customer_email') aria-invalid="true" @enderror>
                    @error('customer_email') <p class="field__error">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="quantity">Билетов</label>
                    <input id="quantity" name="quantity" type="number" min="1" max="10" inputmode="numeric"
                           value="{{ old('quantity', 1) }}" @error('quantity') aria-invalid="true" @enderror>
                    @error('quantity') <p class="field__error">{{ $message }}</p> @enderror
                </div>
            </div>
            <button class="btn" type="submit" @disabled($available < 1)>
                {{ $available < 1 ? 'Билеты закончились' : 'Купить за '.\App\Support\Fmt::money($event['price']).' за билет' }}
            </button>
        </form>

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
