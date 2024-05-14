<?php

declare(strict_types=1);

namespace Che\SimpleLisp\Parse;

use Che\SimpleLisp\Symbol;

/**
 * @phpstan-type Atom scalar|bool|Symbol
 * @phpstan-type Command Atom|iterable
 */

/**
 * @param string $program
 * @return iterable<string>
 */
function tokenize(string $program): iterable
{
    // bad hack :(
    $program = str_replace('(-', '||||', $program);
    $program = str_replace('-', '____', $program);
    $program = str_replace('||||', '(-', $program);
    $program = str_replace('____', '-', $program);

    // удаляем комментарии
    $program = preg_replace('/^;.*/m', '', $program);

    preg_match_all('/\(|\)|[-\/\\\_!?\w+]{2,}|\w+|\"[^"]*\"|[-\'=><?!*\/+\\\]+/m', $program, $m, PREG_UNMATCHED_AS_NULL);

    return array_values(
        array_filter(
            array_map(
                fn (string $item): string => trim($item),
                $m[0]
                //                explode(
                //                    ' ',
                //                    str_replace(['(', ')', '\''], [' ( ', ' ) ', ' \' '], $program)
                //                )
            ),
            fn ($val) => match ($val) {
                '0' => true,
                "\n", "\r", "\t", "\r\n" => false,
                default => !in_array(substr($val, 0, 1), ["\n", "\r", "\t"]) && !empty($val)
            }
        )
    );
}

/**
 * @return Atom
 */
function _atom(string $token): int|float|bool|string|Symbol
{
    if(is_numeric($token)) {
        return is_int($token) ? (int)$token : (float) $token;
    }

    if($token === 'true') {
        return true;
    }
    if($token === 'false') {
        return false;
    }

    if($token[0] === '"') { // todo: проверить что заканчивается на '"'
        return substr($token, 1, -1);
    }

    return new Symbol($token === '\'' ? 'quote' : $token);
}

/**
 * @param iterable<Atom> $tokens
 * @return Command
 * @throws \Exception
 */
function parseTokens(iterable $tokens): int|float|bool|string|Symbol|iterable
{
    if(!$tokens instanceof \SplDoublyLinkedList) {
        $buf = new \SplDoublyLinkedList();
        foreach ($tokens as $t) {
            $buf->push($t);
        }
        $tokens = $buf;
    }

    return _parseTokens($tokens);
}

/**
 * @param \SplDoublyLinkedList<Atom> $tokens
 * @return Command
 * @throws \Exception
 */
function _parseTokens(\SplDoublyLinkedList $tokens): int|float|bool|string|Symbol|iterable
{
    if($tokens->isEmpty()) {
        throw new \Exception('Unexpected EOF');
    }
    $token = $tokens->shift();
    if($token === ')') {
        throw new \Exception('Unexpected ")"');
    }
    if($token === '(') {
        $elements = [];
        while ($tokens[0] !== ')') {
            $elements[] = _parseTokens($tokens);
            if($tokens->isEmpty()) {
                throw new \Exception('Unexpected EOF');
            }
        }
        $tokens->shift(); // remove ')'
        return $elements;
    }

    if($token === '\'') {
        return [
            _atom($token),
            _parseTokens($tokens),
        ];
    }

    return _atom($token);
}
