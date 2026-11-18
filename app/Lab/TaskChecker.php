<?php

namespace App\Lab;

/**
 * Проверяет задания по текущему состоянию лабораторной базы.
 * Проверка «живая»: если ключи истекли или удалены, задание снова станет невыполненным.
 */
class TaskChecker
{
    public function __construct(private KeyInspector $inspector) {}

    /**
     * @return array<string, array{done: bool, detail: string}>
     */
    public function check(): array
    {
        $keys = collect($this->inspector->snapshot('lab')['keys']);
        $byType = fn (string $type) => $keys->where('type', $type)->values();

        $strings = $byType('string');
        $withTtl = $keys->filter(fn ($k) => $k['ttl'] > 0);
        $hashes = $byType('hash')->filter(fn ($k) => $k['size'] >= 4);
        $counters = $strings->filter(fn ($k) => str_contains($k['key'], 'views')
            && preg_match('/^"-?\d+"$/', $k['preview']));
        $sets = $byType('set')->filter(fn ($k) => $k['size'] >= 4);
        $zsets = $byType('zset')->filter(fn ($k) => $k['size'] >= 5);

        $leader = $counters->sortByDesc(fn ($k) => (int) trim($k['preview'], '"'))->first();
        $zsetLeader = $zsets->first();

        return [
            'strings' => [
                'done' => $strings->count() >= 4,
                'detail' => "Строковых ключей: {$strings->count()} из 4.",
            ],
            'ttl' => [
                'done' => $withTtl->count() >= 3,
                'detail' => $withTtl->count()
                    ? 'Ключей с TTL сейчас: '.$withTtl->count().' ('.$withTtl->pluck('ttl')->map(fn ($t) => "{$t} с")->implode(', ').').'
                    : 'Ключей с TTL нет. Если вы их создавали, они уже истекли — так и должно быть.',
            ],
            'hash' => [
                'done' => $hashes->count() >= 3,
                'detail' => "Хешей с четырьмя и более полями: {$hashes->count()} из 3.",
            ],
            'counters' => [
                'done' => $counters->count() >= 3,
                'detail' => $counters->count()
                    ? "Счётчиков: {$counters->count()} из 3. Больше всех у {$leader['key']}: ".trim($leader['preview'], '"').'.'
                    : 'Счётчиков *views* пока нет.',
            ],
            'sets' => [
                'done' => $sets->isNotEmpty() && $zsets->isNotEmpty(),
                'detail' => ($sets->isNotEmpty() ? 'Множество посетителей есть. ' : 'Нужно множество из 4+ элементов. ')
                    .($zsetLeader
                        ? 'Лидер рейтинга '.$zsetLeader['key'].': '.explode(',', $zsetLeader['preview'])[0].'.'
                        : 'Нужен рейтинг из 5+ мероприятий.'),
            ],
        ];
    }
}
