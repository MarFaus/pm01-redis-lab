<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Тесты, которым нужен живой Redis. Работают в базах 10–12 (см. phpunit.xml),
 * чтобы не трогать данные сайта (0–2). Если Redis не запущен, тест пропускается.
 */
trait UsesRedis
{
    protected function setUpUsesRedis(): void
    {
        try {
            foreach (['default', 'cache', 'lab'] as $connection) {
                Redis::connection($connection)->flushdb();
            }
        } catch (Throwable $e) {
            $this->markTestSkipped('Redis не запущен на 127.0.0.1:6379: '.$e->getMessage());
        }
    }
}
