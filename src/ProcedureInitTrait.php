<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

trait ProcedureInitTrait
{
    protected readonly array $args;
    protected readonly Symbol|array|string|bool|float|int|Form|Located $body;

    private function initParams(array $list): void
    {
        $args = $list[1] ?? null;
        if ($args instanceof Form) {
            $args = $args->toArray();
        }
        if (!is_array($args)) {
            throw new \TypeError('Procedure parameters must be a list');
        }
        $this->args = $args;

        $body = $list[2] ?? null;
        if (
            !is_scalar($body)
            && !$body instanceof Symbol
            && !$body instanceof Form
            && !$body instanceof Located
            && !is_array($body)
        ) {
            throw new \TypeError('Procedure body has an unexpected type');
        }
        $this->body = $body;
    }
}
