@php
    use App\Support\Fmt;
    $date = Fmt::date($event['starts_at']);
    $views = $stat['views'] ?? null;
    $visitors = $stat['visitors'] ?? null;
@endphp
<a class="ticket ticket--{{ $event['category'] }}" href="{{ route('events.show', $event['id']) }}">
    <span class="ticket__main">
        <span class="ticket__date">
            <span class="ticket__day">{{ $date->format('j') }}</span>
            <span class="ticket__month">{{ \Illuminate\Support\Str::after($date->translatedFormat('j F'), ' ') }}</span>
            <span class="ticket__time">{{ $date->translatedFormat('D') }}, {{ $date->format('H:i') }}</span>
        </span>
        <span class="ticket__body">
            <span class="ticket__cat">{{ \App\Models\Event::CATEGORIES[$event['category']] ?? '' }}</span>
            <span class="ticket__name">{{ $event['name'] }}</span>
            <span class="ticket__place">{{ $event['venue'] }}, {{ $event['city'] }}</span>
        </span>
        <span class="ticket__price">{{ Fmt::money($event['price']) }}</span>
    </span>
    <span class="ticket__stub" aria-label="Статистика из Redis">
        @if ($views === null)
            <span class="stub__num stub__num--muted">—</span>
            <span class="stub__label">нет связи с Redis</span>
        @else
            <span class="stub__num">{{ $views }}</span>
            <span class="stub__label">{{ Fmt::plural($views, 'просмотр', 'просмотра', 'просмотров') }}</span>
            <span class="stub__sub">{{ $visitors }} {{ Fmt::plural($visitors, 'зритель', 'зрителя', 'зрителей') }}</span>
        @endif
    </span>
</a>
