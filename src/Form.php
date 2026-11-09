<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

final readonly class Form implements \IteratorAggregate
{
    /**
     * @param list<mixed> $items
     */
    public function __construct(
        public array $items,
        public int $line,
        public int $column,
        public string $sourceLine = '',
    ) {}

    /**
     * @return list<mixed>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (mixed $item): mixed => $item instanceof self ? $item->toArray() : $item,
            $this->items,
        );
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->toArray());
    }
}
