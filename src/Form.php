<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

/**
 * @implements \IteratorAggregate<int, mixed>
 */
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

    public static function data(mixed $value): object|array|int|float|string|bool|null
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

        if (is_object($value) || is_scalar($value) || $value === null) {
            return $value;
        }

        throw new \TypeError('Unexpected value');
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->toArray());
    }
}
