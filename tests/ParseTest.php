<?php

declare(strict_types=1);

namespace Test;

use Che\SimpleLisp\Parse\SyntaxError;
use Che\SimpleLisp\Parse\Token;
use Che\SimpleLisp\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Che\SimpleLisp\Parse\_atom;
use function Che\SimpleLisp\Parse\_parseTokens;
use function Che\SimpleLisp\Parse\formatSyntaxError;
use function Che\SimpleLisp\Parse\parseTokens;
use function Che\SimpleLisp\Parse\tokenize;
use function PHPUnit\Framework\assertEquals;

/**
 * @param list<Token> $tokens
 * @return list<string>
 */
function tokenTexts(array $tokens): array
{
    return array_map(static fn(Token $token): string => $token->text, $tokens);
}

function parseSource(string $source): mixed
{
    $tokens = new \SplDoublyLinkedList();
    foreach (tokenize($source) as $token) {
        $tokens->push($token);
    }

    $value = null;
    while (!$tokens->isEmpty()) {
        $value = parseTokens($tokens, $source);
    }

    return $value;
}

class ParseTest extends TestCase
{
    #[DataProvider('tokenDP')]
    public function testTokenizer(array $expectedList, string $program): void
    {
        self::assertEquals($expectedList, tokenTexts(tokenize($program)));
    }

    public function testSkipCommentAtStartLine(): void
    {
        $command = <<<EON
            ; some comment
            ;another text
            (+ 0 1)
            EON;

        self::assertEquals(['(', '+', '0', '1', ')'], tokenTexts(tokenize($command)));
    }

    public function testTokenizeZero(): void
    {
        self::assertEquals(['(', '+', '1', '0', ')'], tokenTexts(tokenize('(+ 1 0)')));
    }

    public function testTokenizeFloatingPoint(): void
    {
        assertEquals(['(', '+', '1', '1.1', ')'], tokenTexts(tokenize('(+ 1 1.1)')));
    }

    #[DataProvider('atomDP')]
    public function testAtom(string $token, mixed $expected): void
    {
        self::assertEquals($expected, _atom($token));
    }

    public function testParseTokensEmpty(): void
    {
        try {
            _parseTokens(new \SplDoublyLinkedList());
            self::fail('Unexpected EOF must be reported');
        } catch (SyntaxError $error) {
            self::assertSame('Unexpected EOF', $error->getMessage());
        }
    }

    public function testParseTokenSyntaxErr(): void
    {
        try {
            parseTokens([')']);
            self::fail('Unexpected ")" must be reported');
        } catch (SyntaxError $error) {
            self::assertSame('Unexpected ")"', $error->getMessage());
        }
    }

    public function testUnexpectedCharacterPointsAtColumn(): void
    {
        try {
            tokenize('(+ 1 @)');
            self::fail('An unexpected character must be reported');
        } catch (SyntaxError $error) {
            self::assertSame('Unexpected character "@"', $error->getMessage());
            self::assertSame(1, $error->atLine);
            self::assertSame(6, $error->atColumn);
            self::assertSame("1:6: Unexpected character \"@\"\n(+ 1 @)\n     ^", formatSyntaxError($error, '(+ 1 @)'));
        }
    }

    public function testExtraParenPointsAtColumn(): void
    {
        $source = '(+ 1 2))';
        try {
            parseSource($source);
            self::fail('An extra closing paren must be reported');
        } catch (SyntaxError $error) {
            self::assertSame('Unexpected ")"', $error->getMessage());
            self::assertSame(1, $error->atLine);
            self::assertSame(8, $error->atColumn);
        }
    }

    public function testUnclosedFormPointsAtEndOfSecondLine(): void
    {
        $source = "(+ 1\n2";
        try {
            parseSource($source);
            self::fail('An unclosed form must be reported');
        } catch (SyntaxError $error) {
            self::assertSame('Unexpected EOF', $error->getMessage());
            self::assertSame(2, $error->atLine);
            self::assertSame(2, $error->atColumn);
        }
    }

    public function testExtraParenOnSecondLine(): void
    {
        $source = "(+ 1\n2))";
        try {
            parseSource($source);
            self::fail('An extra closing paren must be reported');
        } catch (SyntaxError $error) {
            self::assertSame('Unexpected ")"', $error->getMessage());
            self::assertSame(2, $error->atLine);
            self::assertSame(3, $error->atColumn);
        }
    }

    public function testParseTokenSyntaxAtom(): void
    {
        assertEquals('test 123', parseTokens(['"test 123"']));
    }

    public function testParseTokenList(): void
    {
        assertEquals(
            [
                new Symbol('+'),
                1,
                [
                    new Symbol('-'),
                    10,
                    1.3,
                ],
            ],
            parseTokens(['(', '+', '1', '(', '-', '10', '1.3', ')', ')']),
        );
    }

    public function testParseShortQuote(): void
    {
        assertEquals([
            new Symbol('quote'),
            new Symbol('abc'),
        ], parseTokens(['\'', 'abc']));

        assertEquals(
            [
                new Symbol('quote'),
                [
                    new Symbol('-'),
                    10,
                    1.3,
                ],
            ],
            parseTokens(['\'', '(', '-', '10', '1.3', ')']),
        );
    }

    public static function tokenDP(): iterable
    {
        yield [['(', '+', '1', '2', ')'], '(+ 1 2)'];
        yield [['"abc"', '"def"'], '"abc"   "def"'];
        yield [['\'', 'abc'], '\'abc'];
        yield [['\'', 'hash', '\''], "'hash'"];
        yield [['(', '1', '2', ')', '(', 'def', 'b', '2', ')'], "(1 2)\n\n\n(def b 2)\n\n"];
        yield [['(', 'def', 'test', '1', ')'], "(def test\n 1)"];
        yield [['(', 'def', 'a', '" "', ')'], '(def a " ")'];
        yield [['(', 'def', 'a', "\"\n \"", ')'], "(def a \"\n \")"];
        yield [['(', 'def', 'a', '""', ')'], '(def a "")'];
        yield [['(', 'def', 'a1-b2', '2', ')'], '(def a1-b2 2)'];
        yield [['(', '-', '1', '2', ')'], '(- 1 2)'];
        yield [['(', 'set!', 'a', '2', ')'], '(set! a 2)'];
        yield [['(', 'def', 'a', '"(a)*b"', ')'], '(def a "(a)*b")'];
        yield [['nil?'], 'nil?'];
        yield [['nil!'], 'nil!'];
        yield [['/n'], '/n'];
        yield [['\n'], '\n'];
        yield [['\='], '\='];
        yield [['/='], '/='];
        yield [['!='], '!='];
        yield [['?='], '?='];
        yield [['test-1'], 'test-1'];
        yield [['test_1'], 'test_1'];

        $newLine = '
';
        yield [['(', 'def', 'n', "\"\n\"", ')'], '(def n "' . $newLine . '")'];
    }

    public static function atomDP(): iterable
    {
        yield 'Int' => [
            '13',
            13,
        ];

        yield 'Float' => [
            '13.1',
            13.1,
        ];

        yield 'True' => [
            'true',
            true,
        ];

        yield 'False' => [
            'false',
            false,
        ];

        yield 'String' => [
            '"some string"',
            'some string',
        ];

        yield 'Empty string' => [
            '""',
            '',
        ];

        yield 'Quote short' => [
            '\'',
            new Symbol('quote'),
        ];

        yield 'Symbol' => [
            'def',
            new Symbol('def'),
        ];
    }
}
