<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

final readonly class Symbol
{
    public string $hash;

    public function __construct(public string $name)
    {
        $this->hash = hash('crc32', $this->name);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
