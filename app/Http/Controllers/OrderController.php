<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Order;
use App\Services\RabbitMQProducer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * ЛР 50: оформление заказов, отправка в RabbitMQ, скачивание PDF-билета.
 */
class OrderController extends Controller
{
    public function index(): View
    {
        $orders = Order::with(['event', 'ticket'])
            ->latest('id')
            ->get();

        return view('orders.index', compact('orders'));
    }

    public function store(Request $request, RabbitMQProducer $producer): RedirectResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ], [
            'customer_name.required' => 'Укажите имя покупателя.',
            'customer_email.required' => 'Укажите электронную почту.',
            'customer_email.email' => 'Почта должна быть вида name@example.com.',
            'quantity.min' => 'Минимум 1 билет.',
            'quantity.max' => 'Не больше 10 билетов в одном заказе.',
            'quantity.integer' => 'Количество — целое число.',
        ]);

        // Создаём заказ в базе
        $order = DB::transaction(function () use ($validated) {
            // Блокируем строку мероприятия на время покупки
            $event = Event::query()
                ->lockForUpdate()
                ->findOrFail($validated['event_id']);

            $quantity = (int) $validated['quantity'];

            if ($event->available_tickets < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Недостаточно свободных билетов.',
                ]);
            }

            $order = Order::create([
                'event_id' => $event->id,
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'quantity' => $quantity,
                'total_amount' => $event->price * $quantity,
                'status' => 'pending',
            ]);

            // Уменьшаем количество доступных билетов
            $event->decrement('available_tickets', $quantity);

            return $order;
        });

        // После сохранения заказа отправляем сообщение в RabbitMQ
        try {
            $producer->send([
                'event' => 'order.created',
                'order_id' => $order->id,
                'event_id' => $order->event_id,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'quantity' => $order->quantity,
                'total_amount' => (float) $order->total_amount,
                'created_at' => $order->created_at->toISOString(),
            ]);

            return redirect()
                ->route('orders.index')
                ->with('success', "Заказ №{$order->id} создан и отправлен в RabbitMQ!");
        } catch (Throwable $e) {
            Log::error('Не удалось отправить заказ в RabbitMQ', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('orders.index')
                ->with('error', "Заказ №{$order->id} сохранён, но сообщение не отправлено: RabbitMQ недоступен.");
        }
    }

    public function downloadTicket(Order $order): StreamedResponse
    {
        $ticket = $order->ticket;

        abort_unless(
            $ticket && $ticket->status === 'generated' && $ticket->file_path,
            404,
            'Билет ещё не сформирован.'
        );

        abort_unless(Storage::disk('public')->exists($ticket->file_path), 404);

        return Storage::disk('public')->download($ticket->file_path);
    }
}
