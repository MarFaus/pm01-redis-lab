<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\Concerns\UsesRedis;
use Tests\TestCase;

/**
 * Маршруты ровно из методички: задание 7 и дополнительное задание.
 */
class MethodRoutesTest extends TestCase
{
    use RefreshDatabase, UsesRedis;

    protected $seed = true;

    public function test_redis_test_route(): void
    {
        $this->get('/redis-test')->assertOk()->assertSeeText('Данные из Redis: Александр');
        $this->assertSame('Александр', Redis::get('student:name'));
    }

    public function test_views_route_increments_counter(): void
    {
        $this->getJson('/events/100/views')->assertExactJson(['event_id' => '100', 'views' => 1]);
        $this->getJson('/events/100/views')->assertExactJson(['event_id' => '100', 'views' => 2]);
        $this->assertSame('2', Redis::get('event:100:views'));
    }

    public function test_events_cache_route_uses_redis_cache(): void
    {
        $this->assertFalse(Cache::has('events:list'));

        $response = $this->getJson('/events-cache')->assertOk();
        $response->assertJsonCount(12);
        $response->assertJsonStructure([['id', 'name', 'price']]);
        $this->assertSame('IT Conference', $response->json('0.name'), 'Сортировка по названию');

        $this->assertTrue(Cache::has('events:list'));
        $this->assertGreaterThan(0, Redis::connection('cache')->ttl('events:list'));
    }
}
