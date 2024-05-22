<?php

declare(strict_types=1);

namespace Che\SimpleLisp\Env;

use Che\SimpleLisp\HashMap;
use Che\SimpleLisp\HashMapInterface;
use Che\SimpleLisp\Procedure;
use Che\SimpleLisp\Symbol;

use function Che\SimpleLisp\Eval\reduce;

function _defaultEnv(): HashMapInterface
{
    $storage = new HashMap();

    $add = static function (string $name, callable $closure) use ($storage) {
        $storage->put(new Symbol($name), new Procedure($name, $closure));
    };

    $add('=', fn ($a, $b): bool => $a === $b);
    $add('>', fn ($a, $b): bool => $a > $b);
    $add('<', fn ($a, $b): bool => $a < $b);
    $add('>=', fn ($a, $b): bool => $a >= $b);
    $add('<=', fn ($a, $b): bool => $a <= $b);
    $add('not', fn (bool $x): bool => !$x);
    $add('abs', fn ($x): int|float => abs($x));
    $add('+', fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => $a + $b));
    $add('-', fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => $a - $b));
    $add('*', fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => $a * $b));
    $add('/', fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => $a / $b));
    $add('max', fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => max($a, $b)));
    $add('min', fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => min($a, $b)));
    $add('mod', fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => $a % $b));
    $add('++', fn (...$x): string => reduce($x, fn ($a, $b): string => $a . $b));
    $add('car', fn (array $x): Symbol|string|int|float|bool => current($x));
    $add('cdr', fn (array $x): array => array_slice($x, 1));
    $add('cons', fn ($a, $b): array => array_merge([$a], (array)$b));

    return $storage;
}
