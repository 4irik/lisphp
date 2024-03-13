<?php

declare(strict_types=1);

error_reporting(E_ALL);

use function Che\SimpleLisp\Env\_defaultEnv;
use function Che\SimpleLisp\Eval\_eval;
use function Che\SimpleLisp\Parse\parseTokens;
use function Che\SimpleLisp\Parse\tokenize;

require_once './vendor/autoload.php';

enum MessageType: string
{
    case REGULAR = '';
    case INFO = 'Info';
    case WARNING = 'Warning';
    case ERROR = 'Error';
}

$env = _defaultEnv();

// todo: подгрузка истории команд

while (true) {
    $lengthToken = false;

    $command = readline('?> ');

    if(empty(str_replace(' ', '', $command))) {
        continue;
    }

    readline_add_history($command);

    try {
        if($command[0] === ':') {
            switch (true) {
                case $command === ':quit':
                case $command === ':q':
                    break 2;
                case $command === ':env':
                case $command === ':e':
                    writeMessage(hmToString($env), MessageType::INFO);
                    continue 2;
                case str_starts_with($command, ':load'):
                case str_starts_with($command, ':l'):
                    $fileName = str_replace([':load ', ':l '], '', $command);
                    if(!file_exists($fileName)) {
                        throw new Exception(sprintf('File Not Found: "%s"', $fileName));
                    }
                    $command = file_get_contents($fileName);
                    break;
                case str_starts_with($command, ':tokens'):
                    $lengthToken = true;
                    // no break
                case str_starts_with($command, ':t'):
                    $command = trim(substr($command, strlen($lengthToken ? ':tokens' : ':t')));
                    writeMessage(toString(tokenize($command)), MessageType::INFO);
                    continue 2;
                case str_starts_with($command, ':parsed_tokens'):
                    $lengthToken = true;
                    // no break
                case str_starts_with($command, ':pt'):
                    $command = trim(substr($command, strlen($lengthToken ? ':parsed_tokens' : ':pt')));
                    writeMessage(toString(parseTokens(tokenize($command))), MessageType::INFO);
                    continue 2;
                    // todo: очистка истории команд
                default:
                    writeMessage(sprintf("Unknown command '%s'\n", $command), MessageType::WARNING);
                    continue 2;
            }
        }

        $result = _eval(parseTokens(tokenize($command)), $env);
        $messageType = $result === null ? MessageType::INFO : MessageType::REGULAR;
        $result = $result ?? 'OK';
    } catch (\Throwable $e) {
        $result = sprintf("%s\n%s", $e->getMessage(), $e->getTraceAsString());
        $messageType = MessageType::ERROR;
    }

    writeMessage(toString($result), $messageType);
}
exit(0);


function toString(mixed $value): string
{
    if(is_iterable($value)) {
        $acc = '';
        foreach ($value as $item) {
            $acc .= ' ';
            $acc .= toString($item);
        }

        return sprintf('(%s)', substr($acc, 1));
    }

    if(is_string($value)) {
        $value = sprintf('"%s"', $value);
    }

    return str_replace(["\r", "\n", "\t"], ['\r', '\n', '\t'], (string)$value);
}

function decorateMessage(string $message, MessageType $type = MessageType::REGULAR): string
{
    [$color, $nc] = match ($type) {
        MessageType::REGULAR => [null, null],
        MessageType::INFO => ["\033[0;37m", "\033[0m"],
        MessageType::WARNING => ["\033[0;33m", "\033[0m"],
        MessageType::ERROR => ["\033[0;31m", "\033[0m"],
    };

    return implode('', array_filter([
        $color,
        ($type === MessageType::REGULAR ? null : $type->value . '! '),
        $nc,
        $message
    ]));
}

function writeMessage(string $message, MessageType $type): void
{
    echo decorateMessage($message, $type). "\n";
}

function hmToString(\Traversable $map): string
{
    $acc = [];
    foreach ($map as $key => $value) {
        if(is_callable($value)) {
            continue;
        }

        $acc[] = sprintf("\033[0;32m%s\033[0m => \033[0;35m%s\033[0m", $key, toString($value));
    }

    return sprintf("=== Global env ===\n%s", implode("\n", $acc));
}
