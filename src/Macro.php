<?php

namespace Che\SimpleLisp;

use Symfony\Component\VarDumper\Caster\SplCaster;

use function Che\SimpleLisp\Eval\_eval;
use function Che\SimpleLisp\Eval\trampoline;

final class MacroArgs
{
    /**
     * @var array<string, mixed>
     */
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
}

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
        $argsMap = new MacroArgs();

        $countExpectedArgs = count($this->args);
        $countReceivedArgs = count($args);
        foreach ($this->args as $argKey => $argName) {
            if (!$argName instanceof Symbol) {
                throw new \TypeError('Macro parameter must be a symbol');
            }
            if ($countExpectedArgs < $countReceivedArgs && ($argKey + 1) === $countExpectedArgs) {
                $argsValue = $args;
            } else {
                $argsValue = array_shift($args);
            }

            $argsMap->put($argName, $argsValue);
        }

        $expanded = $this->substitute($this->body, $argsMap);
        if (!is_object($expanded) && !is_array($expanded) && !is_scalar($expanded)) {
            throw new \TypeError('Macro expansion is not a value');
        }

        return _eval($expanded, $env, true);
    }

    private function substitute(mixed $node, MacroArgs $argsMap): mixed
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
