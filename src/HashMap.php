<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

class HashMap implements HashMapInterface
{
    private array $_map;

    public function has(Symbol $symbol): bool
    {
        return isset($this->_map[$symbol->name]);
    }

    public function get(Symbol $symbol): mixed
    {
        return $this->_map[$symbol->name] ?? false;
    }

    public function put(Symbol $symbol, mixed $value): void
    {
        $this->_map[$symbol->name] = $value;
    }

    public function del(Symbol $symbol): void
    {
        unset($this->_map[$symbol->name]);
    }
}
