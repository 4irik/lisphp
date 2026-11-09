<?php

declare(strict_types=1);

namespace Che\SimpleLisp;

use Che\SimpleLisp\Eval\OperandFailure;

use function Che\SimpleLisp\Eval\_eval;
use function Che\SimpleLisp\Eval\argumentPlace;

final readonly class Procedure implements \IteratorAggregate
{
    public function __construct(
        private string $name,
        private \Closure $closure,
    ) {}

    public function __invoke(HashMapInterface $env, ...$args): mixed
    {
        $places = [];
        $values = [];
        foreach ($args as $arg) {
            $places[] = argumentPlace($arg);
            $values[] = _eval($arg, $env);
        }

        $closure = $this->closure;
        try {
            return $closure(...$values);
        } catch (RuntimeError $error) {
            throw $error;
        } catch (OperandFailure $error) {
            $place = $places[$error->index] ?? null;
            if ($place === null) {
                throw $error->getPrevious() ?? $error;
            }

            throw new RuntimeError(
                $error->getMessage(),
                $place[0],
                $place[1],
                $place[2],
                $error->getPrevious() ?? $error,
            );
        } catch (\Throwable $error) {
            if (count($args) === 1 && $places[0] !== null) {
                [$line, $column, $sourceLine] = $places[0];
                throw new RuntimeError($error->getMessage(), $line, $column, $sourceLine, $error);
            }

            throw $error;
        }
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator([new Symbol('procedure'), new Symbol($this->name)]);
    }
}
