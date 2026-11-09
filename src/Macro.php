<?php

namespace Che\SimpleLisp;

use Symfony\Component\VarDumper\Caster\SplCaster;

use function Che\SimpleLisp\Eval\_eval;
use function Che\SimpleLisp\Eval\trampoline;

final readonly class Macro implements \IteratorAggregate
{
    use ProcedureInitTrait;

    public function __construct(array $list)
    {
        $this->initParams($list);
    }

    public function __invoke(HashMapInterface $env, ...$args): mixed
    {
        return trampoline($this->tailApply($env, $args));
    }

    public function tailApply(HashMapInterface $env, array $args): mixed
    {
        $argsMap = new class() {
            private array $map = [];

            public function put(Symbol $symbol, mixed $value): void
            {
                $this->map[(string) $symbol] = $value;
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

        $countExpectedArgs = count($this->args);
        $countReceivedArgs = count($args);
        foreach ($this->args as $argKey => $argName) {
            if ($countExpectedArgs < $countReceivedArgs && ($argKey + 1) === $countExpectedArgs) {
                $argsValue = $args;
            } else {
                $argsValue = array_shift($args);
            }

            $argsMap->put($argName, $argsValue);
        }

        return _eval($this->substitute($this->body, $argsMap), $env, true);
    }

    private function substitute(mixed $node, object $argsMap): mixed
    {
        if ($node instanceof Symbol && $argsMap->has($node)) {
            return $argsMap->get($node);
        }

        if ($node instanceof Located) {
            if ($node->value instanceof Symbol && $argsMap->has($node->value)) {
                return $argsMap->get($node->value);
            }

            return $node;
        }

        if ($node instanceof Form) {
            $items = [];
            foreach ($node->items as $item) {
                $items[] = $this->substitute($item, $argsMap);
            }

            return new Form($items, $node->line, $node->column, $node->sourceLine);
        }

        if (is_array($node)) {
            $items = [];
            foreach ($node as $key => $item) {
                $items[$key] = $this->substitute($item, $argsMap);
            }

            return $items;
        }

        return $node;
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator([new Symbol(ControlStructureName::MACRO->value), $this->args, $this->body]);
    }
}
