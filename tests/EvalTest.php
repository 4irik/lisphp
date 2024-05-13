<?php

declare(strict_types=1);

namespace Test;

use Che\SimpleLisp\Lambda;
use Che\SimpleLisp\Macro;
use Che\SimpleLisp\HashMap;
use Che\SimpleLisp\Procedure;
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
        $env->put(new Symbol($eq = '=='), $procedure = new Procedure($eq, fn ($a, $b) => $a == $b));

        assertEquals(10, _eval(new Symbol('a'), $env));
        assertEquals($procedure, _eval(new Symbol('=='), $env));
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
        yield 'macro' => [new Macro(['macro', [], 10]), 'Macro'];
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
        assertEquals(2, _eval([
            new Symbol('eval'),
            [
                new Symbol('quote'),
                [
                    new Symbol('cond'), false, 1, 2
                ]
            ],
        ], new HashMap()));
    }

    public function testProcedureEmbedded(): void
    {
        $env = new HashMap();
        $env->put(new Symbol($sum = '+'), new Procedure($sum, fn (...$x) => reduce($x, fn ($a, $b) => $a + $b)));
        $env->put(new Symbol($qteq = '>='), new Procedure($qteq, fn ($a, $b) => $a >= $b));

        assertEquals(10, _eval([new Symbol('+'), 1, 2, 3, 4], $env));
        assertTrue(_eval([new Symbol('>='), 10, 9], $env));
    }

    public function testDeepEvaluation(): void
    {
        $env = new HashMap();
        $env->put(new Symbol($sum = '+'), new Procedure($sum, fn (...$x) => reduce($x, fn ($a, $b) => $a + $b)));

        assertEquals(10, _eval([new Symbol('+'), 1, 2, [new Symbol('+'), 3, 4]], $env));
    }

    public function testProcedureSymbolList(): void
    {
        $env = new HashMap();
        assertEquals([1, 2, 3], _eval([1, 2, 3], $env));

        $env->put(new Symbol('a'), 2);
        assertEquals([1, 2, 3], _eval([1, new Symbol('a'), 3], $env));
    }

    public function testLambda(): void
    {
        $env = new HashMap();
        $env->put(new Symbol($sum = '+'), new Procedure($sum, fn (...$x) => reduce($x, fn ($a, $b) => $a + $b)));

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
        $env->put(new Symbol($sum = '+'), new Procedure($sum, fn (...$x) => reduce($x, fn ($a, $b) => $a + $b)));

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

    public function testLambdaLocalEnv(): void
    {
        $env = new HashMap();

        _eval([
            [
                new Symbol('lambda'),
                [
                ],
                [
                    new Symbol('def'),
                    new Symbol('x'),
                    10,
                ]
            ],
        ], $env);

        assertFalse($env->has(new Symbol('x')));
        $lambdaEnv = $env->childList()->getIterator()->current();
        assertEquals(10, $lambdaEnv->get(new Symbol('x')));
    }

    public function testLambdaOOP(): void
    {
        $env = new HashMap();
        $env->put(new Symbol($sum = '+'), new Procedure($sum, fn (...$x) => reduce($x, fn ($a, $b) => $a + $b)));
        $env->put(new Symbol($eq = '='), new Procedure($eq, fn ($a, $b) => $a == $b));

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

    public function testMacroEval(): void
    {
        $env = new HashMap();
        $env->put(new Symbol($sum = '+'), new Procedure($sum, fn (...$x) => reduce($x, fn ($a, $b) => $a + $b)));
        $env->put(new Symbol('l1'), _handleLambda(['', [], 10], $env));

        _eval([
            new Symbol('def'),
            new Symbol('m1'),
            [
                new Symbol('macro'),
                [
                    new Symbol('x'),
                    new Symbol('y'),
                    new Symbol('z'),
                ],
                [
                    new Symbol('cond'),
                    new Symbol('x'),
                    new Symbol('y'),
                    [new Symbol('z')],
                ],
            ],
        ], $env);

        assertEquals(
            5,
            _eval([
                new Symbol('m1'),
                true,
                [new Symbol('+'), 2, 3],
                new Symbol('l1')
            ], $env)
        );
        assertEquals(0, $env->childList()->count());

        assertEquals(
            10,
            _eval([
                new Symbol('m1'),
                false,
                [new Symbol('+'), 3, 3],
                new Symbol('l1')
            ], $env)
        );
        assertEquals(1, $env->childList()->count());
    }

    public function testMacroEnv(): void
    {
        $env = new HashMap();
        $env->put(new Symbol($concat = '++'), new Procedure($concat, fn (...$x): string => reduce($x, fn ($a, $b): string => $a . $b)));

        _eval([
            new Symbol('def'),
            new Symbol('m1'),
            [
                new Symbol('macro'),
                [
                    new Symbol('x'),
                    new Symbol('y'),
                ],
                [
                    new Symbol('def'),
                    new Symbol('x'),
                    new Symbol('y'),
                ],
            ]
        ], $env);

        _eval([
            new Symbol('m1'),
            new Symbol('a'),
            10
        ], $env);
        assertEquals(10, $env->get(new Symbol('a')));
        assertEquals(0, $env->childList()->count());

        _eval([
            [
                new Symbol('lambda'),
                [
                    new Symbol('x'),
                    new Symbol('y'),
                ],
                [
                    new Symbol('m1'),
                    [new Symbol('++'), new Symbol('x'), ""],
                    new Symbol('y')
                ]
            ],
            new Symbol('b'),
            20
        ], $env);
        assertFalse($env->has(new Symbol('b')));
        $childList = $env->childList();
        assertEquals(1, $childList->count());
        $closureEnv = $childList->getIterator()->current();
        assertEquals(20, $closureEnv->get(new Symbol('b')));
    }

    public function testMacroExpand(): void
    {
        $env = new HashMap();

        _eval([
            new Symbol('def'),
            new Symbol('defmacro'),
            $defmacro = [
                new Symbol('macro'),
                [
                    new Symbol('name'),
                    new Symbol('args'),
                    new Symbol('body'),
                ],
                [
                    new Symbol('def'),
                    new Symbol('name'),
                    [
                        new Symbol('macro'),
                        new Symbol('args'),
                        new Symbol('body'),
                    ]
                ]
            ]
        ], $env);
        assertEquals(
            new Macro($defmacro),
            $env->get(new Symbol('defmacro'))
        );

        _eval([
            new Symbol('defmacro'),
            new Symbol('defn'),
            [
                new Symbol('name'),
                new Symbol('args'),
                new Symbol('body'),
            ],
            [
                new Symbol('def'),
                new Symbol('name'),
                [
                    new Symbol('lambda'),
                    new Symbol('args'),
                    new Symbol('body'),
                ]
            ]
        ], $env);
        assertEquals(
            new Macro([
                new Symbol('macro'),
                [
                    new Symbol('name'),
                    new Symbol('args'),
                    new Symbol('body'),
                ],
                [
                    new Symbol('def'),
                    new Symbol('name'),
                    [
                        new Symbol('lambda'),
                        new Symbol('args'),
                        new Symbol('body'),
                    ]
                ]
            ]),
            $env->get(new Symbol('defn'))
        );
    }
}
