<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

use function Che\SimpleLisp\Eval\_eval;
use function Che\SimpleLisp\Eval\trampoline;
use function Che\SimpleLisp\Eval\values;

/**
 * @implements \IteratorAggregate<int, mixed>
 */
final readonly class Lambda implements \IteratorAggregate
{
    use ProcedureInitTrait;

    public function __construct(
        array $list,
        private HashMapInterface $env,
    ) {
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
        $localEnv = new HashMap($this->env);
        $args = values($args);
        $countExpectedArgs = count($this->args);
        $countReceivedArgs = count($args);
        $lastKey = array_key_last($this->args);

        foreach ($this->args as $key => $argVarItem) {
            $argsValue = $countExpectedArgs < $countReceivedArgs && $key === $lastKey ? $args : array_shift($args);

            $localEnv->put($this->parameterSymbol($argVarItem, $env), _eval($argsValue, $env));
        }

        return _eval($this->body, $localEnv, true);
    }

    private function parameterSymbol(object|array|int|float|string|bool|null $argVarItem, HashMapInterface $env): Symbol
    {
        if ($argVarItem instanceof Symbol) {
            return $argVarItem;
        }
        if ($argVarItem instanceof Located && $argVarItem->value instanceof Symbol) {
            return $argVarItem->value;
        }

        $name = _eval($argVarItem, $env);
        if (is_scalar($name) || $name instanceof \Stringable) {
            return new Symbol((string) $name);
        }

        throw new \Error(sprintf('Object of class %s could not be converted to string', get_debug_type($name)));
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator([new Symbol(ControlStructureName::LAMBDA->value), $this->args, $this->body]);
    }
}
