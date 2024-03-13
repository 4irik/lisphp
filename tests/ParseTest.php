<?php

declare(strict_types=1);

namespace Test;

use Che\SimpleLisp\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Che\SimpleLisp\Parse\_atom;
use function Che\SimpleLisp\Parse\parseTokens;
use function Che\SimpleLisp\Parse\_parseTokens;
use function Che\SimpleLisp\Parse\tokenize;
use function PHPUnit\Framework\assertEquals;

class ParseTest extends TestCase
{
    public function testTokenizer(): void
    {
        self::assertEquals(['(', '+', '1', '2', ')'], tokenize('(+ 1 2)'));
        self::assertEquals(['"abc"', '"def"'], tokenize('"abc"   "def"'));
        self::assertEquals(['\'', 'abc'], tokenize('\'abc'));
        self::assertEquals(['(', '1', '2', ')', '(', 'def', 'b', '2', ')'], tokenize("(1 2)\n\n\n(def b 2)\n\n"));
        self::assertEquals(['(', 'def', 'test', '1', ')'], tokenize("(def test\n 1)"));
        self::assertEquals(['(', 'def', 'a', '" "', ')'], tokenize('(def a " ")'));
        self::assertEquals(['(', 'def', 'a', "\"\n \"", ')'], tokenize("(def a \"\n \")"));
        self::assertEquals(['(', 'def', 'a', '""', ')'], tokenize('(def a "")'));
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

    public function testParseQuote(): void
    {
        assertEquals([
            new Symbol('quote'),
            new Symbol('abc'),
        ], parseTokens(['(', '\'', 'abc', ')']));

        assertEquals([
            new Symbol('quote'),
            [
                new Symbol('-'),
                10,
                1.3
            ]
        ], parseTokens(['(', '\'', '(', '-', '10', '1.3', ')', ')']));
    }

    public static function atomDP(): iterable
    {
        yield 'int' => [
            '13',
            13
        ];

        yield 'float' => [
            '13.1',
            13.1
        ];

        yield 'True' => [
            '#t',
            true
        ];

        yield 'false' => [
            '#f',
            false
        ];

        yield 'string' => [
            '"some string"',
            'some string'
        ];

        yield 'empty string' => [
            '""',
            ''
        ];

        yield 'quote' => [
            '\'',
            new Symbol('quote')
        ];

        yield 'Symbol' => [
            'def',
            new Symbol('def')
        ];
    }
}
