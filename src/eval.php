<?php

declare(strict_types=1);

namespace Che\SimpleLisp\Eval;

use Che\SimpleLisp\HashMapInterface;
use Che\SimpleLisp\ControlStructureName;
use Che\SimpleLisp\Procedure;
use Che\SimpleLisp\Symbol;
use Che\SimpleLisp\Lambda;
use Che\SimpleLisp\Macro;

/**
 * @template T
 * @param iterable<T> $list
 * @param \Closure(T|mixed, T): mixed $f
 * @return mixed
 */
function reduce(iterable $list, \Closure $f): mixed
{
    $acc = null;
    foreach ($list as $item) {
        if(null === $acc) {
            $acc = $item;
            continue;
        }

        $acc = $f($acc, $item);
    }

    return $acc;
}

/**
 * @template T
 * @template M
 * @param iterable<T> $list
 * @param \Closure<T>:M $f
 * @return iterable<M>
 */
function map(iterable $list, \Closure $f): iterable
{
    foreach ($list as $item) {
        yield $f($item);
    }
}

function envForSymbol(Symbol $s, HashMapInterface $env): HashMapInterface
{
    $isDetected = false;
    $startEnv = $env;

    while (true) {
        if($env->has($s)) {
            $isDetected = true;
            break;
        }

        if($env->parent() === null) {
            break;
        }

        $env = $env->parent();
    }

    return $isDetected ? $env : $startEnv;
}

function _eval(object|array|string|int|float|bool $x, HashMapInterface $env): mixed
{
    if(is_scalar($x)) {
        return $x;
    }

    if($x instanceof Symbol) {
        $symbolEnv = envForSymbol($x, $env);
        return $symbolEnv->has($x)
            ? $symbolEnv->get($x)
            : $x;
    }

    if(is_object($x)) {
        return $x;
    }

    if(!$x) {
        return $x;
    }

    $procedure  = ControlStructureName::tryFrom(is_array($x[0]) ? '' : (string)$x[0]);
    return match ($procedure) {
        ControlStructureName::COND => _handleIf($x, $env),
        ControlStructureName::DEF => _handleDefine($x, $env),
        ControlStructureName::SET => _handleSet($x, $env),
        ControlStructureName::DO => _handleDo($x, $env),
        ControlStructureName::QUOTE => $x[1],
        ControlStructureName::LAMBDA => new Lambda($x, $env),
        ControlStructureName::MACRO => new Macro($x),
        ControlStructureName::EVAL => _eval(_eval($x[1], $env), $env),
        ControlStructureName::TYPEOF => _typeOf(_eval($x[1], $env)),
        ControlStructureName::PRINT => _print($x[1], $env),
        ControlStructureName::READ => _handleRead(),
        default => _handleProcedure($x, $env),
    };
}

function _print(object|array|int|float|string|bool $x, HashMapInterface $env): void
{
    $value = _eval($x, $env);
    $printableValue = match (true) {
        is_scalar($value) => $value,
        is_object($value) && $value instanceof \Stringable => (string) $value,
        default => throw new \Exception(sprintf('class "%s" could not be convert to string', get_class($value)))
    };

    file_put_contents('php://stdout', $printableValue);
}

function _handleRead(): string
{
    return readline();
}

function _typeOf(object|array|int|float|string|bool $x): string
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
        default => throw new \Exception('Undefined type of $x')
    };
}

/**
 * @throws \Exception
 */
function _handleIf(array $x, HashMapInterface $env): mixed
{
    $cond = $x[1];
    $st_true = $x[2];
    $st_false = $x[3] ?? [];
    $exp = _eval($cond, $env) ? $st_true : $st_false;
    return _eval($exp, $env);
}

/**
 * @throws \Exception
 */
function _handleDefine(array $x, HashMapInterface $env): null
{
    array_shift($x);
    if(count($x) % 2 != 0) {
        throw new \Exception(sprintf('"def" required an even number of arguments, received: %d', count($x)));
    }
    foreach (array_chunk($x, 2) as [$expSymbol, $exprValue]) {
        $env->put(
            $expSymbol instanceof Symbol
            ? $expSymbol
            : new Symbol((string)_eval($expSymbol, $env)),
            _eval($exprValue, $env)
        );
    }
    return null;
}

function _handleSet(array $x, HashMapInterface $env): null
{
    array_shift($x);
    if(count($x) % 2 != 0) {
        throw new \Exception(sprintf('"set!" required an even number of arguments, received: %d', count($x)));
    }

    foreach (array_chunk($x, 2) as [$expSymbol, $exprValue]) {
        $symbol = $expSymbol instanceof Symbol
            ? $expSymbol
            : new Symbol((string)_eval($expSymbol, $env));

        $envForSymbol = envForSymbol($symbol, $env);
        $envForSymbol->put($symbol, _eval($exprValue, $env));
    }

    return null;
}

/**
 * @throws \Exception
 */
function _handleDo(array $x, HashMapInterface $env): mixed
{
    array_shift($x);
    if(!$x) {
        throw new \Exception('"do" required one or more arguments');
    }
    foreach ($x as $expr) {
        $val = _eval($expr, $env);
    }
    return $val;
}

function _handleProcedure(array $x, HashMapInterface $env): mixed
{
    $procInstance = _eval(array_shift($x), $env);
    if(!is_callable($procInstance)) {
        $procInstance = new Procedure('list_spread', fn (...$x) => [$procInstance, ...$x]);
    }

    return $procInstance($env, ...$x);
}
