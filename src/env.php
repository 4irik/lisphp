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

    $add('=', static fn ($a, $b): bool => $a == $b);
    $add('>', static fn ($a, $b): bool => $a > $b);
    $add('<', static fn ($a, $b): bool => $a < $b);
    $add('>=', static fn ($a, $b): bool => $a >= $b);
    $add('<=', static fn ($a, $b): bool => $a <= $b);
    $add('not', static fn (bool $x): bool => !$x);
    $add('abs', static fn ($x): int|float => abs($x));
    $add('+', static fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => $a + $b));
    $add('-', static fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => $a - $b));
    $add('*', static fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => $a * $b));
    $add('/', static fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => $a / $b));
    $add('max', static fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => max($a, $b)));
    $add('min', static fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => min($a, $b)));
    $add('mod', static fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => $a % $b));
    $add('++', static fn (...$x): string => reduce($x, fn ($a, $b): string => $a . $b));
    $add('car', static fn (array $x): Symbol|array|string|int|float|bool => current($x));
    $add('cdr', static fn (array $x): array => array_slice($x, 1));
    $add('cons', static fn ($a, $b): array => array_merge([$a], (array)$b));
    $add('class', static fn (string $className): string => match(class_exists($className)) {
        true => $className,
        false => throw new \Exception(sprintf('class name "%s" not found', $className)),
    });
    $add('php', static function (string|object $fn, ...$args): mixed {
        if(is_string($fn) && function_exists($fn)) {
            return $fn(...$args);
        }

        $method = array_shift($args);
        if($method === 'new') {
            $fn = new readonly class ($fn) {
                public function __construct(private string $className)
                {
                }

                public function new(...$args): object
                {
                    return new $this->className(...$args);
                }
            };
        }
        // вызов invocable-объекта
        if(!method_exists($fn, (string)$method) && is_callable($fn)) {
            return $fn($method, ...$args);
        }

        return [$fn, $method](...$args);
    });

    return $storage;
}
