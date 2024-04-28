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
    $storage->put(new Symbol(Procedure::EQUAL->value), fn ($a, $b): bool => $a === $b);
    $storage->put(new Symbol(Procedure::GT->value), fn ($a, $b): bool => $a > $b);
    $storage->put(new Symbol(Procedure::LT->value), fn ($a, $b): bool => $a < $b);
    $storage->put(new Symbol(Procedure::GTOEQ->value), fn ($a, $b): bool => $a >= $b);
    $storage->put(new Symbol(Procedure::LTOEQ->value), fn ($a, $b): bool => $a <= $b);
    $storage->put(new Symbol(Procedure::NOT->value), fn (bool $x): bool => !$x);
    $storage->put(new Symbol(Procedure::ABS->value), fn ($x): int|float => abs($x));
    $storage->put(new Symbol(Procedure::SUM->value), fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => $a + $b));
    $storage->put(new Symbol(Procedure::SUB->value), fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => $a - $b));
    $storage->put(new Symbol(Procedure::MUL->value), fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => $a * $b));
    $storage->put(new Symbol(Procedure::DIV->value), fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => $a / $b));
    $storage->put(new Symbol(Procedure::MAX->value), fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => max($a, $b)));
    $storage->put(new Symbol(Procedure::MIN->value), fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => min($a, $b)));
    $storage->put(new Symbol(Procedure::MOD->value), fn (...$x): int|float => reduce($x, fn ($a, $b): int|float => $a % $b));

    $storage->put(new Symbol(Procedure::CONCAT->value), fn (...$x): string => reduce($x, fn ($a, $b): string => $a . $b));
    $storage->put(new Symbol(Procedure::PRINT->value), static function (...$x): null { // todo: непонятно как это проверить
        $str = reduce($x, fn ($a, $b) => $a . $b);
        echo $str;
        return null;
    });

    $storage->put(new Symbol(Procedure::CAR->value), fn (array $x): array => array_slice($x, 0, 1));
    $storage->put(new Symbol(Procedure::CDR->value), fn (array $x): array => array_slice($x, 1));
    $storage->put(new Symbol(Procedure::CONS->value), fn (...$x): array => $x);

    return $storage;
}
