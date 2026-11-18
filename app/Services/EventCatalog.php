<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Чтение мероприятий через кеш.
 *
 * Список целиком лежит в кеше Laravel (драйвер redis) под ключом events:list.
 * Карточка одного мероприятия лежит в Redis как Hash event:{id}, как в задании 4.
 * Если Redis недоступен, данные читаются напрямую из базы, сайт не падает.
 */
class EventCatalog
{
    public const LIST_KEY = 'events:list';

    public const LIST_TTL = 300;

    public const CARD_TTL = 300;

    /**
     * @return array{events: array<int, array>, source: string, ms: float, queries: int, ttl: ?int}
     *         source: redis (из кеша), db (промах кеша, записали), offline (Redis недоступен)
     */
    public function list(): array
    {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        // Открываем оба соединения заранее, чтобы замер сравнивал только
        // чтение из кеша и SQL-запрос, без времени на подключение.
        DB::connection()->getPdo();
        $store = Cache::store();
        try {
            $store->has('__warmup');
        } catch (Throwable) {
            // Redis недоступен — ниже сработает запасной путь через базу.
        }

        $missed = false;
        $started = hrtime(true);

        try {
            $events = $store->remember(self::LIST_KEY, self::LIST_TTL, function () use (&$missed) {
                $missed = true;

                return $this->fresh();
            });
            $source = $missed ? 'db' : 'redis';
        } catch (Throwable) {
            $events = $this->fresh();
            $source = 'offline';
        }

        $ms = (hrtime(true) - $started) / 1e6;

        return [
            'events' => $events,
            'source' => $source,
            'ms' => round($ms, 2),
            'queries' => $queries,
            'ttl' => $source === 'offline' ? null : $this->listTtl(),
        ];
    }

    /** Сколько секунд осталось жить кешу списка (null — ключа нет или Redis молчит). */
    public function listTtl(): ?int
    {
        try {
            $key = Cache::getStore()->getPrefix().self::LIST_KEY;
            $ttl = (int) Redis::connection(config('cache.stores.redis.connection'))->ttl($key);

            return $ttl > 0 ? $ttl : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Карточка мероприятия: сначала HGETALL event:{id}, при промахе — база и HSET + EXPIRE.
     *
     * @return array{card: ?array, hit: bool, log: array<int, array{cmd: string, result: string}>}
     */
    public function card(int $id): array
    {
        $key = "event:{$id}";
        $log = [];

        try {
            $hash = Redis::hgetall($key);
            $log[] = ['cmd' => "HGETALL {$key}", 'result' => $hash ? count($hash).' полей' : '(empty array)'];

            if ($hash) {
                return ['card' => $this->castCard($hash), 'hit' => true, 'log' => $log];
            }
        } catch (Throwable) {
            $event = Event::find($id);

            return ['card' => $event ? $this->toCard($event) : null, 'hit' => false, 'log' => []];
        }

        $event = Event::find($id);
        if (! $event) {
            return ['card' => null, 'hit' => false, 'log' => $log];
        }

        $card = $this->toCard($event);
        Redis::hset($key, ...$this->flatten($card));
        Redis::expire($key, self::CARD_TTL);
        $log[] = ['cmd' => "HSET {$key} name … price {$card['price']}", 'result' => '(integer) '.count($card)];
        $log[] = ['cmd' => "EXPIRE {$key} ".self::CARD_TTL, 'result' => '(integer) 1'];

        return ['card' => $card, 'hit' => false, 'log' => $log];
    }

    /** Удаляет кеш после изменения мероприятия. */
    public function forget(?int $id = null): void
    {
        try {
            Cache::forget(self::LIST_KEY);
            if ($id) {
                Redis::del("event:{$id}");
            }
        } catch (Throwable) {
            // Redis выключен — чистить нечего, при запуске кеш соберётся заново.
        }
    }

    /** Список прямо из базы, в том виде, в каком он лежит в кеше. */
    public function fresh(): array
    {
        return Event::query()
            ->select('id', 'name', 'category', 'starts_at', 'city', 'venue', 'price', 'tickets_total')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Event $e) => $this->toCard($e))
            ->all();
    }

    private function toCard(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'category' => $event->category,
            'starts_at' => $event->starts_at->toIso8601String(),
            'city' => $event->city,
            'venue' => $event->venue,
            'price' => $event->price,
            'tickets_total' => $event->tickets_total,
            'description' => (string) $event->description,
        ];
    }

    private function castCard(array $hash): array
    {
        $hash['id'] = (int) $hash['id'];
        $hash['price'] = (int) $hash['price'];
        $hash['tickets_total'] = (int) $hash['tickets_total'];

        return $hash;
    }

    private function flatten(array $card): array
    {
        $flat = [];
        foreach ($card as $field => $value) {
            $flat[] = $field;
            $flat[] = (string) $value;
        }

        return $flat;
    }
}
