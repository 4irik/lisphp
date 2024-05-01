<?php

declare(strict_types=1);

namespace Test;

use Che\SimpleLisp\Eval\Lambda;
use Che\SimpleLisp\Eval\Macro;
use Che\SimpleLisp\HashMap;
use Che\SimpleLisp\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Che\SimpleLisp\Eval\_eval;
use function Che\SimpleLisp\Eval\_handleLambda;
use function Che\SimpleLisp\Eval\_typeOf;
use function Che\SimpleLisp\Eval\envForSymbol;
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
        assertEquals([], _eval([new Symbol('cond'), false, 10], $env));
        assertEquals([], _eval([new Symbol('cond'), true, [], 20], $env));
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

    public function testDefHierarchy()
    {
        $env1 = new HashMap();
        _eval([new Symbol('def'), new Symbol('a'), 'a1_1'], $env1);

        $env2 = new HashMap($env1);
        _eval([new Symbol('def'), new Symbol('b'), 'b2_1'], $env2);

        assertTrue($env1->has(new Symbol('a')));
        assertFalse($env1->has(new Symbol('b')));

        assertFalse($env2->has(new Symbol('a')));
        assertTrue($env2->has(new Symbol('b')));

        _eval([new Symbol('def'), new Symbol('a'), 'a2_1'], $env2);

        // состав переменных окружения
        assertEquals('a1_1', $env1->get(new Symbol('a')));
        assertFalse($env1->has(new Symbol('b')));
        assertEquals('a2_1', $env2->get(new Symbol('a')));
        assertEquals('b2_1', $env2->get(new Symbol('b')));

        // извлечение данных из переменных окружения
        assertEquals('a1_1', _eval(new Symbol('a'), $env1));
        assertEquals(new Symbol('b'), _eval(new Symbol('b'), $env1));
        assertEquals('a2_1', _eval(new Symbol('a'), $env2));
        assertEquals('b2_1', _eval(new Symbol('b'), $env2));
    }

    public function testSet(): void
    {
        $env1 = new HashMap();
        _eval([new Symbol('def'), new Symbol('a'), 'a1_1'], $env1);

        $env2 = new HashMap($env1);
        _eval([new Symbol('def'), new Symbol('b'), 'b2_1'], $env2);

        assertTrue($env1->has(new Symbol('a')));
        assertFalse($env1->has(new Symbol('b')));

        assertFalse($env2->has(new Symbol('a')));
        assertTrue($env2->has(new Symbol('b')));

        _eval([new Symbol('set!'), new Symbol('a'), 'a2_1'], $env2);
        _eval([new Symbol('set!'), new Symbol('b'), 'b2_2'], $env2);

        // состав переменных окружения
        assertFalse($env1->has(new Symbol('b')));
        assertFalse($env2->has(new Symbol('a')));
        assertEquals('a2_1', $env1->get(new Symbol('a')));
        assertEquals('b2_2', $env2->get(new Symbol('b')));

        // извлечение данных из переменных окружения
        assertEquals('a2_1', _eval(new Symbol('a'), $env1));
        assertEquals(new Symbol('b'), _eval(new Symbol('b'), $env1));
        assertEquals('a2_1', _eval(new Symbol('a'), $env2));
        assertEquals('b2_2', _eval(new Symbol('b'), $env2));
    }

    #[DataProvider("typeOfDP")]
    public function testTypeOf(mixed $value, string $expectedType): void
    {
        assertEquals($expectedType, _typeOf($value));
    }

    public static function typeOfDP(): iterable
    {
        yield 'int' => [123, 'int'];
        yield 'float' => [123.1, 'float'];
        yield 'string' => ["abc", 'str'];
        yield 'boolean' => [true, 'bool'];
        yield 'list' => [[1,2,3], 'ConsList'];
        yield 'Symbol' => [new Symbol('a'), 'Symbol'];
        yield 'lambda' => [new Lambda(['lambda', [], 10], new HashMap()), 'Lambda'];
        yield 'macro' => [new Macro(['macro', [], 10], new HashMap()), 'Macro'];
    }

    public function testDo(): void
    {
        $env = new HashMap();

        assertFalse($env->has(new Symbol('a')));
        assertFalse($env->has(new Symbol('b')));

        assertEquals(10, _eval([new Symbol('do'), [new Symbol('def'), 'a', 1], [new Symbol('cond'), true, 10 ,20]], $env));
        assertEquals(1, $env->get(new Symbol('a')));
        assertEquals(5, _eval([new Symbol('do'), 2, 3, 5], $env));
    }

    public function testEnvForSymbol(): void
    {
        $env1 = new HashMap();
        $env1->put(new Symbol('a'), 'a1_1');
        $env1->put(new Symbol('b'), 'b1_1');

        $env2 = new HashMap($env1);
        $env2->put(new Symbol('a'), 'a2_1');

        assertEquals($env2, envForSymbol(new Symbol('a'), $env2));
        assertEquals($env1, envForSymbol(new Symbol('b'), $env2));
    }

    public function testQuote(): void
    {
        assertEquals($expect = [new Symbol('cond'), true, 1, 2], _eval([new Symbol('quote'), $expect], new HashMap()));
    }

    public function testEvalEmptyList(): void
    {
        assertEquals([], _eval([], new HashMap()));
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

    public function testLambda(): void
    {
        $env = new HashMap();
        $env->put(new Symbol('+'), fn (...$x) => reduce($x, fn ($a, $b) => $a + $b));

        assertEquals(10, _eval([
            [
                new Symbol('lambda'),
                [
                    new Symbol('x'),
                    new Symbol('y'),
                    new Symbol('z'),
                ],
                [
                    new Symbol('+'),
                    new Symbol('x'),
                    new Symbol('y'),
                    new Symbol('z'),
                ]
            ],
            2,3,5
        ], $env));
        assertFalse($env->has(new Symbol('x')));
        assertFalse($env->has(new Symbol('y')));
        assertFalse($env->has(new Symbol('z')));

        assertEquals(15, _eval([
            [
                new Symbol('lambda'),
                [
                ],
                15
            ],
        ], $env));
    }

    public function testLambdaGlobalEnv(): void
    {
        $env = new HashMap();
        $env->put(new Symbol('+'), fn (...$x) => reduce($x, fn ($a, $b) => $a + $b));

        _eval([
            new Symbol('def'),
            new Symbol('test'),
            [
                new Symbol('lambda'),
                [
                    new Symbol('x'),
                    new Symbol('y'),
                ],
                [
                    new Symbol('+'),
                    new Symbol('x'),
                    new Symbol('y'),
                    new Symbol('z'),
                ]
            ]
        ], $env);

        _eval([new Symbol('def'), new Symbol('z'), 10], $env);
        assertEquals(15, _eval([new Symbol('test'), 2, 3], $env));

        _eval([new Symbol('def'), new Symbol('z'), 15], $env);
        assertEquals(22, _eval([new Symbol('test'), 3, 4], $env));
    }

    public function testLambdaOOP(): void
    {
        $env = new HashMap();
        $env->put(new Symbol('+'), fn (...$x) => reduce($x, fn ($a, $b) => $a + $b));
        $env->put(new Symbol('='), fn ($a, $b) => $a == $b);

        _eval([
            new Symbol('def'),
            new Symbol('obj'),
            [
                new Symbol('lambda'),
                [
                    new Symbol('x')
                ],
                [
                    new Symbol('do'),
                    [
                        new Symbol('def'),
                        new Symbol('get'),
                        [
                            new Symbol('lambda'),
                            [],
                            new Symbol('x')
                        ],
                    ],
                    [
                        new Symbol('def'),
                        new Symbol('add'),
                        [
                            new Symbol('lambda'),
                            [
                                new Symbol('y'),
                            ],
                            [
                                new Symbol('do'),
                                [
                                    new Symbol('set!'),
                                    new Symbol('x'),
                                    [
                                        new Symbol('+'),
                                        new Symbol('x'),
                                        new Symbol('y')
                                    ]
                                ],
                                new Symbol('x'),
                            ]
                        ]
                    ],
                    [
                        new Symbol('lambda'),
                        [
                            new Symbol('cmd')
                        ],
                        [
                            new Symbol('cond'),
                            [
                                new Symbol('='),
                                new Symbol('cmd'),
                                [
                                    new Symbol('quote'),
                                    new Symbol('add')
                                ]
                            ],
                            new Symbol('add'),
                            new Symbol('get')
                        ]
                    ],
                ]
            ]
        ], $env);

        _eval([
            new Symbol('def'),
            new Symbol('o1'),
            [new Symbol('obj'), 100]
        ], $env);

        assertEquals(100, _eval([
            [new Symbol('o1'), [new Symbol('quote'), new Symbol('get')]],
            ""
        ], $env));


        assertEquals(110, _eval([
            [new Symbol('o1'), "add"],
            10
        ], $env));

        assertEquals(110, _eval([
            [new Symbol('o1'), [new Symbol('quote'), new Symbol('get')]],
            ""
        ], $env));
    }

    public function testMacro(): void
    {
        $env = new HashMap();
        $env->put(new Symbol('+'), fn (...$x) => reduce($x, fn ($a, $b) => $a + $b));
        $env->put(new Symbol('l1'), _handleLambda(['', [], 10], $env));

        assertEquals(
            5,
            _eval([
                [
                    new Symbol('macro'),
                    [
                        new Symbol('x'),
                        new Symbol('y'),
                    ],
                    [
                        new Symbol('cond'),
                        true,
                        new Symbol('x'),
                        [new Symbol('y')],
                    ],
                ],
                [new Symbol('+'), 2, 3], new Symbol('l1')
            ], $env)
        );
        assertEquals(0, $env->childList()->count());

        assertEquals(
            10,
            _eval([
                [
                    new Symbol('macro'),
                    [
                        new Symbol('x'),
                        new Symbol('y'),
                    ],
                    [
                        new Symbol('cond'),
                        false,
                        new Symbol('x'),
                        [new Symbol('y')],
                    ],
                ],
                [new Symbol('+'), 3, 3],
                new Symbol('l1')
            ], $env)
        );
        assertEquals(1, $env->childList()->count());
    }
}
