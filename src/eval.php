<?php

declare(strict_types=1);

namespace Che\SimpleLisp\Eval;

use Che\SimpleLisp\ControlStructureName;
use Che\SimpleLisp\Form;
use Che\SimpleLisp\HashMapInterface;
use Che\SimpleLisp\Lambda;
use Che\SimpleLisp\Located;
use Che\SimpleLisp\Macro;
use Che\SimpleLisp\Procedure;
use Che\SimpleLisp\RuntimeError;
use Che\SimpleLisp\Symbol;
use Che\SimpleLisp\TailCall;

function value(mixed $item): object|array|int|float|string|bool|null
{
    if (is_object($item) || is_array($item)) {
        return $item;
    }
    if (is_scalar($item) || $item === null) {
        return $item;
    }

    throw new \TypeError('Unexpected value');
}

/**
 * @param array<array-key, mixed> $list
 *
 * @return array<array-key, object|array|int|float|string|bool|null>
 */
function values(array $list): array
{
    $out = [];
    foreach (array_keys($list) as $key) {
        $out[$key] = value($list[$key] ?? null);
    }

    return $out;
}

/**
 * @param array<array-key, mixed> $list
 * @param \Closure(object|array|int|float|string|bool|null, object|array|int|float|string|bool|null): (object|array|int|float|string|bool|null) $f
 */
function reduce(array $list, \Closure $f): object|array|int|float|string|bool|null
{
    $acc = null;
    $accIndex = 0;
    $index = 0;
    foreach (values($list) as $item) {
        if (null === $acc) {
            $acc = $item;
            $accIndex = $index;
            $index++;
            continue;
        }

        try {
            $next = $f($acc, $item);
        } catch (RuntimeError|OperandFailure $error) {
            throw $error;
        } catch (\Throwable $error) {
            $accIsNumber = is_int($acc) || is_float($acc);
            $itemIsNumber = is_int($item) || is_float($item);
            $blame = !$accIsNumber && $itemIsNumber ? $accIndex : $index;
            throw new OperandFailure($blame, $error);
        }

        $acc = $next;
        $accIndex = $index;
        $index++;
    }

    return $acc;
}

/**
 * @return array{int, int, string}|null
 */
function argumentPlace(mixed $expr): ?array
{
    if ($expr instanceof Form || $expr instanceof Located) {
        return [$expr->line, $expr->column, $expr->sourceLine];
    }

    return null;
}

/**
 * @param iterable<mixed> $list
 * @param \Closure(mixed): mixed $f
 */
function map(iterable $list, \Closure $f): \Generator
{
    $items = is_array($list) ? $list : iterator_to_array($list);
    foreach (values($items) as $item) {
        yield $f($item);
    }
}

function envForSymbol(Symbol $s, HashMapInterface $env): HashMapInterface
{
    $startEnv = $env;

    while (true) {
        if ($env->has($s)) {
            return $env;
        }

        $parent = $env->parent();
        if ($parent === null) {
            return $startEnv;
        }

        $env = $parent;
    }
}

function trampoline(object|array|int|float|string|bool|null $value): object|array|int|float|string|bool|null
{
    while ($value instanceof TailCall) {
        $value = $value->resume();
    }

    return $value;
}

