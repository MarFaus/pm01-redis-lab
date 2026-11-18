<?php

namespace Tests\Unit;

use App\Lab\CommandRunner;
use App\Support\Fmt;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CommandRunnerTest extends TestCase
{
    public static function lines(): array
    {
        return [
            'простая команда' => ['GET student:name', ['GET', 'student:name']],
            'кавычки и кириллица' => ['SET student:name "Александр"', ['SET', 'student:name', 'Александр']],
            'пробел внутри кавычек' => ['HSET event:100 name "Хакатон 2026" price 5000', ['HSET', 'event:100', 'name', 'Хакатон 2026', 'price', '5000']],
            'одинарные кавычки' => ["SET a 'b c'", ['SET', 'a', 'b c']],
            'лишние пробелы' => ['  INCR   event:1:views  ', ['INCR', 'event:1:views']],
            'пустая строка в кавычках' => ['SET a ""', ['SET', 'a', '']],
            'экранированная кавычка' => ['SET a "say \"hi\""', ['SET', 'a', 'say "hi"']],
        ];
    }

    #[DataProvider('lines')]
    public function test_tokenize_splits_like_redis_cli(string $line, array $expected): void
    {
        $this->assertSame($expected, (new CommandRunner)->tokenize($line));
    }

    public function test_unclosed_quote_is_reported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CommandRunner)->tokenize('SET a "oops');
    }

    public function test_dangerous_commands_are_not_allowed(): void
    {
        foreach (['CONFIG', 'SELECT', 'FLUSHALL', 'SHUTDOWN', 'EVAL', 'DEBUG', 'MIGRATE'] as $cmd) {
            $this->assertNotContains($cmd, CommandRunner::ALLOWED, "{$cmd} не должна быть разрешена");
        }
    }

    public function test_format_matches_redis_cli_output(): void
    {
        $runner = new CommandRunner;

        $this->assertSame('(nil)', $runner->format(null));
        $this->assertSame('(integer) 3', $runner->format(3));
        $this->assertSame('OK', $runner->format('OK'));
        $this->assertSame('"Александр"', $runner->format('Александр'));
        $this->assertSame('(empty array)', $runner->format([]));
        $this->assertSame("1) \"event:101\"\n2) \"250\"", $runner->format(['event:101', '250']));
    }

    public function test_plural_forms(): void
    {
        $this->assertSame('просмотр', Fmt::plural(1, 'просмотр', 'просмотра', 'просмотров'));
        $this->assertSame('просмотра', Fmt::plural(3, 'просмотр', 'просмотра', 'просмотров'));
        $this->assertSame('просмотров', Fmt::plural(11, 'просмотр', 'просмотра', 'просмотров'));
        $this->assertSame('просмотр', Fmt::plural(21, 'просмотр', 'просмотра', 'просмотров'));
        $this->assertSame('просмотров', Fmt::plural(0, 'просмотр', 'просмотра', 'просмотров'));
    }

    public function test_money_and_clock(): void
    {
        $this->assertSame('Бесплатно', Fmt::money(0));
        $this->assertSame('12 000 ₸', Fmt::money(12000));
        $this->assertSame('4:57', Fmt::clock(297));
        $this->assertSame('—', Fmt::clock(null));
    }
}
