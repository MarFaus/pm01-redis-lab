<?php

namespace App\Services;

use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * ЛР 50, этап 3: делает PDF электронного билета и кладёт его в storage/app/public/tickets.
 */
class TicketGenerator
{
    public function generate(Ticket $ticket): string
    {
        $ticket->load('order.event');

        $pdf = Pdf::loadView('tickets.pdf', [
            'ticket' => $ticket,
            'order' => $ticket->order,
            'event' => $ticket->order->event,
        ]);

        $path = 'tickets/'.$ticket->ticket_number.'.pdf';

        Storage::disk('public')->put(
            $path,
            $pdf->output()
        );

        return $path;
    }
}
