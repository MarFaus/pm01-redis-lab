<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\Concerns\UsesRedis;
use Tests\TestCase;

/**
 * Афиша, кеш списка и страница мероприятия.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase, UsesRedis;

    protected $seed = true;

    public function test_afisha_shows_seeded_events(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Хакатон 2026')
            ->assertSee('Чек запроса');
    }

    public function test_first_request_goes_to_database_second_to_redis_cache(): void
    {
        $this->get('/')->assertOk()->assertSee('база данных');
        $this->assertTrue(Cache::has('events:list'));
        $this->assertGreaterThan(0, Redis::connection('cache')->ttl('events:list'));

        $this->get('/')->assertOk()->assertSee('кеш Redis');
    }

    public function test_city_filter_keeps_only_that_city(): void
    {
        $this->get('/?city=Алматы')
            ->assertOk()
            ->assertSee('Ночь джаза')
            ->assertDontSee('Хакатон 2026');
    }

    public function test_opening_event_counts_view_unique_visitor_and_rating(): void
    {
        $event = Event::where('name', 'Ночь джаза')->first();

        $this->withCookie('kassa_visitor', 'v-aaaaaa')->get("/events/{$event->id}")->assertOk()->assertSee('№ 1');
        $this->withCookie('kassa_visitor', 'v-aaaaaa')->get("/events/{$event->id}")->assertOk()->assertSee('№ 2');
        $this->withCookie('kassa_visitor', 'v-bbbbbb')->get("/events/{$event->id}")->assertOk();

        $this->assertSame('3', Redis::get("event:{$event->id}:views"));
        $this->assertSame(2, (int) Redis::scard("event:{$event->id}:visitors"));
        $this->assertEquals(3, Redis::zscore('events:rating', "event:{$event->id}"));
    }

    public function test_event_card_is_cached_as_hash_with_ttl(): void
    {
        $event = Event::first();

        $this->get("/events/{$event->id}")->assertOk()->assertSee('не было');
        $this->assertSame($event->name, Redis::hget("event:{$event->id}", 'name'));
        $this->assertGreaterThan(0, Redis::ttl("event:{$event->id}"));

        $this->get("/events/{$event->id}")->assertOk()->assertSee('база не трогалась');
    }

    public function test_price_change_invalidates_cache(): void
    {
        $event = Event::first();
        $this->get('/');
        $this->get("/events/{$event->id}");
        $this->assertTrue(Cache::has('events:list'));
        $this->assertSame(1, (int) Redis::exists("event:{$event->id}"));

        $this->patch("/events/{$event->id}/price", ['price' => 7777])
            ->assertRedirect("/events/{$event->id}")
            ->assertSessionHas('status');

        $this->assertSame(7777, $event->fresh()->price);
        $this->assertFalse(Cache::has('events:list'));
        $this->assertSame(0, (int) Redis::exists("event:{$event->id}"));
        $this->get('/')->assertSee('7 777 ₸');
    }

    public function test_price_validation(): void
    {
        $event = Event::first();

        $this->patch("/events/{$event->id}/price", ['price' => -5])->assertSessionHasErrors('price');
        $this->patch("/events/{$event->id}/price", ['price' => 'дорого'])->assertSessionHasErrors('price');
        $this->patch("/events/{$event->id}/price", [])->assertSessionHasErrors('price');
        $this->assertNotSame(-5, $event->fresh()->price);
    }

    public function test_forms_carry_csrf_token(): void
    {
        // Саму проверку CSRF Laravel в тестах отключает; здесь проверяем, что формы шлют токен.
        // Ручная проверка: curl -X POST http://127.0.0.1:8000/cache/forget → 419.
        $event = Event::first();

        $this->get("/events/{$event->id}")->assertSee('name="_token"', false);
        $this->get('/')->assertSee('name="_token"', false);
    }

    public function test_cache_forget_button_removes_list_key(): void
    {
        $this->get('/');
        $this->assertTrue(Cache::has('events:list'));

        $this->post('/cache/forget')->assertRedirect('/');
        $this->assertFalse(Cache::has('events:list'));
    }

    public function test_unknown_event_is_404(): void
    {
        $this->get('/events/99999')->assertNotFound()->assertSee('Такого билета нет');
    }

    public function test_event_names_are_escaped(): void
    {
        Event::factory()->create(['name' => '<script>alert(1)</script>']);
        Cache::forget('events:list');

        $this->get('/')->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }
}
