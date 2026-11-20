<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use UnexpectedValueException;

/**
 * Тело обработчика Consumer из методички (callback внутри RabbitMQConsumer),
 * вынесенное в отдельный класс, чтобы его можно было проверить тестом без брокера.
 *
 * Возвращает строки для вывода в терминал Consumer.
 * Бросает исключение, если сообщение некорректное или заказ не найден:
 * тогда Consumer делает nack.
 */
class OrderTicketProcessor
{
    public function __construct(private TicketGenerator $generator) {}

    /** @return list<string> */
    public function process(string $body): array
    {
        $out = [];

        // Декодируем JSON
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (($data['event'] ?? null) !== 'order.created' || ! isset($data['order_id'])) {
            throw new UnexpectedValueException('Некорректное сообщение RabbitMQ');
        }

        $orderId = (int) $data['order_id'];
        $out[] = "Получен заказ №{$orderId}";

        // Находим заказ в базе
        $order = Order::with('event')->findOrFail($orderId);

        // Создаём билет, если его ещё нет. order_id уникален, поэтому
        // повторное сообщение о том же заказе не создаст второй билет.
        $ticket = DB::transaction(fn () => Ticket::firstOrCreate(
            ['order_id' => $order->id],
            [
                'ticket_number' => 'TKT-'.Str::upper(Str::random(12)),
                'status' => 'pending',
            ]
        ));

        // Проверяем, не сформирован ли билет ранее
        if ($ticket->status === 'generated'
            && $ticket->file_path
            && Storage::disk('public')->exists($ticket->file_path)) {
            $out[] = "Билет {$ticket->ticket_number} уже сформирован.";

            return $out;
        }

        $out[] = 'Формирование PDF...';
        $path = $this->generator->generate($ticket);

        $ticket->update([
            'file_path' => $path,
            'status' => 'generated',
        ]);

        $order->update([
            'status' => 'completed',
        ]);

        Log::info('Электронный билет сформирован', [
            'order_id' => $order->id,
            'ticket_number' => $ticket->ticket_number,
            'file_path' => $path,
        ]);

        $out[] = 'Билет успешно сформирован!';
        $out[] = "Файл: {$path}";

        return $out;
    }
}
