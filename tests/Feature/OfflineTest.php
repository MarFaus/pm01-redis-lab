<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Redis выключен: сайт не падает, а честно говорит, что случилось и что делать.
 */
class OfflineTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Порт, на котором точно никого нет.
        foreach (['default', 'cache', 'lab'] as $connection) {
            config(["database.redis.{$connection}.port" => 1]);
            config(["database.redis.{$connection}.timeout" => 0.2]);
        }
        Redis::purge('default');
        Redis::purge('cache');
        Redis::purge('lab');
        app('cache')->forgetDriver('redis');
    }

    public function test_afisha_falls_back_to_database(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Redis не отвечает')
            ->assertSee('Хакатон 2026')
            ->assertSee('Redis недоступен, список взят из базы напрямую.');
    }

    public function test_event_page_works_without_counters(): void
    {
        $this->get('/events/1')
            ->assertOk()
            ->assertSee('Хакатон 2026')
            ->assertSee('Просмотры не считаются');
    }

    public function test_lab_explains_how_to_start_redis(): void
    {
        $this->get('/lab')->assertOk()->assertSee('Redis не запущен');
        $this->postJson('/lab/run', ['command' => 'PING'])->assertStatus(503)->assertJsonPath('output', fn ($o) => str_contains($o, 'start.bat'));
    }

    public function test_price_change_still_saves_when_redis_is_down(): void
    {
        $this->patch('/events/1/price', ['price' => 4200])->assertRedirect('/events/1');
        $this->assertDatabaseHas('events', ['id' => 1, 'price' => 4200]);
    }
}