function _eval(object|array|string|int|float|bool|null $x, HashMapInterface $env, bool $tail = false): object|array|string|int|float|bool|null
{
    if ($x === null) {
        throw new \TypeError('Unexpected null');
    }

    if (is_scalar($x)) {
        return $x;
    }

    if ($x instanceof Symbol) {
        $symbolEnv = envForSymbol($x, $env);
        return $symbolEnv->has($x) ? $symbolEnv->get($x) : $x;
    }

    if ($x instanceof Located) {
        try {
            return _eval(value($x->value), $env, $tail);
        } catch (RuntimeError $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw new RuntimeError($error->getMessage(), $x->line, $x->column, $x->sourceLine, $error);
        }
    }

    if ($x instanceof Form) {
        try {
            return _eval($x->items, $env, $tail);
        } catch (RuntimeError $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw new RuntimeError($error->getMessage(), $x->line, $x->column, $x->sourceLine, $error);
        }
    }

    if (is_object($x)) {
        return $x;
    }

    if (!$x) {
        return $x;
    }

    $list = values($x);
    $head = $list[0] ?? null;
    if ($head instanceof Located) {
        $head = value($head->value);
    }
    $procedure = ControlStructureName::tryFrom(headName($head));
    return match ($procedure) {
        ControlStructureName::COND => _handleIf($list, $env, $tail),
        ControlStructureName::DEF => _handleDefine($list, $env),
        ControlStructureName::SET => _handleSet($list, $env),
        ControlStructureName::DO => _handleDo($list, $env, $tail),
        ControlStructureName::QUOTE => Form::data($list[1] ?? null),
        ControlStructureName::LAMBDA => new Lambda($list, $env),
        ControlStructureName::MACRO => new Macro($list),
        ControlStructureName::EVAL => _eval(_eval($list[1] ?? null, $env), $env, $tail),
        ControlStructureName::TYPEOF => _typeOf(_eval($list[1] ?? null, $env)),
        ControlStructureName::SYMBOL => _handleSymbol($list[1] ?? null, $env),
        ControlStructureName::PRINT => _print($list[1] ?? null, $env),
        ControlStructureName::READ => _handleRead(),
        default => _handleProcedure($list, $env, $tail),
    };
}

function headName(object|array|int|float|string|bool|null $head): string
{
    if ($head === null || is_array($head) || $head instanceof Form) {
        return '';
    }
    if (is_scalar($head) || $head instanceof \Stringable) {
        return (string) $head;
    }

    throw new \Error(sprintf('Object of class %s could not be converted to string', get_debug_type($head)));
}

function _print(object|array|int|float|string|bool|null $x, HashMapInterface $env): void
{
    $value = _eval($x, $env);
    $printableValue = match (true) {
        is_scalar($value) => $value,
        $value instanceof \Stringable => (string) $value,
        is_object($value) => throw new \Exception(sprintf('class "%s" could not be convert to string', get_class($value))),
        default => throw new \Exception(sprintf('class "%s" could not be convert to string', get_debug_type($value))),
    };

    file_put_contents('php://stdout', $printableValue);
}

function _handleRead(): string
{
    $line = readline();
    if (!is_string($line)) {
        throw new \TypeError('readline(): Return value must be of type string, false returned');
    }

    return $line;
}

function _typeOf(object|array|int|float|string|bool|null $x): string
{
    return match (true) {
        is_int($x) => 'int',
        is_float($x) => 'float',
        is_string($x) => 'str',
        is_bool($x) => 'bool',
        is_array($x) => 'ConsList',
        $x instanceof Symbol => 'Symbol',
        $x instanceof Lambda => 'Lambda',
        $x instanceof Macro => 'Macro',
        is_object($x) => get_class($x),
        default => throw new \Exception('Undefined type of $x'),
    };
}

function _handleSymbol(object|array|int|float|string|bool|null $value, HashMapInterface $env): Symbol
{
    if ($value instanceof Located) {
        $value = value($value->value);
    }

    if (is_string($value)) {
        return new Symbol($value);
    }
    if ($value instanceof Symbol) {
        return new Symbol($value->name);
    }
    if (is_scalar($value)) {
        return new Symbol((string) $value);
    }
    if (is_array($value) || $value instanceof Form) {
        return new Symbol(symbolName(_eval($value, $env)));
    }

    throw new \Exception(sprintf('symbol operation dont perform object type "%s"', gettype($value)));
}

