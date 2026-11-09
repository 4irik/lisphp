<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

final readonly class TailCall
{
    public function __construct(
        private Lambda|Macro $fn,
        private HashMapInterface $env,
        private array $args,
    ) {}

    public function resume(): object|array|int|float|string|bool|null
    {
        return $this->fn->tailApply($this->env, $this->args);
    }
}
