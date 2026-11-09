<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

final readonly class Located
{
    public function __construct(
        public mixed $value,
        public int $line,
        public int $column,
        public string $sourceLine = '',
    ) {}
}
