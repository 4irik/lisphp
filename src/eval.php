<?php

declare(strict_types=1);

namespace Che\SimpleLisp\Eval;

use Che\SimpleLisp\HashMap;
use Che\SimpleLisp\HashMapInterface;
use Che\SimpleLisp\Procedure;
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

function _eval(mixed $x, HashMapInterface $env): mixed
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

    if(is_array($x)) {
        if(!$x) {
            return $x;
        }

        $procedure  = Procedure::tryFrom(is_array($x[0]) ? '' : (string)$x[0]);
        return match ($procedure) {
            Procedure::COND => _handleIf($x, $env),
            Procedure::DEF => _handleDefine($x, $env),
            Procedure::SET => _handleSet($x, $env),
            Procedure::DO => _handleDo($x, $env),
            Procedure::QUOTE => $x[1],
            Procedure::LAMBDA => _handleLambda($x, $env),
            Procedure::MACRO => _handleMacro($x, $env),
            Procedure::EVAL => _eval(_eval($x[1], $env), $env),
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
    $procDef = array_shift($x);
    $procInstance = _eval($procDef, $env);

    $args = static function (iterable $args) use ($procDef, $env): array {
        $procName = (string)(is_array($procDef) ? $procDef[0] : '');

        return match (Procedure::tryFrom($procName)) {
            Procedure::MACRO => $args,
            default => iterator_to_array(map($args, fn ($item) => _eval($item, $env)))
        };
    };

    return is_callable($procInstance)
        ? $procInstance(...$args($x))
        : [$procInstance, ...$args($x)];
}

function _handleLambda(array $x, HashMapInterface $env): \Closure
{
    return static function (...$args) use ($x, $env): mixed {
        $localEnv = new HashMap($env);

        $argsVar = $x[1];
        foreach ($argsVar as $key => $argVarItem) {
            $localEnv->put(
                $argVarItem instanceof Symbol ? $argVarItem : new Symbol((string)_eval($argVarItem, $env)),
                _eval($args[$key], $env)
            );
        }

        return _eval($x[2], $localEnv);
    };
}

function _handleMacro(array $x, HashMapInterface $env): \Closure
{
    return static function (...$args) use ($x, $env): mixed {
        $argsVar = $x[1];
        $body = $x[2];
        foreach ($argsVar as $argVarKey => $argVarName) {
            $paramValueItem = $args[$argVarKey];
            array_walk_recursive($body, static function (&$bodyItem) use ($argVarName, $paramValueItem): void {
                if($bodyItem instanceof Symbol && $bodyItem == $argVarName) {
                    $bodyItem = $paramValueItem;
                }
            });
        }

        return _eval($body, $env);
    };
}
