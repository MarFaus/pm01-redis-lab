<?php

return [
    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'queue' => env('RABBITMQ_QUEUE', 'ticket_queue'),
    // Сколько секунд ждать брокер, прежде чем сказать «RabbitMQ недоступен».
    'connection_timeout' => (float) env('RABBITMQ_TIMEOUT', 2),
];
