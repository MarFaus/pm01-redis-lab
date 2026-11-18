<?php

namespace App\Lab;

use Illuminate\Support\Facades\Redis;

/**
 * Снимок ключей одной базы Redis: тип, TTL и короткое превью значения.
 * Перебор идёт через SCAN, а не KEYS *, чтобы не блокировать сервер.
 */
class KeyInspector
{
    public const LIMIT = 200;

    /** Какие базы можно смотреть: лаборатория — для экспериментов, сайт — только чтение. */
    public const SCOPES = ['lab' => 'lab', 'app' => 'default'];

    /**
     * @return array{keys: array<int, array{key: string, type: string, ttl: int, preview: string, size: ?int}>, total: int, truncated: bool}
     */
    public function snapshot(string $scope = 'lab'): array
    {
        $client = Redis::connection(self::SCOPES[$scope] ?? 'lab')->client();

        $keys = [];
        $cursor = '0';
        do {
            [$cursor, $batch] = $client->scan($cursor, ['COUNT' => 100]);
            foreach ($batch as $key) {
                $keys[$key] = true;
            }
        } while ($cursor !== '0' && $cursor !== 0 && count($keys) < self::LIMIT);

        $keys = array_keys($keys);
        sort($keys, SORT_NATURAL);
        $keys = array_slice($keys, 0, self::LIMIT);

        if (! $keys) {
            return ['keys' => [], 'total' => (int) $client->dbsize(), 'truncated' => false];
        }

        $meta = $client->pipeline(function ($pipe) use ($keys) {
            foreach ($keys as $key) {
                $pipe->type($key);
                $pipe->ttl($key);
            }
        });

        $types = [];
        foreach ($keys as $i => $key) {
            $types[$key] = [(string) $meta[$i * 2], (int) $meta[$i * 2 + 1]];
        }

        $values = $client->pipeline(function ($pipe) use ($types) {
            foreach ($types as $key => [$type]) {
                match ($type) {
                    'string' => $pipe->getrange($key, 0, 80),
                    'hash' => $pipe->hgetall($key),
                    'list' => $pipe->lrange($key, 0, 5),
                    'set' => $pipe->srandmember($key, 6),
                    'zset' => $pipe->zrevrange($key, 0, 5, ['withscores' => true]),
                    default => $pipe->ping(),
                };
            }
        });

        $sizes = $client->pipeline(function ($pipe) use ($types) {
            foreach ($types as $key => [$type]) {
                match ($type) {
                    'hash' => $pipe->hlen($key),
                    'list' => $pipe->llen($key),
                    'set' => $pipe->scard($key),
                    'zset' => $pipe->zcard($key),
                    default => $pipe->strlen($key),
                };
            }
        });

        $rows = [];
        $i = 0;
        foreach ($types as $key => [$type, $ttl]) {
            $rows[] = [
                'key' => $key,
                'type' => $type,
                'ttl' => $ttl,
                'preview' => $this->preview($type, $values[$i]),
                'size' => $type === 'string' ? null : (int) $sizes[$i],
            ];
            $i++;
        }

        $total = (int) $client->dbsize();

        return ['keys' => $rows, 'total' => $total, 'truncated' => $total > count($rows)];
    }

    private function preview(string $type, mixed $value): string
    {
        $text = match ($type) {
            'string' => '"'.$value.'"',
            'hash' => collect($value)->map(fn ($v, $f) => "{$f}: {$v}")->implode(', '),
            'list', 'set' => implode(', ', (array) $value),
            'zset' => collect($value)->map(fn ($s, $m) => "{$m} ({$s})")->implode(', '),
            default => '',
        };

        return mb_strimwidth($text, 0, 90, '…');
    }
}
