<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Проверяет, отвечает ли Redis, и достаёт короткую сводку для шапки сайта.
 * Результат кешируется на время запроса, чтобы не стучаться в Redis по десять раз.
 */
class RedisHealth
{
    private ?array $info = null;

    public function online(): bool
    {
        return $this->info()['online'];
    }

    /**
     * @return array{online: bool, version: ?string, memory: ?string, mode: ?string, error: ?string}
     */
    public function info(): array
    {
        if ($this->info !== null) {
            return $this->info;
        }

        try {
            $client = Redis::connection()->client();
            $server = $client->info('server');
            $memory = $client->info('memory');

            return $this->info = [
                'online' => true,
                'version' => $server['Server']['redis_version'] ?? $server['redis_version'] ?? null,
                'memory' => $memory['Memory']['used_memory_human'] ?? $memory['used_memory_human'] ?? null,
                'mode' => $this->detectMode($server['Server']['os'] ?? $server['os'] ?? ''),
                'error' => null,
            ];
        } catch (Throwable $e) {
            return $this->info = [
                'online' => false,
                'version' => null,
                'memory' => null,
                'mode' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function detectMode(string $os): string
    {
        return str_contains($os, 'Linux') ? 'Docker / Linux' : 'Windows';
    }
}
