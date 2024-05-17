<?php

namespace Che\SimpleLisp;

use Symfony\Component\VarDumper\Caster\SplCaster;

use function Che\SimpleLisp\Eval\_eval;

final readonly class Macro implements \IteratorAggregate
{
    use ProcedureInitTrait;

    public function __construct(array $list)
    {
        $this->initParams($list);
    }

    public function __invoke(HashMapInterface $env, ...$args): mixed
    {
        $body = $this->body;

        $argsMap = new class () {
            private array $map = [];

            public function put(Symbol $symbol, mixed $value): void
            {
                $this->map[(string)$symbol] = $value;
            }

            public function has(Symbol $symbol): bool
            {
                return isset($this->map[(string) $symbol]);
            }

            public function get(Symbol $symbol): mixed
            {
                return $this->map[(string) $symbol] ?? null;
            }
        };
        foreach ($this->args as $argKey => $argName) {
            $argsMap->put($argName, $args[$argKey]);
        }

        array_walk_recursive($body, static function (&$bodyItem) use ($argsMap): void {
            if($bodyItem instanceof Symbol && $argsMap->has($bodyItem)) {
                $bodyItem = $argsMap->get($bodyItem);
            }
        });

        return _eval($body, $env);
    }

    #[\Override] public function getIterator(): \Traversable
    {
        return new \ArrayIterator([new Symbol(ControlStructureName::MACRO->value), $this->args, $this->body]);
    }
}
