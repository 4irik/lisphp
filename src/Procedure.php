<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

use function Che\SimpleLisp\Eval\map;
use function Che\SimpleLisp\Eval\_eval;

final readonly class Procedure implements \IteratorAggregate
{
    public function __construct(private string $name, private \Closure $closure)
    {
    }

    public function __invoke(HashMapInterface $env, ...$args): mixed
    {
        $closure = $this->closure;
        return $closure(...iterator_to_array(map($args, fn ($item) => _eval($item, $env))));
    }

    #[\Override] public function getIterator(): \Traversable
    {
        return new \ArrayIterator([new Symbol('procedure'), new Symbol($this->name)]);
    }
}
