<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

use function Che\SimpleLisp\Eval\_eval;
use function Che\SimpleLisp\Eval\trampoline;
use function Che\SimpleLisp\Eval\value;
use function Che\SimpleLisp\Eval\values;

final class MacroArgs
{
    /**
     * @var array<string, object|array|int|float|string|bool|null>
     */
    private array $map = [];

    public function put(Symbol $symbol, mixed $value): void
    {
        $this->map[(string) $symbol] = value($value);
    }

    public function has(Symbol $symbol): bool
    {
        return isset($this->map[(string) $symbol]);
    }

    public function get(Symbol $symbol): object|array|int|float|string|bool|null
    {
        return $this->map[(string) $symbol] ?? null;
    }
}

/**
 * @implements \IteratorAggregate<int, mixed>
 */
final readonly class Macro implements \IteratorAggregate
{
    use ProcedureInitTrait;

    public function __construct(array $list)
    {
        [$this->args, $this->body] = $this->readParams($list);
    }

    public function __invoke(HashMapInterface $env, mixed ...$args): object|array|int|float|string|bool|null
    {
        return trampoline($this->tailApply($env, $args));
    }

    /**
     * @param array<array-key, mixed> $args
     */
    public function tailApply(HashMapInterface $env, array $args): object|array|int|float|string|bool|null
    {
        $argsMap = new MacroArgs();
        $args = values($args);
        $countExpectedArgs = count($this->args);
        $countReceivedArgs = count($args);
        $lastKey = array_key_last($this->args);
        foreach ($this->args as $argKey => $argName) {
            if (!$argName instanceof Symbol) {
                throw new \TypeError('Macro parameter must be a symbol');
            }
            $argsValue = $countExpectedArgs < $countReceivedArgs && $argKey === $lastKey ? $args : array_shift($args);

            $argsMap->put($argName, $argsValue);
        }

        $expanded = $this->substitute($this->body, $argsMap);
        if ($expanded === null) {
            throw new \TypeError('Macro expansion is not a value');
        }

        return _eval($expanded, $env, true);
    }

    private function substitute(object|array|int|float|string|bool|null $node, MacroArgs $argsMap): object|array|int|float|string|bool|null
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
            foreach (values($node->items) as $item) {
                $items[] = $this->substitute($item, $argsMap);
            }

            return new Form($items, $node->line, $node->column, $node->sourceLine);
        }

        if (is_array($node)) {
            $items = [];
            foreach (values($node) as $key => $item) {
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
