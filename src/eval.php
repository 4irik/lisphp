<?php

declare(strict_types=1);

namespace Che\SimpleLisp\Eval;

use Che\SimpleLisp\HashMapInterface;
use Che\SimpleLisp\Symbol;

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

function _eval(mixed $x, HashMapInterface $env): mixed
{
    if(is_scalar($x)) {
        return $x;
    }

    if(is_object($x) && $x instanceof Symbol) {
        return $env->has($x)
            ? $env->get($x)
            : $x;
    }

    if(is_array($x)) {
        if(!$x) {
            return $x;
        }

        return match ((string) $x[0]) {
            'cond' => _handleIf($x, $env),
            'def' => _handleDefine($x, $env),
            'do' => _handleDo($x, $env),
            'quote' => $x[1],
            //            'lambda' => _handleLambda($x, $env),
            //            'map' => _handleMap($x, $env),
            'eval' => _eval(_eval($x[1], $env), $env),
            default => _handleProcedure($x, $env),
        };
    }

    throw new \Exception('Unknown expression type: $x');
}

/**
 * @throws \Exception
 */
function _handleIf(array $x, HashMapInterface $env): mixed
{
    $cond = $x[1];
    $st_true = $x[2];
    $st_false = $x[3];
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
    $proc = _eval(array_shift($x), $env);
    $args = map($x, fn ($item) => _eval($item, $env));
    return is_scalar($proc)
        ? [$proc, ...$args]
        : $proc(...$args);
}
