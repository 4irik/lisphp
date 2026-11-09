<?php

declare(strict_types=1);

namespace Test;

use Che\SimpleLisp\HashMapInterface;
use Che\SimpleLisp\RuntimeError;
use PHPUnit\Framework\TestCase;

use function Che\SimpleLisp\Env\_defaultEnv;
use function Che\SimpleLisp\Eval\_eval;
use function Che\SimpleLisp\Parse\formatRuntimeError;
use function Che\SimpleLisp\Parse\parseTokens;
use function Che\SimpleLisp\Parse\tokenize;

function evalSource(HashMapInterface $env, string $source): mixed
{
    $tokens = new \SplDoublyLinkedList();
    foreach (tokenize($source) as $token) {
        $tokens->push($token);
    }

    $result = null;
    while (!$tokens->isEmpty()) {
        $result = _eval(parseTokens($tokens, $source), $env);
    }

    return $result;
}

class RuntimeErrorTest extends TestCase
{
    public function testDivisionPointsAtTheForm(): void
    {
        try {
            evalSource(_defaultEnv(), '(/ 1 0)');
            self::fail('Division by zero must be reported');
        } catch (RuntimeError $error) {
            self::assertSame('Division by zero', $error->getMessage());
            self::assertSame(1, $error->atLine);
            self::assertSame(1, $error->atColumn);
            self::assertInstanceOf(\DivisionByZeroError::class, $error->getPrevious());
            self::assertSame("1:1: Division by zero\n(/ 1 0)\n^", formatRuntimeError($error));
        }
    }

    public function testNestedFormKeepsTheInnerLocation(): void
    {
        try {
            evalSource(_defaultEnv(), '(do (/ 1 0))');
            self::fail('Division by zero must be reported');
        } catch (RuntimeError $error) {
            self::assertSame(1, $error->atLine);
            self::assertSame(5, $error->atColumn);
            self::assertSame("1:5: Division by zero\n(do (/ 1 0))\n    ^", formatRuntimeError($error));
        }
    }

    public function testLambdaBodyPointsAtItsDefinition(): void
    {
        $source = "(def f (lambda (n)\n  (/ n 0)))\n(f 1)";

        try {
            evalSource(_defaultEnv(), $source);
            self::fail('Division by zero must be reported');
        } catch (RuntimeError $error) {
            self::assertSame(2, $error->atLine);
            self::assertSame(3, $error->atColumn);
            self::assertSame("2:3: Division by zero\n  (/ n 0)))\n  ^", formatRuntimeError($error));
        }
    }

    public function testDefnBodyKeepsTheWrittenForm(): void
    {
        $env = _defaultEnv();
        $stdLib = file_get_contents(dirname(__DIR__) . '/src/std_lib.lisp');
        self::assertIsString($stdLib);
        evalSource($env, $stdLib);

        $source = "(defn f (n)\n  (/ n 0))\n(f 1)";

        try {
            evalSource($env, $source);
            self::fail('Division by zero must be reported');
        } catch (RuntimeError $error) {
            self::assertSame(2, $error->atLine);
            self::assertSame(3, $error->atColumn);
            self::assertSame("2:3: Division by zero\n  (/ n 0))\n  ^", formatRuntimeError($error));
        }
    }
}
