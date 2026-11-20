<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventCatalog;
use App\Services\EventStats;
use App\Services\RedisHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function __construct(
        private EventCatalog $catalog,
        private EventStats $stats,
    ) {}

    public function index(Request $request, RedisHealth $health): View
    {
        // Сначала открываем соединение с Redis (PING/INFO), чтобы в замер
        // «Чека запроса» попало только чтение кеша, а не TCP-подключение.
        $health->online();

        $list = $this->catalog->list();
        $events = collect($list['events']);

        $cities = $events->pluck('city')->unique()->sort()->values();
        $city = $request->string('city')->toString();
        if ($city !== '' && $cities->contains($city)) {
            $events = $events->where('city', $city)->values();
        } else {
            $city = '';
        }

        $top = $this->stats->top(5);
        $names = collect($list['events'])->pluck('name', 'id');

        return view('events.index', [
            'events' => $events,
            'cities' => $cities,
            'city' => $city,
            'cache' => $list,
            'stats' => $this->stats->forMany($events->pluck('id')->all()),
            'top' => collect($top)->map(fn ($views, $id) => [
                'id' => $id,
                'name' => $names[$id] ?? "Мероприятие #{$id}",
                'views' => $views,
            ])->values(),
        ]);
    }

    public function show(Request $request, int $id): View
    {
        $card = $this->catalog->card($id);
        abort_if($card['card'] === null, 404);

        $view = $this->stats->recordView($id, $request->attributes->get('visitor', 'v-anon'));

        return view('events.show', [
            'event' => $card['card'],
            // Остаток билетов меняется с каждым заказом, поэтому берём его из базы, а не из кеша карточки.
            'available' => (int) Event::whereKey($id)->value('available_tickets'),
            'cardHit' => $card['hit'],
            'view' => $view,
            'log' => array_merge($card['log'], $view['log'] ?? []),
        ]);
    }

    public function updatePrice(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'price' => ['required', 'integer', 'min:0', 'max:1000000'],
        ], [
            'price.required' => 'Укажите цену.',
            'price.integer' => 'Цена — целое число тенге, без пробелов и копеек.',
            'price.min' => 'Цена не может быть отрицательной.',
            'price.max' => 'Цена не больше 1 000 000 ₸.',
        ]);

        $old = $event->price;
        // Событие saved у модели само удалит events:list и event:{id} из Redis.
        $event->update(['price' => $data['price']]);

        return redirect()
            ->route('events.show', $event->id)
            ->with('status', [
                'title' => 'Цена изменена: '.number_format($old, 0, '', ' ').' → '.number_format($event->price, 0, '', ' ').' ₸',
                'text' => "Ключи events:list и event:{$event->id} удалены из Redis, поэтому афиша и эта карточка уже показывают новую цену.",
            ]);
    }

    public function forgetCache(): RedirectResponse
    {
        $this->catalog->forget();

        return redirect()->route('events.index')->with('status', [
            'title' => 'Кеш списка удалён',
            'text' => 'Ключа events:list больше нет. Этот запрос пошёл в базу данных и заново положил список в Redis. Обновите страницу ещё раз: источником станет кеш.',
        ]);
    }
}
