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
    return (
        is_array($value)
        && array_is_list($value)
        && count($value) === 2
        && (is_string($value[0]) || $value[0] instanceof Symbol)
    );
}

function phpValue(mixed $value): mixed
{
    if ($value instanceof Symbol) {
        return (string) $value;
    }

    if (is_array($value) && isPhpPair($value)) {
        $head = $value[0];
        if (!is_string($head) && !$head instanceof Symbol) {
            return $value;
        }

        return [(string) $head => phpValue($value[1])];
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
 * @param array<array-key, mixed> $args
 * @return array<int|string, mixed>
 */
function phpArgs(array $args): array
{
    $converted = [];
    foreach ($args as $arg) {
        if (is_array($arg) && isPhpPair($arg)) {
            $head = $arg[0];
            if (!is_string($head) && !$head instanceof Symbol) {
                $converted[] = phpValue($arg);
                continue;
            }
            $converted[(string) $head] = phpValue($arg[1]);
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
        $parameter = is_int($key) ? $parameters[$index++] ?? null : $byName[$key] ?? null;
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

/**
 * @param \Closure(int|float, int|float): mixed $operation
 */
function numericReduce(array $values, \Closure $operation, string $operator): int|float
{
    $result = reduce($values, static function (mixed $left, mixed $right) use ($operation, $operator): int|float {
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            $next = $operation($left, $right);
            if (is_int($next) || is_float($next)) {
                return $next;
            }

            throw new \TypeError('Arithmetic result must be a number');
        }

        throw new \TypeError(sprintf(
            'Unsupported operand types: %s %s %s',
            get_debug_type($left),
            $operator,
            get_debug_type($right),
        ));
    });
    if (!is_int($result) && !is_float($result)) {
        throw new \TypeError('Arithmetic result must be a number');
    }

    return $result;
}

function textReduce(array $values): string
{
    $result = reduce($values, static function (mixed $left, mixed $right): string {
        if (!(is_scalar($left) || $left instanceof \Stringable || $left === null)) {
            throw new \Error(sprintf('Object of class %s could not be converted to string', get_debug_type($left)));
        }
        if (!(is_scalar($right) || $right instanceof \Stringable || $right === null)) {
            throw new \Error(sprintf('Object of class %s could not be converted to string', get_debug_type($right)));
        }

        return (string) $left . (string) $right;
    });
    if (!is_string($result)) {
        throw new \TypeError('Concatenation result must be a string');
    }

    return $result;
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
    $add('abs', static function (mixed $x): int|float {
        if (!is_int($x) && !is_float($x)) {
            throw new \TypeError(sprintf('abs(): Argument #1 ($num) must be of type int|float, %s given', get_debug_type($x)));
        }

        return abs($x);
    });
    $add('+', static fn(mixed ...$x): int|float => numericReduce($x, static fn(int|float $a, int|float $b): int|float => $a + $b, '+'));
    $add('-', static fn(mixed ...$x): int|float => numericReduce($x, static fn(int|float $a, int|float $b): int|float => $a - $b, '-'));
    $add('*', static fn(mixed ...$x): int|float => numericReduce($x, static fn(int|float $a, int|float $b): int|float => $a * $b, '*'));
    $add('/', static fn(mixed ...$x): int|float => numericReduce($x, static fn(int|float $a, int|float $b): int|float => $a / $b, '/'));
    $add('max', static fn(mixed ...$x): int|float => numericReduce($x, static fn(int|float $a, int|float $b): int|float => max($a, $b), 'max'));
    $add('min', static fn(mixed ...$x): int|float => numericReduce($x, static fn(int|float $a, int|float $b): int|float => min($a, $b), 'min'));
    $add('mod', static fn(mixed ...$x): int|float => numericReduce($x, static fn(int|float $a, int|float $b): int|float => $a % $b, '%'));
    $add('++', static fn(mixed ...$x): string => textReduce($x));
    $add('car', static function (array $x): Symbol|array|string|int|float|bool {
        $item = current($x);
        if (is_array($item) || is_string($item) || is_int($item) || is_float($item) || is_bool($item) || $item instanceof Symbol) {
            return $item;
        }

        throw new \TypeError('car expects a scalar, symbol, or list');
    });
    $add('cdr', static fn(array $x): array => array_slice($x, 1));
    $add('cons', static fn($a, $b): array => array_merge([$a], (array) $b));
    $add('class', static fn(string $className): string => match (class_exists($className)) {
        true => $className,
        false => throw new \Exception(sprintf('class name "%s" not found', $className)),
    });
    $add('php', static function (string|object $fn, mixed ...$args): mixed {
        if ($fn instanceof Symbol) {
            $fn = (string) $fn;
        }
        if (is_string($fn) && function_exists($fn)) {
            $function = new \ReflectionFunction($fn);
            return $function->invoke(...adaptPhpArgs($function, phpArgs($args)));
        }

        $method = array_shift($args);
        $phpArgs = phpArgs($args);
        if ($method === 'new') {
            if (!is_string($fn) || !class_exists($fn)) {
                throw new \Exception(sprintf('class name "%s" not found', is_string($fn) ? $fn : get_debug_type($fn)));
            }
            $class = new \ReflectionClass($fn);
            $constructor = $class->getConstructor();
            if ($constructor !== null) {
                $phpArgs = adaptPhpArgs($constructor, $phpArgs);
            }

            return $class->newInstance(...$phpArgs);
        }
        if (is_object($fn)) {
            if (!method_exists($fn, (string) $method) && is_callable($fn)) {
                return $fn($method, ...$phpArgs);
            }
            $reflection = new \ReflectionMethod($fn, (string) $method);

            return $reflection->invoke($fn, ...adaptPhpArgs($reflection, $phpArgs));
        }

        if (!class_exists($fn)) {
            throw new \Exception(sprintf('class name "%s" not found', $fn));
        }
        $reflection = new \ReflectionMethod($fn, (string) $method);

        return $reflection->invoke(null, ...adaptPhpArgs($reflection, $phpArgs));
    });

    return $storage;
}
