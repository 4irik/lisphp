<?php

declare(strict_types=1);

namespace Test;

use Che\SimpleLisp\HashMap;
use Che\SimpleLisp\Symbol;
use PHPUnit\Framework\TestCase;

use function Che\SimpleLisp\Eval\_eval;
use function Che\SimpleLisp\Eval\map;
use function Che\SimpleLisp\Eval\reduce;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertTrue;

class EvalTest extends TestCase
{
    public function testReduce(): void
    {
        assertEquals(10, reduce([1,2,3,4], fn (int $a, int $b) => $a + $b));
    }

    public function testMap(): void
    {
        assertEquals([2, 4, 6], iterator_to_array(map([1,2,3], fn (int $x) => $x * 2)));
    }

    public function testEvalScalar(): void
    {
        $env = new HashMap();

        assertEquals(1, _eval(1, $env));
        assertEquals('str', _eval('str', $env));
    }

    public function testEvalSymbol(): void
    {
        $env = new HashMap();
        $env->put(new Symbol('a'), 10);
        $env->put(new Symbol('=='), fn ($a, $b) => $a == $b);

        assertEquals(10, _eval(new Symbol('a'), $env));
        assertEquals(fn ($a, $b) => $a == $b, _eval(new Symbol('=='), $env));
    }

    public function testCond(): void
    {
        $env = new HashMap();

        assertEquals(10, _eval([new Symbol('cond'), true, 10, 20], $env));
        assertEquals(20, _eval([new Symbol('cond'), false, 10, 20], $env));
    }

    public function testDefIllegalArgumentsCount()
    {
        self::expectExceptionObject(new \Exception('"def" required an even number of arguments, received: 3'));

        _eval([new Symbol('def'), new Symbol('a'), 1, new Symbol('b')], new HashMap());
    }

    public function testDef(): void
    {
        $env = new HashMap();

        assertFalse($env->has(new Symbol('a')));
        assertFalse($env->has(new Symbol('b')));

        _eval([new Symbol('def'), new Symbol('a'), 1, new Symbol('b'), [new Symbol('cond'), true, 10 ,20]], $env);

        assertEquals(1, $env->get(new Symbol('a')));
        assertEquals(10, $env->get(new Symbol('b')));
    }

    public function testDefRewriteVariable(): void
    {
        $env = new HashMap();

        assertFalse($env->has(new Symbol('a')));

        _eval([new Symbol('def'), new Symbol('a'), 1], $env);
        assertEquals(1, $env->get(new Symbol('a')));

        _eval([new Symbol('def'), new Symbol('a'), 2], $env);
        assertEquals(2, $env->get(new Symbol('a')));
    }

    public function testDo(): void
    {
        $env = new HashMap();

        self::assertFalse($env->has(new Symbol('a')));
        self::assertFalse($env->has(new Symbol('b')));

        assertEquals(10, _eval([new Symbol('do'), [new Symbol('def'), 'a', 1], [new Symbol('cond'), true, 10 ,20]], $env));
        assertEquals(1, $env->get(new Symbol('a')));
    }

    public function testQuote(): void
    {
        assertEquals($expect = [new Symbol('cond'), true, 1, 2], _eval([new Symbol('quote'), $expect], new HashMap()));
    }

    public function testEval(): void
    {
        assertEquals(1, _eval([new Symbol('eval'), 1], new HashMap()));
        assertEquals(1, _eval([
            new Symbol('eval'),
            [
                new Symbol('quote'),
                [
                    new Symbol('cond'), true, 1, 2
                ]
            ],
        ], new HashMap()));
    }

    public function testProcedureEmbedded(): void
    {
        $env = new HashMap();
        $env->put(new Symbol('+'), fn (...$x) => reduce($x, fn ($a, $b) => $a + $b));
        $env->put(new Symbol('>='), fn ($a, $b) => $a >= $b);

        assertEquals(10, _eval([new Symbol('+'), 1, 2, 3, 4], $env));
        assertTrue(_eval([new Symbol('>='), 10, 9], $env));
    }

    public function testProcedureSymbolList(): void
    {
        $env = new HashMap();
        assertEquals([1, 2, 3], _eval([1, 2, 3], $env));
    }
}
