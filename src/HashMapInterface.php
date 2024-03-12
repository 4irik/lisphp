<?php

namespace Che\SimpleLisp;

interface HashMapInterface
{
    public function has(Symbol $symbol): bool;

    public function get(Symbol $symbol): mixed;

    public function put(Symbol $symbol, mixed $value): void;

    public function del(Symbol $symbol): void;
}
