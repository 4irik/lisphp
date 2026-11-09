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
        return array_map(self::data(...), $this->items);
    }

    public static function data(mixed $value): mixed
    {
        if ($value instanceof Located) {
            return self::data($value->value);
        }

        if ($value instanceof self) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return array_map(self::data(...), $value);
        }

        return $value;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->toArray());
    }
}
