<?php

declare(strict_types=1);

namespace Che\SimpleLisp\Parse;

use Che\SimpleLisp\Form;
use Che\SimpleLisp\Located;
use Che\SimpleLisp\RuntimeError;
use Che\SimpleLisp\Symbol;

/**
 * @phpstan-type Atom scalar|bool|Symbol|\Che\SimpleLisp\Located
 * @phpstan-type Command Atom|iterable
 */

final readonly class Token implements \Stringable
{
    public function __construct(
        public string $text,
        public int $line,
        public int $column,
    ) {}

    public function __toString(): string
    {
        return $this->text;
    }
}

final class SyntaxError extends \Exception
{
    public function __construct(
        string $message,
        public readonly int $atLine,
        public readonly int $atColumn,
        public readonly string $sourceLine = '',
    ) {
        parent::__construct($message);
    }
}

function formatSyntaxError(SyntaxError $error, string $source = ''): string
{
    return formatLocation($error->getMessage(), $error->atLine, $error->atColumn, $error->sourceLine, $source);
}

function formatRuntimeError(RuntimeError $error): string
{
    return formatLocation($error->getMessage(), $error->atLine, $error->atColumn, $error->sourceLine);
}

function formatLocation(
    string $message,
    int $atLine,
    int $atColumn,
    string $sourceLine,
    string $source = '',
): string {
    $text = $sourceLine;
    if ($text === '' && $source !== '') {
        $text = lineText($source, $atLine);
    }

    $caret = str_repeat(' ', max(0, $atColumn - 1)) . '^';

    return sprintf("%d:%d: %s\n%s\n%s", $atLine, $atColumn, $message, $text, $caret);
}

function lineText(string $source, int $line): string
{
    $lines = preg_split("/\r\n|\n|\r/", $source) ?: [];

    return $lines[$line - 1] ?? '';
}

/**
 * @return array{int, int}
 */
function sourcePosition(string $program, int $offset): array
{
    $offset = min($offset, strlen($program));
    $prefix = substr($program, 0, $offset);
    $line = substr_count($prefix, "\n") + 1;
    $break = strrpos($prefix, "\n");
    $column = $break === false ? $offset + 1 : $offset - $break;

    return [$line, $column];
}

/**
 * @return list<Token>
 */
function tokenize(string $program): array
{
    // bad hack :(
    $program = str_replace('(-', '||||', $program);
    $program = str_replace('-', '____', $program);
    $program = str_replace('||||', '(-', $program);
    $program = str_replace('____', '-', $program);

    $program = preg_replace_callback(
        '/;.*$/m',
        static fn (array $match): string => str_repeat(' ', strlen($match[0])),
        $program,
    ) ?? $program;

    preg_match_all(
        '/\(|\)|[-\/\\\_!?\.\w+]{2,}|\w+|\"[^"]*\"|[-\'=><?!*\/+\\\]+/m',
        $program,
        $matches,
        PREG_OFFSET_CAPTURE,
    );

    $tokens = [];
    $cursor = 0;
    foreach ($matches[0] as [$text, $offset]) {
        rejectUnparsed($program, $cursor, $offset);
        [$line, $column] = sourcePosition($program, $offset);
        $tokens[] = new Token($text, $line, $column);
        $cursor = $offset + strlen($text);
    }
    rejectUnparsed($program, $cursor, strlen($program));

    return $tokens;
}

function rejectUnparsed(string $program, int $from, int $to): void
{
    for ($i = $from; $i < $to; $i++) {
        if (ctype_space($program[$i])) {
            continue;
        }

        [$line, $column] = sourcePosition($program, $i);
        throw new SyntaxError(sprintf('Unexpected character "%s"', $program[$i]), $line, $column);
    }
}

/**
 * @return Atom
 */
function _atom(string $token): int|float|bool|string|Symbol
{
    if (is_numeric($token)) {
        return is_int($token) ? (int) $token : (float) $token;
    }

    if ($token === 'true') {
        return true;
    }
    if ($token === 'false') {
        return false;
    }

    if ($token[0] === '"') { // todo: проверить что заканчивается на '"'
        return substr($token, 1, -1);
    }

    return new Symbol($token === '\'' ? 'quote' : $token);
}

/**
 * @param iterable<Atom> $tokens
 * @return Command
 * @throws \Exception
 */
function parseTokens(iterable $tokens, ?string $source = null): int|float|bool|string|Symbol|iterable|Located
{
    if (!$tokens instanceof \SplDoublyLinkedList) {
        $buf = new \SplDoublyLinkedList();
        foreach ($tokens as $t) {
            $buf->push($t);
        }
        $tokens = $buf;
    }

    return _parseTokens($tokens, $source);
}

function tokenText(mixed $token): string
{
    return $token instanceof Token ? $token->text : (string) $token;
}

/**
 * @param \SplDoublyLinkedList<Atom> $tokens
 * @return Command
 * @throws \Exception
 */
function _parseTokens(\SplDoublyLinkedList $tokens, ?string $source = null): int|float|bool|string|Symbol|iterable|Located
{
    if ($tokens->isEmpty()) {
        throw unexpectedEof($source);
    }
    $token = $tokens->shift();
    $text = tokenText($token);
    if ($text === ')') {
        [$line, $column] = tokenSourcePosition($token);
        throw new SyntaxError('Unexpected ")"', $line, $column);
    }
    if ($text === '(') {
        $elements = [];
        while (!$tokens->isEmpty() && tokenText($tokens[0]) !== ')') {
            $elements[] = _parseTokens($tokens, $source);
        }
        if ($tokens->isEmpty()) {
            throw unexpectedEof($source);
        }
        $tokens->shift();
        if (!$token instanceof Token) {
            return $elements;
        }

        $snippet = $source === null ? '' : lineText($source, $token->line);

        return new Form($elements, $token->line, $token->column, $snippet);
    }

    if ($text === '\'') {
        $quoted = [
            _atom($text),
            _parseTokens($tokens, $source),
        ];
        if (!$token instanceof Token) {
            return $quoted;
        }

        $snippet = $source === null ? '' : lineText($source, $token->line);

        return new Form($quoted, $token->line, $token->column, $snippet);
    }

    $atom = _atom($text);
    if (!$token instanceof Token) {
        return $atom;
    }

    $snippet = $source === null ? '' : lineText($source, $token->line);

    return new Located($atom, $token->line, $token->column, $snippet);
}

function unexpectedEof(?string $source): SyntaxError
{
    if ($source === null || $source === '') {
        return new SyntaxError('Unexpected EOF', 1, 1);
    }

    [$line, $column] = sourcePosition($source, strlen($source));

    return new SyntaxError('Unexpected EOF', $line, $column);
}

/**
 * @return array{int, int}
 */
function tokenSourcePosition(mixed $token): array
{
    return $token instanceof Token ? [$token->line, $token->column] : [1, 1];
}
