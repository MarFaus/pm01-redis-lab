<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\OrderTicketProcessor;
use App\Services\RabbitMQProducer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private function event(int $available = 5, int $price = 5000): Event
    {
        return Event::withoutEvents(fn () => Event::factory()->create([
            'price' => $price,
            'available_tickets' => $available,
        ]));
    }

    private function order(Event $event, array $override = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/orders', array_merge([
            'event_id' => $event->id,
            'customer_name' => 'Иван',
            'customer_email' => 'ivan@example.com',
            'quantity' => 2,
        ], $override));
    }

    public function test_order_is_saved_tickets_decremented_and_message_sent(): void
    {
        $event = $this->event(available: 5, price: 5000);
        $sent = null;
        $this->mock(RabbitMQProducer::class)
            ->shouldReceive('send')->once()
            ->with(Mockery::on(function ($data) use (&$sent) {
                $sent = $data;

                return true;
            }));

        $this->order($event)
            ->assertRedirect('/orders')
            ->assertSessionHas('success');

        $order = Order::sole();
        $this->assertSame('pending', $order->status);
        $this->assertSame(10000.0, (float) $order->total_amount);
        $this->assertSame(3, $event->fresh()->available_tickets);

        $this->assertSame('order.created', $sent['event']);
        $this->assertSame($order->id, $sent['order_id']);
        $this->assertSame(2, $sent['quantity']);
    }

    public function test_order_is_kept_when_rabbitmq_is_down(): void
    {
        $event = $this->event();
        $this->mock(RabbitMQProducer::class)
            ->shouldReceive('send')->andThrow(new RuntimeException('connection refused'));

        $this->order($event)
            ->assertRedirect('/orders')
            ->assertSessionHas('error');

        $this->assertSame(1, Order::count());
    }

    public function test_cannot_buy_more_tickets_than_available(): void
    {
        $event = $this->event(available: 1);
        $this->mock(RabbitMQProducer::class)->shouldNotReceive('send');

        $this->order($event, ['quantity' => 2])->assertSessionHasErrors('quantity');

        $this->assertSame(0, Order::count());
        $this->assertSame(1, $event->fresh()->available_tickets);
    }

    public function test_validation(): void
    {
        $event = $this->event();
        $this->mock(RabbitMQProducer::class)->shouldNotReceive('send');

        $this->order($event, ['customer_email' => 'не почта', 'quantity' => 11, 'customer_name' => ''])
            ->assertSessionHasErrors(['customer_email', 'quantity', 'customer_name']);
    }

    public function test_consumer_generates_pdf_and_completes_order(): void
    {
        Storage::fake('public');
        $event = $this->event();
        $order = Order::create([
            'event_id' => $event->id, 'customer_name' => 'Иван', 'customer_email' => 'ivan@example.com',
            'quantity' => 1, 'total_amount' => 5000, 'status' => 'pending',
        ]);

        $out = app(OrderTicketProcessor::class)->process(json_encode(['event' => 'order.created', 'order_id' => $order->id]));

        $ticket = Ticket::sole();
        $this->assertSame('generated', $ticket->status);
        $this->assertSame('completed', $order->fresh()->status);
        Storage::disk('public')->assertExists($ticket->file_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('public')->get($ticket->file_path));
        $this->assertContains('Билет успешно сформирован!', $out);

        // Повторное сообщение о том же заказе не создаёт второй билет.
        $again = app(OrderTicketProcessor::class)->process(json_encode(['event' => 'order.created', 'order_id' => $order->id]));
        $this->assertSame(1, Ticket::count());
        $this->assertStringContainsString('уже сформирован', end($again));

        // Скачивание
        $this->get("/orders/{$order->id}/ticket")->assertOk()->assertDownload("{$ticket->ticket_number}.pdf");
    }

    public function test_consumer_rejects_wrong_message(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        app(OrderTicketProcessor::class)->process(json_encode(['event' => 'ticket.created', 'order_id' => 1]));
    }

    public function test_ticket_download_is_404_until_generated(): void
    {
        $event = $this->event();
        $order = Order::create([
            'event_id' => $event->id, 'customer_name' => 'Иван', 'customer_email' => 'ivan@example.com',
            'quantity' => 1, 'total_amount' => 5000, 'status' => 'pending',
        ]);

        $this->get("/orders/{$order->id}/ticket")->assertNotFound();
        $this->get('/orders')->assertOk()->assertSee('Формируется...');
    }
}