function symbolName(object|array|int|float|string|bool|null $value): string
{
    if (is_string($value)) {
        return $value;
    }

    throw new \TypeError(sprintf('Symbol::__construct(): Argument #1 ($name) must be of type string, %s given', get_debug_type($value)));
}

/**
 * @param array<array-key, object|array|int|float|string|bool|null> $x
 *
 * @throws \Exception
 */
function _handleIf(array $x, HashMapInterface $env, bool $tail): object|array|int|float|string|bool|null
{
    $cond = $x[1] ?? null;
    $st_true = $x[2] ?? null;
    $st_false = $x[3] ?? [];
    $exp = _eval($cond, $env) ? $st_true : $st_false;

    return _eval($exp, $env, $tail);
}

/**
 * @param array<array-key, object|array|int|float|string|bool|null> $x
 *
 * @throws \Exception
 */
function _handleDefine(array $x, HashMapInterface $env): null
{
    array_shift($x);
    if ((count($x) % 2) != 0) {
        throw new \Exception(sprintf('"def" required an even number of arguments, received: %d', count($x)));
    }
    foreach (array_chunk($x, 2) as [$expSymbol, $exprValue]) {
        $env->put(bindingSymbol($expSymbol, $env), _eval($exprValue, $env));
    }

    return null;
}

/**
 * @param array<array-key, object|array|int|float|string|bool|null> $x
 */
function _handleSet(array $x, HashMapInterface $env): null
{
    array_shift($x);
    if ((count($x) % 2) != 0) {
        throw new \Exception(sprintf('"set!" required an even number of arguments, received: %d', count($x)));
    }

    foreach (array_chunk($x, 2) as [$expSymbol, $exprValue]) {
        $symbol = bindingSymbol($expSymbol, $env);
        $envForSymbol = envForSymbol($symbol, $env);
        $envForSymbol->put($symbol, _eval($exprValue, $env));
    }

    return null;
}

/**
 * @param array<array-key, object|array|int|float|string|bool|null> $x
 *
 * @throws \Exception
 */
function _handleDo(array $x, HashMapInterface $env, bool $tail): object|array|int|float|string|bool|null
{
    array_shift($x);
    if (!$x) {
        throw new \Exception('"do" required one or more arguments');
    }
    $lastIndex = array_key_last($x);
    $val = null;
    foreach ($x as $index => $expr) {
        $val = _eval($expr, $env, $tail && $index === $lastIndex);
    }

    return $val;
}

/**
 * @param array<array-key, object|array|int|float|string|bool|null> $x
 */
function _handleProcedure(array $x, HashMapInterface $env, bool $tail): object|array|int|float|string|bool|null
{
    $procInstance = _eval(array_shift($x), $env);
    if ($tail && ($procInstance instanceof Lambda || $procInstance instanceof Macro)) {
        return new TailCall($procInstance, $env, $x);
    }
    if ($procInstance instanceof Procedure || $procInstance instanceof Lambda || $procInstance instanceof Macro) {
        return $procInstance($env, ...$x);
    }
    if (!is_callable($procInstance)) {
        $procInstance = new Procedure('list_spread', static fn(mixed ...$args): array => [$procInstance, ...$args]);

        return $procInstance($env, ...$x);
    }

    return value($procInstance($env, ...$x));
}

function bindingSymbol(object|array|int|float|string|bool|null $expr, HashMapInterface $env): Symbol
{
    if ($expr instanceof Located) {
        $expr = value($expr->value);
    }
    if ($expr instanceof Symbol) {
        return $expr;
    }

    $name = _eval($expr, $env);
    if (is_scalar($name) || $name instanceof \Stringable) {
        return new Symbol((string) $name);
    }

    throw new \Error(sprintf('Object of class %s could not be converted to string', get_debug_type($name)));
}

final class OperandFailure extends \Exception
{
    public function __construct(
        public readonly int $index,
        \Throwable $cause,
    ) {
        parent::__construct($cause->getMessage(), previous: $cause);
    }
}
