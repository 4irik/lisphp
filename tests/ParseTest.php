<?php

declare(strict_types=1);

namespace Test;

use Che\SimpleLisp\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function Che\SimpleLisp\Parse\_atom;
use function Che\SimpleLisp\Parse\parseTokens;
use function Che\SimpleLisp\Parse\_parseTokens;
use function Che\SimpleLisp\Parse\tokenize;
use function PHPUnit\Framework\assertEquals;

class ParseTest extends TestCase
{
    #[DataProvider("tokenDP")]
    public function testTokenizer(array $expectedList, string $program): void
    {
        self::assertEquals($expectedList, tokenize($program));
    }

    public function testSkipCommentAtStartLine(): void
    {
        $command = <<<EON
; some comment
;another text
(+ 0 1)
EON;

        self::assertEquals(['(', '+', '0', '1', ')'], tokenize($command));
    }

    public function testTokenizeZero(): void
    {
        self::assertEquals(['(', '+', '1', '0', ')'], tokenize('(+ 1 0)'));
    }

    #[DataProvider('atomDP')]
    public function testAtom(string $token, mixed $expected): void
    {
        self::assertEquals($expected, _atom($token));
    }

    public function testParseTokensEmpty(): void
    {
        self::expectExceptionObject(new \Exception('Unexpected EOF'));

        _parseTokens(new \SplDoublyLinkedList());
    }

    public function testParseTokenSyntaxErr(): void
    {
        self::expectExceptionObject(new \Exception('Unexpected ")"'));

        parseTokens([')']);
    }

    public function testParseTokenSyntaxAtom(): void
    {
        assertEquals('test 123', parseTokens(['"test 123"']));
    }

    public function testParseTokenList(): void
    {
        assertEquals([
            new Symbol('+'),
            1,
            [
                new Symbol('-'),
                10,
                1.3
            ]
        ], parseTokens(['(', '+', '1', '(', '-', '10', '1.3', ')', ')']));
    }

    #[Group("ignore")]
    public function testParseQuote(): void
    {
        assertEquals([
            new Symbol('quote'),
            [new Symbol('abc')],
        ], parseTokens(['(', '\'', 'abc', ')']));

        assertEquals([
            new Symbol('quote'),
            [
                [
                    new Symbol('-'),
                    10,
                    1.3
                ]
            ]
        ], parseTokens(['(', '\'', '(', '-', '10', '1.3', ')', ')']));
    }

    public static function tokenDP(): iterable
    {
        yield [['(', '+', '1', '2', ')'], '(+ 1 2)'];
        yield [['"abc"', '"def"'], '"abc"   "def"'];
        yield [['\'', 'abc'], '\'abc'];
        yield [['(', '1', '2', ')', '(', 'def', 'b', '2', ')'], "(1 2)\n\n\n(def b 2)\n\n"];
        yield [['(', 'def', 'test', '1', ')'], "(def test\n 1)"];
        yield [['(', 'def', 'a', '" "', ')'], '(def a " ")'];
        yield [['(', 'def', 'a', "\"\n \"", ')'], "(def a \"\n \")"];
        yield [['(', 'def', 'a', '""', ')'], '(def a "")'];
        yield [['(', 'def', 'a1_b2', '2', ')'], '(def a1-b2 2)'];
        yield [['(', '-', '1', '2', ')'], '(- 1 2)'];
        yield [['(', 'set!', 'a', '2', ')'], '(set! a 2)'];
        yield [['(', 'def', 'a', '"(a)*b"', ')'], '(def a "(a)*b")'];
    }

    public static function atomDP(): iterable
    {
        yield 'Int' => [
            '13',
            13
        ];

        yield 'Float' => [
            '13.1',
            13.1
        ];

        yield 'True' => [
            'true',
            true
        ];

        yield 'False' => [
            'false',
            false
        ];

        yield 'String' => [
            '"some string"',
            'some string'
        ];

        yield 'Empty string' => [
            '""',
            ''
        ];

        yield 'Quote short' => [
            '\'',
            new Symbol('quote')
        ];

        yield 'Symbol' => [
            'def',
            new Symbol('def')
        ];
    }
}
