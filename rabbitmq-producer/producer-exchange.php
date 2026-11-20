<?php
// Задание повышенной сложности: Direct Exchange portal_exchange и три очереди.
//
//   Routing Key        →  очередь
//   email.send         →  email_queue      (отправка писем)
//   ticket.generate    →  ticket_queue     (формирование билетов)
//   analytics.update   →  analytics_queue  (сбор статистики)
//
// Producer отправляет сообщения не в очередь, а в Exchange. Exchange сравнивает
// Routing Key с ключами Binding и кладёт сообщение в нужную очередь.

require_once __DIR__.'/bootstrap.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

$exchange = 'portal_exchange';

$bindings = [
    'email.send' => 'email_queue',
    'ticket.generate' => 'ticket_routed_queue',
    'analytics.update' => 'analytics_queue',
];

// ticket_queue из методички уже занят Consumer портала (он ждёт order.created).
// Чтобы демонстрация не мешала порталу, по умолчанию билеты уходят в
// ticket_routed_queue. Ключ --methodology направит их в ticket_queue, как в тексте задания.
if (in_array('--methodology', $argv, true)) {
    $bindings['ticket.generate'] = 'ticket_queue';
}

try {
    $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
    $channel = $connection->channel();

    // Подтверждения публикации: брокер сообщит, что принял каждое сообщение.
    $channel->confirm_select();

    // 1. Direct Exchange
    $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);

    // 2–4. Три очереди и Binding каждой со своим Routing Key
    foreach ($bindings as $routingKey => $queue) {
        $channel->queue_declare($queue, false, true, false, false);
        $channel->queue_bind($queue, $exchange, $routingKey);
        echo "Binding: {$exchange} --[{$routingKey}]--> {$queue}\n";
    }
    echo "\n";

    // 5. Отправка через Exchange
    $orderId = (int) ($argv[1] ?? 201);
    foreach (array_keys($bindings) as $routingKey) {
        $body = json_encode([
            'order_id' => $orderId,
            'user_id' => 15,
            'event' => $routingKey,
            'created_at' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);

        $channel->basic_publish(
            new AMQPMessage($body, [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]),
            $exchange,
            $routingKey
        );
        echo "Отправлено с ключом {$routingKey}: {$body}\n";
    }

    $channel->wait_for_pending_acks(5);

    echo "\nСообщений в очередях:\n";
    foreach ($bindings as $queue) {
        [, $count] = $channel->queue_declare($queue, true);
        echo "  {$queue}: {$count}\n";
    }

    $channel->close();
    $connection->close();
} catch (Throwable $e) {
    echo 'Ошибка: '.$e->getMessage()."\n";
    exit(1);
}
