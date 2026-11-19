<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Задание 3: уведомление о заказе. sleep(3) имитирует отправку письма.
 *
 * Идемпотентность (вопрос из доп. задания): перед работой задача ставит
 * отметку order:{id}:notified через атомарный Cache::add (в Redis это SET NX).
 * Если такая же задача пришла второй раз (двойной клик), отметка уже есть
 * и письмо повторно не уходит.
 */
class SendOrderNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $orderId
    ) {}

    public function handle(): void
    {
        if (! Cache::add("order:{$this->orderId}:notified", now()->toDateTimeString(), now()->addDay())) {
            Log::warning('Повтор задачи пропущен: уведомление уже отправлено', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        sleep(3);

        Log::info('Уведомление отправлено', [
            'order_id' => $this->orderId,
        ]);
    }
}
