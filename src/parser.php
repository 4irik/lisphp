<?php

declare(strict_types=1);

namespace Che\SimpleLisp\Parse;

use Che\SimpleLisp\Symbol;

/**
 * @param string $program
 * @return iterable<string>
 */
function tokenize(string $program): iterable
{
    return array_values(
        array_filter(
            explode(
                ' ',
                str_replace(['(', ')', '\''], [' ( ', ' ) ', ' \' '], $program)
            ),
            fn ($val) => match ($val) {
                '0' => true,
                "\n", "\r", "\t", "\r\n" => false,
                default => !in_array(substr($val, 0, 1), ["\n", "\r", "\t"]) && !empty($val)
            }
        )
    );
}

function _atom(string $token): int|float|bool|string|Symbol
{
    if(is_numeric($token)) {
        return is_int($token) ? (int)$token : (float) $token;
    }

    if($token === '#t') {
        return true;
    }
    if($token === '#f') {
        return false;
    }

    if($token[0] === '"') { // todo: проверить что заканчивается на '"'
        return substr($token, 1, -1);
    }

    return new Symbol($token === '\'' ? 'quote' : $token);
}

function parseTokens(\SplDoublyLinkedList|array  $tokens): int|float|bool|string|Symbol|iterable
{
    if(is_array($tokens)) {
        $buf = new \SplDoublyLinkedList();
        foreach ($tokens as $t) {
            $buf->push($t);
        }
        $tokens = $buf;
    }

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
            $elements[] = parseTokens($tokens);
            if($tokens->isEmpty()) {
                throw new \Exception('Unexpected EOF');
            }
        }
        $tokens->shift(); // remove ')'
        return $elements;
    }

    return _atom($token);
}
