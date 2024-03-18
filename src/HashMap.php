<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

class HashMap implements HashMapInterface, \IteratorAggregate
{
    private array $_map = [];

    private ?self $parent = null;

    /**
     * @var \WeakMap<self>
     */
    private \WeakMap $child;

    public function __construct(?self $parent = null)
    {
        $this->parent = $parent;
        $this->child = new \WeakMap();
        if($parent !== null) {
            $parent->child[$this] = $this;
        }
    }

    public function parent(): ?self
    {
        return $this->parent;
    }

    public function childList(): \WeakMap
    {
        return $this->child;
    }

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

    #[\Override] public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->_map);
    }
}
