@extends('layouts.app')
@section('title', 'Заказы')

@section('content')
<h1 class="display">Заказы</h1>
<p class="muted" style="max-width:72ch">
    Заказ сохраняется в базе со статусом «Ждёт билет» и уходит сообщением <code>order.created</code> в очередь <code>ticket_queue</code> RabbitMQ.
    Consumer (<code>php artisan rabbitmq:consume</code>, отдельное окно) формирует PDF и меняет статус на «Билет готов».
    Страница обновляется сама каждые 3 секунды, пока есть заказы без билета.
</p>

@if (session('success'))
    <p class="flash" role="status">{{ session('success') }}</p>
@endif
@if (session('error'))
    <p class="flash flash--error" role="alert">{{ session('error') }}</p>
@endif

<div class="orders-wrap">
<table class="orders">
    <thead>
        <tr>
            <th>№</th>
            <th>Покупатель</th>
            <th>Мероприятие</th>
            <th>Билетов</th>
            <th>Сумма</th>
            <th>Статус</th>
            <th>Билет</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($orders as $order)
            <tr>
                <td class="num">{{ $order->id }}</td>
                <td>{{ $order->customer_name }}<div class="orders__sub">{{ $order->customer_email }}</div></td>
                <td>
                    <a href="{{ route('events.show', $order->event_id) }}">{{ $order->event->name }}</a>
                    <div class="orders__sub">{{ $order->created_at->format('d.m.Y H:i') }}</div>
                </td>
                <td class="num">{{ $order->quantity }}</td>
                <td class="num">{{ \App\Support\Fmt::money((int) $order->total_amount) }}</td>
                <td><span class="badge badge--{{ $order->status }}">{{ $order->statusLabel() }}</span></td>
                <td>
                    @if ($order->ticket && $order->ticket->status === 'generated')
                        <a href="{{ route('orders.ticket.download', $order) }}">Скачать билет PDF</a>
                        <div class="orders__sub">{{ $order->ticket->ticket_number }}</div>
                    @else
                        <span class="muted">Формируется...</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7">Заказов пока нет. Откройте любое мероприятие на <a href="{{ route('events.index') }}">афише</a> и купите билет.</td>
            </tr>
        @endforelse
    </tbody>
</table>
</div>

@if ($orders->contains('status', 'pending'))
    <script>setTimeout(() => location.reload(), 3000);</script>
@endif
@endsection
