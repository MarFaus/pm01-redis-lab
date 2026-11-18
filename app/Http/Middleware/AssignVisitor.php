<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Выдаёт браузеру короткий id посетителя (cookie на год).
 * По нему Redis-множество event:{id}:visitors считает уникальных зрителей.
 */
class AssignVisitor
{
    public const COOKIE = 'kassa_visitor';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->cookie(self::COOKIE);

        if (! is_string($id) || ! preg_match('/^v-[a-z0-9]{6}$/', $id)) {
            $id = 'v-'.Str::lower(Str::random(6));
            Cookie::queue(self::COOKIE, $id, 60 * 24 * 365);
        }

        $request->attributes->set('visitor', $id);

        return $next($request);
    }
}
