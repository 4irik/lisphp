<?php

declare(strict_types=1);

namespace Test;

use Che\SimpleLisp\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function Che\SimpleLisp\Env\_defaultEnv;
use function PHPUnit\Framework\assertEquals;

class EnvTest extends TestCase
{
    #[DataProvider('envDP')]
    public function testSuccess(string $op, mixed $expected, ...$args): void
    {
        $env = _defaultEnv();
        self::assertEquals($expected, $env->get(new Symbol($op))($env, ...$args));
    }

    public function testInteropFunctions(): void
    {
        $env = _defaultEnv();
        self::assertEquals('9eb471eb', $env->get(new Symbol('php'))($env, 'hash', 'murmur3a', '123'));
    }

    #[Group('ignore')]
    public function testInteropNamedArgs(): void
    {
        // todo: именованные аргументы

        $env = _defaultEnv();
        self::assertEquals('9832d40f', $env->get(new Symbol('php'))($env, 'hash', 'murmur3a', '123', ['options', ['seed', 42]]));
    }

    public function testInteropCreateNewObject(): void
    {
        $env = _defaultEnv();
        self::assertInstanceOf(\DateTime::class, $env->get(new Symbol('php'))($env, [new Symbol('class'), '\DateTime'], 'new'));
    }

    public function testInteropCreateNewObjectWithArguments(): void
    {
        $env = _defaultEnv();
        $date = '22.01.2022 00:00:00';
        self::assertEquals(new \DateTime($date), $env->get(new Symbol('php'))($env, [new Symbol('class'), '\DateTime'], 'new', $date));
    }

    public function testInteropCallMethod(): void
    {
        $env = _defaultEnv();
        $format = 'd.m.Y H:i:s';
        self::assertEquals(
            \DateTime::createFromFormat($format, '23.01.2022 00:00:00'),
            $env->get(new Symbol('php'))($env, \DateTime::createFromFormat($format, '22.01.2022 00:00:00'), 'modify', '+1 day')
        );
    }

    public function testInteropCallStaticMethod(): void
    {
        $env = _defaultEnv();
        $format = 'd.m.Y H:i:s';
        $date = '22.01.2022 00:00:00';
        self::assertEquals(
            \DateTime::createFromFormat($format, $date),
            $env->get(new Symbol('php'))($env, [new Symbol('class'), '\DateTime'], 'createFromFormat', $format, $date)
        );
    }

    public function testInteropCallableObject(): void
    {
        $obj = new class () {
            public function __invoke(int $i): int
            {
                return $i + 10;
            }
        };

        $env = _defaultEnv();
        assertEquals(
            15,
            $env->get(new Symbol('php'))($env, $obj, 5)
        );
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

        yield 'concat' => [
            '++',
            "12",
            1, 2
        ];

        yield 'car' => [
            'car',
            1,
            [1,2,3]
        ];

        yield 'cdr' => [
            'cdr',
            [2, 3],
            [1, 2, 3]
        ];

        yield 'cons' => [
            'cons',
            [1, 2],
            1, 2
        ];

        yield 'cons with empty list' => [
            'cons',
            [1],
            1, []
        ];

        yield 'cons non empty list at head' => [
            'cons',
            [[1, 2], 3],
            [1, 2], 3
        ];

        yield 'cons non empty list at tail' => [
            'cons',
            [1, 2, 3],
            1, [2, 3]
        ];
    }
}
