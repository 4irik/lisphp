<?php

namespace Che\SimpleLisp;

interface HashMapInterface
{
    public function parent(): ?self;

    /**
     * @return iterable<self>
     */
    public function childList(): iterable;

    public function has(Symbol $symbol): bool;

    public function get(Symbol $symbol): mixed;

    public function put(Symbol $symbol, mixed $value): void;

    public function del(Symbol $symbol): void;
}
