<?php

namespace App\Lab;

use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;

/**
 * Выполняет команды Redis из браузерной консоли «Лаборатории».
 *
 * Команды идут только в отдельную базу (connection "lab"), список разрешённых
 * команд закрытый: нельзя переключить базу, поменять конфиг или выключить сервер.
 */
class CommandRunner
{
    public const MAX_LENGTH = 1000;

    public const MAX_ARGS = 64;

    /** Разрешённые команды: всё, что нужно для заданий 2–6, и немного сверху. */
    public const ALLOWED = [
        'PING', 'ECHO', 'DBSIZE', 'FLUSHDB', 'KEYS', 'SCAN', 'TYPE', 'EXISTS', 'DEL', 'RENAME',
        'SET', 'GET', 'MSET', 'MGET', 'APPEND', 'STRLEN', 'GETDEL',
        'INCR', 'INCRBY', 'DECR', 'DECRBY',
        'EXPIRE', 'TTL', 'PTTL', 'PERSIST',
        'HSET', 'HGET', 'HGETALL', 'HDEL', 'HEXISTS', 'HKEYS', 'HVALS', 'HLEN', 'HINCRBY', 'HMGET',
        'LPUSH', 'RPUSH', 'LPOP', 'RPOP', 'LRANGE', 'LLEN', 'LINDEX', 'LREM',
        'SADD', 'SREM', 'SMEMBERS', 'SCARD', 'SISMEMBER', 'SINTER', 'SUNION', 'SDIFF',
        'ZADD', 'ZRANGE', 'ZREVRANGE', 'ZINCRBY', 'ZSCORE', 'ZREM', 'ZCARD', 'ZRANK', 'ZREVRANK', 'ZCOUNT',
    ];

    /**
     * Разбивает строку как redis-cli: пробелы делят аргументы, кавычки их склеивают.
     *
     * @return array<int, string>
     */
    public function tokenize(string $line): array
    {
        $tokens = [];
        $current = '';
        $quote = null;
        $hasToken = false;
        $length = mb_strlen($line);

        for ($i = 0; $i < $length; $i++) {
            $ch = mb_substr($line, $i, 1);

            if ($quote !== null) {
                if ($ch === '\\' && $i + 1 < $length) {
                    $next = mb_substr($line, ++$i, 1);
                    $current .= match ($next) {
                        'n' => "\n",
                        't' => "\t",
                        default => $next,
                    };
                } elseif ($ch === $quote) {
                    $quote = null;
                } else {
                    $current .= $ch;
                }

                continue;
            }

            if ($ch === '"' || $ch === "'") {
                $quote = $ch;
                $hasToken = true;
            } elseif (trim($ch) === '') {
                if ($hasToken) {
                    $tokens[] = $current;
                    $current = '';
                    $hasToken = false;
                }
            } else {
                $current .= $ch;
                $hasToken = true;
            }
        }

        if ($quote !== null) {
            throw new InvalidArgumentException('Не закрыта кавычка. Проверьте, что у каждого значения есть пара кавычек.');
        }

        if ($hasToken) {
            $tokens[] = $current;
        }

        return $tokens;
    }

    /**
     * @return array{ok: bool, command: string, output: string}
     */
    public function run(string $line): array
    {
        $line = trim($line);

        if ($line === '') {
            throw new InvalidArgumentException('Введите команду, например: SET student:name "Александр"');
        }

        if (mb_strlen($line) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Команда длиннее '.self::MAX_LENGTH.' символов.');
        }

        $args = $this->tokenize($line);

        if (count($args) > self::MAX_ARGS) {
            throw new InvalidArgumentException('Слишком много аргументов: максимум '.self::MAX_ARGS.'.');
        }

        $command = strtoupper($args[0]);

        if (! in_array($command, self::ALLOWED, true)) {
            throw new InvalidArgumentException(
                "Команда {$command} в лаборатории недоступна. Работают команды для строк, хешей, списков, множеств, TTL и счётчиков."
            );
        }

        $args[0] = $command;
        $client = Redis::connection('lab')->client();
        $error = false;
        $reply = $client->executeRaw($args, $error);

        return [
            'ok' => ! $error,
            'command' => $command,
            'output' => $error ? '(error) '.$reply : $this->format($reply),
        ];
    }

    /** Форматирует ответ так же, как это делает redis-cli. */
    public function format(mixed $reply, int $depth = 0): string
    {
        if ($reply === null) {
            return '(nil)';
        }

        if (is_int($reply)) {
            return "(integer) {$reply}";
        }

        if (is_array($reply)) {
            if ($reply === []) {
                return '(empty array)';
            }

            $lines = [];
            $width = strlen((string) count($reply));
            $pad = str_repeat(' ', $depth * ($width + 2));
            $n = 1;
            foreach ($reply as $item) {
                $label = str_pad((string) $n, $width, ' ', STR_PAD_LEFT).') ';
                $body = $this->format($item, $depth + 1);
                $lines[] = ($n === 1 ? '' : $pad).$label.ltrim($body);
                $n++;
            }

            return implode("\n", $lines);
        }

        $reply = (string) $reply;

        // Статусные ответы (OK, PONG) redis-cli печатает без кавычек.
        if (in_array($reply, ['OK', 'PONG', 'QUEUED'], true)) {
            return $reply;
        }

        return '"'.addcslashes($reply, "\"\\\n\t").'"';
    }
}
