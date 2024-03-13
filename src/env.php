<?php

declare(strict_types=1);

namespace Che\SimpleLisp\Env;

use Che\SimpleLisp\HashMap;
use Che\SimpleLisp\HashMapInterface;
use Che\SimpleLisp\Symbol;

use function Che\SimpleLisp\Eval\reduce;

function _defaultEnv(): HashMapInterface
{
    $storage = new HashMap();
    $storage->put(new Symbol('='), fn ($a, $b) => $a == $b);
    $storage->put(new Symbol('>'), fn ($a, $b) => $a > $b);
    $storage->put(new Symbol('<'), fn ($a, $b) => $a < $b);
    $storage->put(new Symbol('>='), fn ($a, $b) => $a >= $b);
    $storage->put(new Symbol('<='), fn ($a, $b) => $a <= $b);
    $storage->put(new Symbol('<='), fn ($a, $b) => $a <= $b);
    $storage->put(new Symbol('not'), fn (bool $x) => !$x);
    $storage->put(new Symbol('abs'), fn ($x) => abs($x));
    $storage->put(new Symbol('+'), fn (...$x) => reduce($x, fn ($a, $b) => $a + $b));
    $storage->put(new Symbol('-'), fn (...$x) => reduce($x, fn ($a, $b) => $a - $b));
    $storage->put(new Symbol('*'), fn (...$x) => reduce($x, fn ($a, $b) => $a * $b));
    $storage->put(new Symbol('/'), fn (...$x) => reduce($x, fn ($a, $b) => $a / $b));
    $storage->put(new Symbol('max'), fn (...$x) => reduce($x, fn ($a, $b) => max($a, $b)));
    $storage->put(new Symbol('min'), fn (...$x) => reduce($x, fn ($a, $b) => min($a, $b)));
    $storage->put(new Symbol('mod'), fn (...$x) => reduce($x, fn ($a, $b) => $a % $b));

    $storage->put(new Symbol('++'), fn (...$x) => reduce($x, fn ($a, $b) => $a . $b));
    $storage->put(new Symbol('print'), static function (...$x): null { // todo: непонятно как это проверить
        $str = reduce($x, fn ($a, $b) => $a . $b);
        echo $str;
        return null;
    });

    return $storage;
}
