<?php

namespace Tests\Feature;

use App\Jobs\GenerateTicketDocument;
use App\Jobs\SendOrderNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueTest extends TestCase
{
    public function test_async_order_puts_job_in_queue_and_answers_fast(): void
    {
        Queue::fake();

        $res = $this->getJson('/async-order')->assertOk()->assertJson(['message' => 'Заказ создан', 'order_id' => 100]);

        Queue::assertPushed(SendOrderNotification::class, fn ($job) => $job->orderId === 100);
        $this->assertLessThan(1, $res->json('response_time'));
    }

    public function test_ticket_document_is_queued(): void
    {
        Queue::fake();

        $this->getJson('/ticket-document')->assertOk();

        Queue::assertPushed(GenerateTicketDocument::class, fn ($job) => $job->orderId === 100);
    }

    public function test_double_order_pushes_same_job_twice(): void
    {
        Queue::fake();

        $id = $this->getJson('/double-order')->assertOk()->json('order_id');

        Queue::assertPushed(SendOrderNotification::class, 2);
        Queue::assertPushed(SendOrderNotification::class, fn ($job) => $job->orderId === $id);
    }

    public function test_second_copy_of_notification_is_skipped(): void
    {
        config(['cache.default' => 'array']);
        Log::spy();

        Cache::put('order:555:notified', 'x', 60); // первая копия уже отработала
        (new SendOrderNotification(555))->handle();

        Log::shouldHaveReceived('warning')->once();
        Log::shouldNotHaveReceived('info');
    }

    public function test_queue_page_and_status(): void
    {
        $this->get('/queue')->assertOk()->assertSee('/sync-order')->assertSee('/async-order');
        $this->getJson('/queue/status')->assertOk()->assertJsonStructure(['connection', 'pending', 'log']);
    }
}
