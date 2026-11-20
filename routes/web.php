<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\QueueController;
use App\Jobs\GenerateTicketDocument;
use App\Jobs\SendOrderNotification;
use App\Services\EventCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

/*
| ЛР 50: заказы, RabbitMQ и PDF-билеты.
*/
Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/{order}/ticket', [OrderController::class, 'downloadTicket'])->name('orders.ticket.download');

/*
| ПМ01, работа 49: синхронная и асинхронная обработка, очереди Laravel.
| Маршруты /sync-order и /async-order взяты из методички без изменений.
*/
Route::get('/queue', [QueueController::class, 'index'])->name('queue.index');
Route::get('/queue/status', [QueueController::class, 'status'])->name('queue.status');

Route::get('/async-order', function () {
    $start = microtime(true);
    // Имитация создания заказа
    $orderId = 100;
    // Передача задачи в очередь
    SendOrderNotification::dispatch($orderId);
    $time = microtime(true) - $start;

    return response()->json([
        'message' => 'Заказ создан',
        'order_id' => $orderId,
        'response_time' => round($time, 4),
    ]);
})->name('task.async-order');

Route::get('/sync-order', function () {
    $start = microtime(true);
    $orderId = 101;
    // Имитация длительной операции
    sleep(3);
    Log::info('Уведомление обработано синхронно', [
        'order_id' => $orderId,
    ]);

    return response()->json([
        'message' => 'Заказ обработан',
        'order_id' => $orderId,
        'response_time' => round(
            microtime(true) - $start,
            2
        ),
    ]);
})->name('task.sync-order');

// Дополнительная задача: формирование электронного билета в фоне.
Route::get('/ticket-document', function () {
    $start = microtime(true);
    GenerateTicketDocument::dispatch(100);

    return response()->json([
        'message' => 'Формирование билета поставлено в очередь',
        'order_id' => 100,
        'response_time' => round(microtime(true) - $start, 4),
    ]);
})->name('task.ticket-document');

// Вопрос из доп. задания: двойной клик отправляет одну и ту же задачу дважды.
// Новый номер заказа на каждый вызов, чтобы демонстрацию можно было повторять.
Route::get('/double-order', function () {
    $orderId = random_int(1000, 9999);
    SendOrderNotification::dispatch($orderId);
    SendOrderNotification::dispatch($orderId);

    return response()->json([
        'message' => 'Одна и та же задача отправлена в очередь два раза',
        'order_id' => $orderId,
        'expected' => 'В журнале одно «Уведомление отправлено» и одно «Повтор задачи пропущен»',
    ]);
})->name('task.double-order');
