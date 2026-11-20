<?php
// Дополнительное задание: разные типы событий портала.
//
//   php producer-events.php            заказ 201, пользователь 15
//   php producer-events.php 305 22     свой номер заказа и пользователя
//
// Отправляет 5 сообщений в очередь events_queue: заказ, оплата, билет,
// письмо и статистика. У каждого есть дата и время создания.
// Отдельная очередь нужна, чтобы эти учебные события не попали к Consumer
// портала, который слушает ticket_queue и ждёт только order.created.

require_once __DIR__.'/bootstrap.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$orderId = (int) ($argv[1] ?? 201);
$userId = (int) ($argv[2] ?? 15);
$queue = 'events_queue';

$events = [
    'order.created',
    'payment.completed',
    'ticket.generate',
    'email.send',
    'analytics.update',
];

function makeMessage(int $orderId, int $userId, string $event): AMQPMessage
{
    $data = [
        'order_id' => $orderId,
        'user_id' => $userId,
        'event' => $event,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    return new AMQPMessage(
        json_encode($data, JSON_UNESCAPED_UNICODE),
        [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]
    );
}

try {
    $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
    $channel = $connection->channel();
    $channel->queue_declare($queue, false, true, false, false);

    foreach ($events as $i => $event) {
        $message = makeMessage($orderId, $userId, $event);
        $channel->basic_publish($message, '', $queue);
        echo ($i + 1).". {$event}: ".$message->getBody()."\n";
    }

    // Пассивная проверка возвращает число сообщений в очереди
    [, $count] = $channel->queue_declare($queue, true);
    echo "\nОтправлено сообщений: ".count($events).". Сейчас в {$queue}: {$count}.\n";
    echo "Посмотреть: http://localhost:15672 → Queues and Streams → {$queue} → Get messages\n";

    $channel->close();
    $connection->close();
} catch (Throwable $e) {
    echo 'Ошибка: '.$e->getMessage()."\n";
    exit(1);
}
