<?php

declare(strict_types=1);

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

while (true) {
    $command = readline('?> ');

    if(empty(str_replace(' ', '', $command))) {
        continue;
    }

    readline_add_history($command);

    if($command[0] === ':') {
        switch ($command) {
            case ':quit':
            case ':q':
                break 2;
            default:
                writeMessage(sprintf("Unknown command '%s'\n", $command), MessageType::WARNING);
                continue 2;
        }
    }

    try {
        $result = _eval(parseTokens(tokenize($command)), $env);
        $messageType = MessageType::REGULAR;
    } catch (\Throwable $e) {
        $result = sprintf('%s', $e->getMessage());
        $messageType = MessageType::ERROR;
    }

    writeMessage(toString($result), $messageType);
}
exit(0);


function toString(mixed $value): string
{
    if(is_array($value)) {
        $acc = '';
        foreach ($value as $item) {
            $acc .= ' ';
            $acc .= toString($item);
        }

        return sprintf('(%s)', substr($acc, 1));
    }

    return (string)$value;
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
