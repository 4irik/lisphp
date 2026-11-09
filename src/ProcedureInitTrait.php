<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

trait ProcedureInitTrait
{
    /** @var array<array-key, object|array|int|float|string|bool|null> */
    protected readonly array $args;

    protected readonly Symbol|array|string|bool|float|int|Form|Located $body;

    /**
     * @param array<array-key, mixed> $list
     *
     * @return array{0: array<array-key, object|array|int|float|string|bool|null>, 1: Symbol|array|string|bool|float|int|Form|Located}
     */
    private function readParams(array $list): array
    {
        $args = \Che\SimpleLisp\Eval\value($list[1] ?? null);
        if ($args instanceof Form) {
            $args = $args->toArray();
        }
        if (!is_array($args)) {
            throw new \TypeError('Procedure parameters must be a list');
        }

        $body = \Che\SimpleLisp\Eval\value($list[2] ?? null);
        if (
            !is_scalar($body)
            && !$body instanceof Symbol
            && !$body instanceof Form
            && !$body instanceof Located
            && !is_array($body)
        ) {
            throw new \TypeError('Procedure body has an unexpected type');
        }

        return [\Che\SimpleLisp\Eval\values($args), $body];
    }
}
