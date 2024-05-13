<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

trait ProcedureInitTrait
{
    protected readonly array $args;
    protected readonly Symbol|array|string|bool|float|int $body;

    private function initParams(array $list): void
    {
        $this->args = $list[1];
        $this->body = $list[2];
    }
}
