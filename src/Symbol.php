<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

final readonly class Symbol implements \Stringable
{
    public function __construct(public string $name)
    {
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
