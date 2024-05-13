<?php

namespace Test;

use Che\SimpleLisp\HashMap;
use Che\SimpleLisp\Symbol;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertSame;

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

    public function testHierarhy(): void
    {
        $hm_1 = new HashMap();
        $hm_1->put(new Symbol('a'), 10);
        $hm_1->put(new Symbol('b'), 20);

        $hm_2 = new HashMap($hm_1);
        $hm_2->put(new Symbol('a'), 11);
        $hm_2->put(new Symbol('c'), 13);

        assertEquals(10, $hm_1->get(new Symbol('a')));
        assertEquals(11, $hm_2->get(new Symbol('a')));
        assertFalse($hm_1->has(new Symbol('c')));
        assertFalse($hm_2->has(new Symbol('b')));

        assertSame($hm_1, $hm_2->parent());
        assertSame($hm_2, $hm_1->childList()[$hm_2]);
    }
}
