<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\Concerns\UsesRedis;
use Tests\TestCase;

/**
 * Лаборатория: консоль, изоляция базы, ключи, проверка заданий, лимит.
 */
class LabTest extends TestCase
{
    use RefreshDatabase, UsesRedis;

    private function run_(string $command)
    {
        // JSON-запросы в тестах шлют cookie только с withCredentials(), как fetch в браузере.
        return $this->withCredentials()->withCookie('kassa_visitor', 'v-tester')->postJson('/lab/run', ['command' => $command]);
    }

    public function test_lab_page_opens(): void
    {
        $this->get('/lab')->assertOk()->assertSee('Лаборатория')->assertSee('Задание 1 выполнено');
    }

    public function test_set_and_get_with_cyrillic(): void
    {
        $this->run_('SET student:name "Александр"')->assertJson(['ok' => true, 'output' => 'OK']);
        $this->run_('GET student:name')->assertJson(['ok' => true, 'output' => '"Александр"']);
        $this->run_('GET nothing')->assertJson(['output' => '(nil)']);
    }

    public function test_commands_go_to_separate_lab_database(): void
    {
        $this->run_('SET only:in:lab 1');

        $this->assertSame('1', Redis::connection('lab')->get('only:in:lab'));
        $this->assertNull(Redis::connection()->get('only:in:lab'));
    }

    public function test_flushdb_does_not_touch_site_data(): void
    {
        Redis::set('event:1:views', 42);
        $this->run_('SET temp 1');

        $this->run_('FLUSHDB')->assertJson(['ok' => true]);

        $this->assertSame('42', Redis::get('event:1:views'));
        $this->assertSame(0, (int) Redis::connection('lab')->dbsize());
    }

    public function test_forbidden_command_is_rejected(): void
    {
        $this->run_('CONFIG GET maxmemory')->assertJson(['ok' => false])->assertJsonPath('output', fn ($o) => str_contains($o, 'недоступна'));
        $this->run_('SELECT 0')->assertJson(['ok' => false]);
        $this->run_('FLUSHALL')->assertJson(['ok' => false]);
    }

    public function test_redis_errors_are_shown_like_redis_cli(): void
    {
        $this->run_('SET counter abc');
        $this->run_('INCR counter')->assertJson(['ok' => false])->assertJsonPath('output', fn ($o) => str_starts_with($o, '(error) ERR'));
    }

    public function test_command_is_required(): void
    {
        $this->run_('')->assertStatus(422);
    }

    public function test_hash_and_sorted_set_output(): void
    {
        $this->run_('HSET event:100 name "Хакатон 2026" city "Костанай" price 5000')->assertJson(['output' => '(integer) 3']);
        $this->run_('ZADD events:rating 150 "event:100" 250 "event:101"');
        $this->run_('ZREVRANGE events:rating 0 -1 WITHSCORES')
            ->assertJson(['output' => "1) \"event:101\"\n2) \"250\"\n3) \"event:100\"\n4) \"150\""]);
    }

    public function test_keys_snapshot_shows_type_and_ttl(): void
    {
        $this->run_('SET event:1:name "IT Conference" EX 120');
        $this->run_('SADD event:100:visitors 101 102 103');

        $keys = collect($this->getJson('/lab/keys')->assertOk()->json('keys'))->keyBy('key');

        $this->assertSame('string', $keys['event:1:name']['type']);
        $this->assertGreaterThan(100, $keys['event:1:name']['ttl']);
        $this->assertSame('set', $keys['event:100:visitors']['type']);
        $this->assertSame(3, $keys['event:100:visitors']['size']);
        $this->assertSame(-1, $keys['event:100:visitors']['ttl']);
    }

    public function test_app_scope_reads_site_database(): void
    {
        Redis::set('event:5:views', 9);

        $keys = collect($this->getJson('/lab/keys?scope=app')->json('keys'))->pluck('key');
        $this->assertContains('event:5:views', $keys);
    }

    public function test_task_checker(): void
    {
        $this->getJson('/lab/check')->assertJsonPath('strings.done', false);

        foreach (['SET s:name A', 'SET s:group B', 'SET s:spec C', 'SET s:module D',
            'SET k30 a EX 30', 'SET k60 b EX 60', 'SET k120 c EX 120',
            'HSET e:1 name A date 2026-12-01 place X price 100',
            'HSET e:2 name B date 2026-12-02 place Y price 200',
            'HSET e:3 name C date 2026-12-03 place Z price 300',
            'SET e:1:views 5', 'SET e:2:views 50', 'SET e:3:views 7',
            'SADD v 1 2 3 4', 'ZADD r 1 a 2 b 3 c 4 d 9 e'] as $cmd) {
            $this->run_($cmd)->assertJson(['ok' => true]);
        }

        $check = $this->getJson('/lab/check')->assertOk()->json();
        foreach (['strings', 'ttl', 'hash', 'counters', 'sets'] as $task) {
            $this->assertTrue($check[$task]['done'], "Задание {$task}: {$check[$task]['detail']}");
        }
        $this->assertStringContainsString('e:2:views', $check['counters']['detail']);
        $this->assertStringContainsString('e (9)', $check['sets']['detail']);
    }

    public function test_rate_limit_after_60_commands(): void
    {
        Redis::set('ratelimit:lab:v-tester', 60);
        Redis::expire('ratelimit:lab:v-tester', 60);

        $this->run_('PING')->assertStatus(429)->assertJsonPath('output', fn ($o) => str_contains($o, 'Слишком часто'));
    }
}
