<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

use function Che\SimpleLisp\Eval\_eval;

final readonly class Lambda implements \IteratorAggregate
{
    use ProcedureInitTrait;

    public function __construct(array $list, private HashMapInterface $env)
    {
        $this->initParams($list);
    }

    public function __invoke(...$args): mixed
    {
        $localEnv = new HashMap($this->env);

        foreach ($this->args as $key => $argVarItem) {
            $localEnv->put(
                $argVarItem instanceof Symbol ? $argVarItem : new Symbol((string)_eval($argVarItem, $this->env)),
                _eval($args[$key], $this->env)
            );
        }

        return _eval($this->body, $localEnv);
    }

    #[\Override] public function getIterator(): \Traversable
    {
        return new \ArrayIterator([ControlStructureName::LAMBDA->value, $this->args, $this->body]);
    }
}
