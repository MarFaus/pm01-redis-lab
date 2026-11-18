<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\LabController;
use App\Services\EventCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

/*
| Сайт: афиша и карточки мероприятий.
*/
Route::get('/', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{id}', [EventController::class, 'show'])->whereNumber('id')->name('events.show');
Route::patch('/events/{event}/price', [EventController::class, 'updatePrice'])->whereNumber('event')->name('events.price');
Route::post('/cache/forget', [EventController::class, 'forgetCache'])->name('cache.forget');

/*
| Лаборатория: консоль Redis и задания 2–6.
*/
Route::get('/lab', [LabController::class, 'index'])->name('lab.index');
Route::post('/lab/run', [LabController::class, 'run'])->name('lab.run');
Route::get('/lab/keys', [LabController::class, 'keys'])->name('lab.keys');
Route::get('/lab/check', [LabController::class, 'check'])->name('lab.check');

/*
| Маршруты ровно из методички: задание 7 и дополнительное задание.
*/
Route::get('/redis-test', function () {

    Redis::set('student:name', 'Александр');

    $name = Redis::get('student:name');

    return 'Данные из Redis: '.$name;
})->name('task.redis-test');

Route::get('/events/{id}/views', function ($id) {

    $key = "event:{$id}:views";

    $views = Redis::incr($key);

    // Дополнительно к методичке: держим рейтинг (Sorted Set) в согласии со счётчиком.
    Redis::zincrby('events:rating', 1, "event:{$id}");

    return response()->json([
        'event_id' => $id,
        'views' => $views,
    ]);
})->whereNumber('id')->name('task.views');

Route::get('/events-cache', function () {

    // Тот же ключ events:list, что и у афиши: один кеш на весь сайт,
    // и после изменения цены он сбрасывается в одном месте (модель Event).
    $events = Cache::remember(
        EventCatalog::LIST_KEY,
        EventCatalog::LIST_TTL,
        fn () => app(EventCatalog::class)->fresh()
    );

    return response()->json(
        collect($events)
            ->map(fn ($event) => Arr::only($event, ['id', 'name', 'price']))
            ->sortBy('name')
            ->values()
    );
})->name('task.events-cache');
