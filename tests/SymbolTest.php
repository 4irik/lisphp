<?php

declare(strict_types=1);

namespace Test;

use Che\SimpleLisp\Symbol;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertEquals;

class SymbolTest extends TestCase
{
    public function testSuccess(): void
    {
        $s = new Symbol('test');
        self::assertEquals('test', $s->name);
        self::assertEquals('test', (string)$s);
    }
}
