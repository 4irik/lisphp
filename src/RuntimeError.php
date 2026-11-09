<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

final class RuntimeError extends \Exception
{
    public function __construct(
        string $message,
        public readonly int $atLine,
        public readonly int $atColumn,
        public readonly string $sourceLine,
        \Throwable $cause,
    ) {
        parent::__construct($message, previous: $cause);
    }
}
