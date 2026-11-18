<?php

namespace App\Lab;

/**
 * Задания практической работы (методичка ПМ01, тема «Знакомство с Redis»).
 * Команды можно кликнуть в интерфейсе — они подставятся в консоль.
 */
class Tasks
{
    /**
     * @return array<int, array{id: string, number: int, title: string, goal: string, commands: array<int, string>, todo: array<int, string>, checkable: bool}>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'strings',
                'number' => 2,
                'title' => 'Строки',
                'goal' => 'SET сохраняет значение по ключу, GET читает его обратно.',
                'commands' => [
                    'SET student:name "Александр"',
                    'GET student:name',
                    'SET student:group "ПО-23"',
                    'GET student:group',
                ],
                'todo' => [
                    'Сохраните своё имя, группу, специальность и учебный модуль в четыре ключа.',
                    'Прочитайте каждое значение командой GET.',
                ],
                'checkable' => true,
            ],
            [
                'id' => 'ttl',
                'number' => 3,
                'title' => 'Время жизни (TTL)',
                'goal' => 'Ключ с EX живёт заданное число секунд, потом Redis удаляет его сам.',
                'commands' => [
                    'SET event:1:name "IT Conference" EX 120',
                    'TTL event:1:name',
                    'GET event:1:name',
                ],
                'todo' => [
                    'Создайте три ключа: на 30, 60 и 120 секунд.',
                    'Смотрите в панели ключей, как тает TTL и ключи исчезают.',
                ],
                'checkable' => true,
            ],
            [
                'id' => 'hash',
                'number' => 4,
                'title' => 'Hash',
                'goal' => 'Hash хранит запись из полей: удобно для карточки мероприятия.',
                'commands' => [
                    'HSET event:100 name "Хакатон 2026" city "Костанай" price 5000',
                    'HGET event:100 name',
                    'HGETALL event:100',
                    'HSET event:100 price 6000',
                    'HDEL event:100 city',
                ],
                'todo' => [
                    'Создайте три мероприятия с полями name, date, place и price.',
                    'Измените стоимость билета у одного из них.',
                ],
                'checkable' => true,
            ],
            [
                'id' => 'counters',
                'number' => 5,
                'title' => 'Счётчик просмотров',
                'goal' => 'INCR увеличивает число атомарно: два запроса одновременно не потеряют просмотр.',
                'commands' => [
                    'SET event:100:views 0',
                    'INCR event:100:views',
                    'GET event:100:views',
                    'INCRBY event:100:views 10',
                ],
                'todo' => [
                    'Заведите счётчики для трёх мероприятий и покрутите их.',
                    'Определите, у кого больше всего просмотров.',
                ],
                'checkable' => true,
            ],
            [
                'id' => 'sets',
                'number' => 6,
                'title' => 'Set и Sorted Set',
                'goal' => 'Set не хранит дубликаты, Sorted Set держит элементы упорядоченными по score.',
                'commands' => [
                    'SADD event:100:visitors 101 102 103',
                    'SADD event:100:visitors 104',
                    'SADD event:100:visitors 101',
                    'SMEMBERS event:100:visitors',
                    'SCARD event:100:visitors',
                    'ZADD events:rating 150 "event:100"',
                    'ZADD events:rating 250 "event:101"',
                    'ZADD events:rating 100 "event:102"',
                    'ZRANGE events:rating 0 -1 WITHSCORES',
                    'ZREVRANGE events:rating 0 -1 WITHSCORES',
                ],
                'todo' => [
                    'Соберите множество минимум из четырёх посетителей.',
                    'Постройте рейтинг из пяти мероприятий и найдите лидера.',
                ],
                'checkable' => true,
            ],
        ];
    }
}
