<?php

namespace Test;

use Che\SimpleLisp\HashMap;
use Che\SimpleLisp\Symbol;
use PHPUnit\Framework\TestCase;

class HashMapTest extends TestCase
{
    public function testSuccess(): void
    {
        $hm = new HashMap();
        self::assertFalse($hm->has(new Symbol('a')));
        self::assertFalse($hm->get(new Symbol('a')));

        $hm->put(new Symbol('a'), 123);
        self::assertTrue($hm->has(new Symbol('a')));
        self::assertEquals(123, $hm->get(new Symbol('a')));

        $hm->del(new Symbol('a'));
        self::assertFalse($hm->has(new Symbol('a')));
        self::assertFalse($hm->get(new Symbol('a')));
    }
}
