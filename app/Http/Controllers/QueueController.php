<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Queue;
use Illuminate\View\View;
use Throwable;

class QueueController extends Controller
{
    public function index(): View
    {
        return view('queue.index', ['connection' => config('queue.default')]);
    }

    /**
     * Сколько задач ждёт в очереди и последние записи о заказах из laravel.log.
     */
    public function status(): JsonResponse
    {
        try {
            $pending = Queue::size();
        } catch (Throwable) {
            $pending = null;
        }

        return response()->json([
            'connection' => config('queue.default'),
            'pending' => $pending,
            'log' => $this->tail(storage_path('logs/laravel.log'), 12),
        ]);
    }

    /** @return list<array{time: string, level: string, text: string}> */
    private function tail(string $file, int $limit): array
    {
        if (! is_file($file)) {
            return [];
        }

        $fh = fopen($file, 'rb');
        fseek($fh, max(0, filesize($file) - 64 * 1024));
        $chunk = stream_get_contents($fh);
        fclose($fh);

        preg_match_all('/^\[(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)\] \w+\.(\w+): (.*"order_id":\d+.*)$/mu', $chunk, $m, PREG_SET_ORDER);

        return collect($m)
            ->take(-$limit)
            ->reverse()
            ->map(fn ($row) => ['time' => substr($row[1], 11), 'level' => strtolower($row[2]), 'text' => trim($row[3])])
            ->values()
            ->all();
    }
}
