<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Дополнительная задача: формирование электронного билета.
 * sleep(5) имитирует генерацию PDF.
 */
class GenerateTicketDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $orderId
    ) {}

    public function handle(): void
    {
        sleep(5);

        Log::info('Электронный билет сформирован', [
            'order_id' => $this->orderId,
        ]);
    }
}
