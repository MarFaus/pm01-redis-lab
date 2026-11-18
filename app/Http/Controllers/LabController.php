<?php

namespace App\Http\Controllers;

use App\Lab\CommandRunner;
use App\Lab\KeyInspector;
use App\Lab\TaskChecker;
use App\Lab\Tasks;
use App\Services\RedisHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class LabController extends Controller
{
    /** Ограничение частоты команд: тот самый Rate Limiting на INCR + EXPIRE из лекции. */
    public const RATE_LIMIT = 60;

    public const RATE_WINDOW = 60;

    public function index(RedisHealth $health): View
    {
        return view('lab.index', [
            'tasks' => Tasks::all(),
            'health' => $health->info(),
        ]);
    }

    public function run(Request $request, CommandRunner $runner): JsonResponse
    {
        $data = $request->validate(['command' => ['required', 'string', 'max:'.CommandRunner::MAX_LENGTH]]);

        try {
            if (($left = $this->hit($request)) < 0) {
                return response()->json([
                    'ok' => false,
                    'output' => '(error) Слишком часто: не больше '.self::RATE_LIMIT.' команд в минуту. Подождите немного.',
                ], 429);
            }

            $result = $runner->run($data['command']);

            return response()->json($result + ['left' => $left]);
        } catch (InvalidArgumentException $e) {
            // Ошибка команды — обычный ответ консоли (как в redis-cli), а не сбой запроса.
            return response()->json(['ok' => false, 'output' => '(error) '.$e->getMessage()]);
        } catch (Throwable) {
            return $this->offline();
        }
    }

    public function keys(Request $request, KeyInspector $inspector): JsonResponse
    {
        $scope = $request->query('scope') === 'app' ? 'app' : 'lab';

        try {
            return response()->json($inspector->snapshot($scope) + ['scope' => $scope]);
        } catch (Throwable) {
            return $this->offline();
        }
    }

    public function check(TaskChecker $checker): JsonResponse
    {
        try {
            return response()->json($checker->check());
        } catch (Throwable) {
            return $this->offline();
        }
    }

    /**
     * Счётчик запросов на окно в 60 секунд. Возвращает, сколько команд осталось (меньше 0 — лимит исчерпан).
     */
    private function hit(Request $request): int
    {
        $key = 'ratelimit:lab:'.$request->attributes->get('visitor', $request->ip());
        $count = (int) Redis::incr($key);

        if ($count === 1) {
            Redis::expire($key, self::RATE_WINDOW);
        }

        return self::RATE_LIMIT - $count;
    }

    private function offline(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'output' => '(error) Redis не отвечает на 127.0.0.1:6379. Запустите start.bat — он поднимет Redis сам.',
        ], 503);
    }
}
