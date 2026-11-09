<?php

declare(strict_types=1);

namespace Che\SimpleLisp\Env;

use Che\SimpleLisp\HashMap;
use Che\SimpleLisp\HashMapInterface;
use Che\SimpleLisp\Procedure;
use Che\SimpleLisp\Symbol;

use function Che\SimpleLisp\Eval\reduce;

function isPhpPair(mixed $value): bool
{
    return is_array($value)
        && array_is_list($value)
        && count($value) === 2
        && (is_string($value[0]) || $value[0] instanceof Symbol);
}

function phpValue(mixed $value): mixed
{
    if ($value instanceof Symbol) {
        return (string) $value;
    }

    if (isPhpPair($value)) {
        return [(string) $value[0] => phpValue($value[1])];
    }

    if (!is_array($value)) {
        return $value;
    }

    $converted = [];
    foreach ($value as $key => $item) {
        $converted[$key] = phpValue($item);
    }

    return $converted;
}

/**
 * @param list<mixed> $args
 * @return array<int|string, mixed>
 */
function phpArgs(array $args): array
{
    $converted = [];
    foreach ($args as $arg) {
        if (isPhpPair($arg)) {
            $converted[(string) $arg[0]] = phpValue($arg[1]);
            continue;
        }

        $converted[] = phpValue($arg);
    }

    return $converted;
}

/**
 * @param array<int|string, mixed> $args
 * @return array<int|string, mixed>
 */
function adaptPhpArgs(\ReflectionFunctionAbstract $reflection, array $args): array
{
    $parameters = $reflection->getParameters();
    $byName = [];
    foreach ($parameters as $parameter) {
        $byName[$parameter->getName()] = $parameter;
    }

    $adapted = [];
    $index = 0;
    foreach ($args as $key => $value) {
        $parameter = is_int($key) ? ($parameters[$index++] ?? null) : ($byName[$key] ?? null);
        $adapted[$key] = adaptPhpValue($value, $parameter?->getType());
    }

    return $adapted;
}

function adaptPhpValue(mixed $value, ?\ReflectionType $type): mixed
{
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = adaptPhpValue($item, null);
        }

        return $value;
    }

    if (!is_float($value) || !is_finite($value) || $value !== floor($value)) {
        return $value;
    }

    $name = $type instanceof \ReflectionNamedType && $type->isBuiltin() ? $type->getName() : null;
    if ($name === 'string') {
        return (string) $value;
    }
    if ($name === 'float') {
        return $value;
    }
    if ($value < PHP_INT_MIN || $value > PHP_INT_MAX) {
        return $value;
    }

    return (int) $value;
}

function _defaultEnv(): HashMapInterface
{
    $storage = new HashMap();

    $add = static function (string $name, callable $closure) use ($storage) {
        $storage->put(new Symbol($name), new Procedure($name, $closure));
    };

    $add('=', static fn($a, $b): bool => $a == $b);
    $add('>', static fn($a, $b): bool => $a > $b);
    $add('<', static fn($a, $b): bool => $a < $b);
    $add('>=', static fn($a, $b): bool => $a >= $b);
    $add('<=', static fn($a, $b): bool => $a <= $b);
    $add('not', static fn(bool $x): bool => !$x);
    $add('abs', static fn($x): int|float => abs($x));
    $add('+', static fn(...$x): int|float => reduce($x, fn($a, $b): int|float => $a + $b));
    $add('-', static fn(...$x): int|float => reduce($x, fn($a, $b): int|float => $a - $b));
    $add('*', static fn(...$x): int|float => reduce($x, fn($a, $b): int|float => $a * $b));
    $add('/', static fn(...$x): int|float => reduce($x, fn($a, $b): int|float => $a / $b));
    $add('max', static fn(...$x): int|float => reduce($x, fn($a, $b): int|float => max($a, $b)));
    $add('min', static fn(...$x): int|float => reduce($x, fn($a, $b): int|float => min($a, $b)));
    $add('mod', static fn(...$x): int|float => reduce($x, fn($a, $b): int|float => $a % $b));
    $add('++', static fn(...$x): string => reduce($x, fn($a, $b): string => $a . $b));
    $add('car', static fn(array $x): Symbol|array|string|int|float|bool => current($x));
    $add('cdr', static fn(array $x): array => array_slice($x, 1));
    $add('cons', static fn($a, $b): array => array_merge([$a], (array) $b));
    $add('class', static fn(string $className): string => match (class_exists($className)) {
        true => $className,
        false => throw new \Exception(sprintf('class name "%s" not found', $className)),
    });
    $add('php', static function (string|object $fn, ...$args): mixed {
        if ($fn instanceof Symbol) {
            $fn = (string) $fn;
        }
        if (is_string($fn) && function_exists($fn)) {
            return $fn(...adaptPhpArgs(new \ReflectionFunction($fn), phpArgs($args)));
        }

        $method = array_shift($args);
        $args = phpArgs($args);
        if ($method === 'new') {
            $constructor = (new \ReflectionClass($fn))->getConstructor();
            if ($constructor !== null) {
                $args = adaptPhpArgs($constructor, $args);
            }

            return new $fn(...$args);
        }
        // вызов invocable-объекта
        if (!method_exists($fn, (string) $method) && is_callable($fn)) {
            return $fn($method, ...$args);
        }

        return [$fn, $method](...adaptPhpArgs(new \ReflectionMethod($fn, (string) $method), $args));
    });

    return $storage;
}
