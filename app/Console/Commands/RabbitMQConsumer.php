<?php

namespace App\Console\Commands;

use App\Services\OrderTicketProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

/**
 * ЛР 50, этап 3: Consumer. Ждёт сообщения order.created в ticket_queue,
 * формирует PDF-билет и переводит заказ в статус completed.
 *
 * Отличие от методички: если брокер ещё не поднялся (или перезапустился),
 * Consumer не завершается, а пробует подключиться снова каждые 5 секунд.
 * Так его можно открыть из start.bat сразу, вместе с сайтом.
 */
class RabbitMQConsumer extends Command
{
    protected $signature = 'rabbitmq:consume {--no-retry : Завершиться при ошибке подключения}';

    protected $description = 'Обработка заказов через RabbitMQ';

    public function handle(OrderTicketProcessor $processor): int
    {
        while (true) {
            $result = $this->consume($processor);

            if ($result === self::SUCCESS || $this->option('no-retry')) {
                return $result;
            }

            $this->warn('Повторное подключение через 5 секунд...');
            sleep(5);
        }
    }

    private function consume(OrderTicketProcessor $processor): int
    {
        $connection = null;
        $channel = null;

        try {
            // Подключение к RabbitMQ
            $connection = new AMQPStreamConnection(
                config('rabbitmq.host'),
                config('rabbitmq.port'),
                config('rabbitmq.user'),
                config('rabbitmq.password'),
                connection_timeout: config('rabbitmq.connection_timeout'),
            );

            $channel = $connection->channel();
            $queue = config('rabbitmq.queue');

            // Проверяем наличие очереди
            $channel->queue_declare($queue, false, true, false, false);

            // Получаем по одному неподтверждённому сообщению
            $channel->basic_qos(0, 1, false);

            $this->info('Consumer запущен.');
            $this->info('Ожидание новых заказов...');

            // Обработчик сообщений
            $callback = function (AMQPMessage $message) use ($processor) {
                try {
                    foreach ($processor->process($message->getBody()) as $line) {
                        $this->info($line);
                    }

                    // Подтверждаем успешную обработку
                    $message->ack();
                } catch (Throwable $e) {
                    Log::error('Ошибка обработки заказа RabbitMQ', [
                        'error' => $e->getMessage(),
                        'payload' => $message->getBody(),
                    ]);

                    $this->error($e->getMessage());

                    // Отклоняем сообщение без повторной постановки
                    $message->nack(false, false);
                }
            };

            // Подписываемся на очередь
            $channel->basic_consume($queue, '', false, false, false, false, $callback);

            // Постоянно ожидаем новые сообщения
            while ($channel->is_consuming()) {
                $channel->wait();
            }
        } catch (Throwable $e) {
            $this->error('Ошибка Consumer: '.$e->getMessage());
            Log::error('RabbitMQ Consumer error', [
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        } finally {
            try {
                if ($channel !== null && $channel->is_open()) {
                    $channel->close();
                }

                if ($connection !== null && $connection->isConnected()) {
                    $connection->close();
                }
            } catch (Throwable) {
                // соединение уже разорвано
            }
        }

        return self::SUCCESS;
    }
}
