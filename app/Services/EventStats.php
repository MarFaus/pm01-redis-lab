<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Живая статистика мероприятий в Redis:
 *  - event:{id}:views     String + INCR  — все просмотры;
 *  - event:{id}:visitors  Set + SADD     — уникальные посетители;
 *  - events:rating        Sorted Set     — рейтинг по просмотрам.
 */
class EventStats
{
    public const RATING_KEY = 'events:rating';

    /**
     * Засчитывает просмотр и возвращает список выполненных команд с ответами Redis.
     *
     * @return array{views: int, visitors: int, new_visitor: bool, place: ?int, log: array<int, array{cmd: string, result: string}>}|null
     */
    public function recordView(int $id, string $visitor): ?array
    {
        try {
            $views = (int) Redis::incr("event:{$id}:views");
            $added = (int) Redis::sadd("event:{$id}:visitors", $visitor);
            $visitors = (int) Redis::scard("event:{$id}:visitors");
            $score = Redis::zincrby(self::RATING_KEY, 1, "event:{$id}");
            $rank = Redis::zrevrank(self::RATING_KEY, "event:{$id}");
        } catch (Throwable) {
            return null;
        }

        return [
            'views' => $views,
            'visitors' => $visitors,
            'new_visitor' => $added === 1,
            'place' => $rank === null ? null : (int) $rank + 1,
            'log' => [
                ['cmd' => "INCR event:{$id}:views", 'result' => "(integer) {$views}"],
                ['cmd' => "SADD event:{$id}:visitors {$visitor}", 'result' => "(integer) {$added}"],
                ['cmd' => "SCARD event:{$id}:visitors", 'result' => "(integer) {$visitors}"],
                ['cmd' => 'ZINCRBY '.self::RATING_KEY." 1 event:{$id}", 'result' => '"'.(int) $score.'"'],
                ['cmd' => 'ZREVRANK '.self::RATING_KEY." event:{$id}", 'result' => "(integer) {$rank}"],
            ],
        ];
    }

    /**
     * Просмотры и посетители для списка мероприятий — одним конвейером, без N запросов.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{views: int, visitors: int}>
     */
    public function forMany(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        try {
            $replies = Redis::pipeline(function ($pipe) use ($ids) {
                foreach ($ids as $id) {
                    $pipe->get("event:{$id}:views");
                    $pipe->scard("event:{$id}:visitors");
                }
            });
        } catch (Throwable) {
            return [];
        }

        $stats = [];
        foreach (array_values($ids) as $i => $id) {
            $stats[$id] = [
                'views' => (int) ($replies[$i * 2] ?? 0),
                'visitors' => (int) ($replies[$i * 2 + 1] ?? 0),
            ];
        }

        return $stats;
    }

    /**
     * Топ мероприятий: ZREVRANGE events:rating 0 {limit-1} WITHSCORES.
     *
     * @return array<int, int> id мероприятия => просмотры, по убыванию
     */
    public function top(int $limit = 5): array
    {
        try {
            $rows = Redis::zrevrange(self::RATING_KEY, 0, $limit - 1, ['withscores' => true]);
        } catch (Throwable) {
            return [];
        }

        $top = [];
        foreach ($rows as $member => $score) {
            $top[(int) str_replace('event:', '', $member)] = (int) $score;
        }

        return $top;
    }
}
