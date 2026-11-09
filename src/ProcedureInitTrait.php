<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

trait ProcedureInitTrait
{
    protected readonly array $args;
    protected readonly Symbol|array|string|bool|float|int|Form $body;

    private function initParams(array $list): void
    {
        $args = $list[1];
        $this->args = $args instanceof Form ? $args->toArray() : $args;
        $this->body = $list[2];
    }
}
