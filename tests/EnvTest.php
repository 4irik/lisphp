<?php

declare(strict_types=1);

namespace Test;

use Che\SimpleLisp\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Che\SimpleLisp\Env\_defaultEnv;

class EnvTest extends TestCase
{
    #[DataProvider('envDP')]
    public function testSuccess(string $op, string|int|float|bool $expected, ...$args): void
    {
        $env = _defaultEnv();
        self::assertEquals($expected, $env->get(new Symbol($op))($env, ...$args));
    }

    public static function envDP(): iterable
    {
        yield '= true' => [
            '=',
            true,
            10,
            10
        ];

        yield '= false' => [
            '=',
            false,
            10,
            11
        ];

        yield '> true' => [
            '>',
            true,
            10,
            9
        ];

        yield '> false eq' => [
            '>',
            false,
            10,
            10
        ];

        yield '> false lt' => [
            '>',
            false,
            10,
            11
        ];

        yield '< true' => [
            '<',
            true,
            10,
            11
        ];

        yield '< false eq' => [
            '<',
            false,
            10,
            10
        ];

        yield '< false gt' => [
            '<',
            false,
            10,
            9
        ];

        yield '>= false' => [
            '>=',
            false,
            10,
            15
        ];

        yield '>= true eq' => [
            '>=',
            true,
            10,
            10
        ];

        yield '>= true gt' => [
            '>=',
            true,
            10,
            9
        ];

        yield '<= true lt' => [
            '<=',
            true,
            10,
            15
        ];

        yield '<= true eq' => [
            '<=',
            true,
            10,
            10
        ];

        yield '<= false' => [
            '<=',
            false,
            15,
            10
        ];

        yield 'not' => [
            'not',
            false,
            true
        ];

        yield 'abs' => [
            'abs',
            10,
            -10
        ];

        yield '+' => [
            '+',
            10,
            1, 2, 3, 4
        ];

        yield '-' => [
            '-',
            7,
            10, 2, 1
        ];

        yield '*' => [
            '*',
            24,
            2, 3, 4
        ];

        yield '/' => [
            '/',
            2,
            100, 10, 5
        ];

        yield 'max' => [
            'max',
            10,
            1, 10, 5
        ];

        yield 'min' => [
            'min',
            1,
            1, 10, 5
        ];

        yield '++' => [
            '++',
            'nav-10-t',
            'nav', '-', 10, '-', 't'
        ];

        yield 'mod' => [
            'mod',
            3,
            13, 5
        ];
    }
}
