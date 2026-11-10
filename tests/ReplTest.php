<?php

declare(strict_types=1);

namespace Test;

use Che\SimpleLisp\Parse\SyntaxError;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/repl.php';

class ReplTest extends TestCase
{
    public function testParensInsideStringDoNotCount(): void
    {
        $counter = new \BraceCounter();
        \consumeLine($counter, '(print "hello (world)")', 1);
        self::assertSame(0, $counter->count());
        self::assertFalse($counter->inString());
    }

    public function testParensInsideCommentDoNotCount(): void
    {
        $counter = new \BraceCounter();
        \consumeLine($counter, '(+ 1 2) ; )', 1);
        self::assertSame(0, $counter->count());
        self::assertFalse($counter->inString());
    }

    public function testStringContinuesAcrossLines(): void
    {
        $counter = new \BraceCounter();
        \consumeLine($counter, '"hello (', 1);
        self::assertSame(0, $counter->count());
        self::assertTrue($counter->inString());

        \consumeLine($counter, ')"', 2);
        self::assertSame(0, $counter->count());
        self::assertFalse($counter->inString());
    }

    public function testUnexpectedParen(): void
    {
        try {
            \consumeLine(new \BraceCounter(), ')', 1);
            self::fail('Unexpected ")" must be reported');
        } catch (SyntaxError $error) {
            self::assertSame('Unexpected ")"', $error->getMessage());
            self::assertSame(1, $error->atLine);
            self::assertSame(1, $error->atColumn);
        }
    }
}
