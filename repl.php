<?php

declare(strict_types=1);

error_reporting(E_ALL);

use Che\SimpleLisp\HashMap;
use Che\SimpleLisp\HashMapInterface;
use Che\SimpleLisp\Symbol;

use function Che\SimpleLisp\Env\_defaultEnv;
use function Che\SimpleLisp\Eval\_eval;
use function Che\SimpleLisp\Parse\parseTokens;
use function Che\SimpleLisp\Parse\tokenize;

require_once './vendor/autoload.php';

const HISTORY_FILE_PATH = '.repl_history';
const STD_LIB = 'std_lib.lisp';

enum OutputMode: string
{
    case ESCAPE = '?> ';
    case UNESCAPE = 'unescape?> ';
}

class ReplMode
{
    private ?OutputMode $disposableMode = null;

    public function __construct(private OutputMode $mode)
    {
    }

    public function setMode(OutputMode $mode): self
    {
        $this->mode = $mode;
        return $this;
    }

    public function setDisposableMode(OutputMode $mode): self
    {
        $this->disposableMode = $mode;
        return $this;
    }

    public function getMode(): OutputMode
    {
        if($this->disposableMode !== null) {
            $result = $this->disposableMode;
            $this->disposableMode = null;

            return $result;
        }

        return $this->mode;
    }
}

enum MessageType: string
{
    case REGULAR = '';
    case INFO = 'Info';
    case WARNING = 'Warning';
    case ERROR = 'Error';
}
$env = new class (_defaultEnv()) extends HashMap {
    #[\Override] public function getIterator(): \Traversable
    {
        $iterate = static function (HashMapInterface $map, int $level = 0, int $number = 0) use (&$iterate): \Generator {
            yield [$level, $number] => $level === 0 ? $map->iterateMap() : $map->getIterator();

            $level += 1;
            foreach ($map->childList() as $objItem) {
                foreach ($iterate($objItem, $level, $number) as $key => $item) {
                    yield $key => $item;
                }
                $number += 1;
            }
        };

        return $iterate($this);
    }

    private function iterateMap(): \Traversable
    {
        return parent::getIterator();
    }
};

$std_lib = file_get_contents(implode(DIRECTORY_SEPARATOR, ['.', 'src', STD_LIB]));
__eval($std_lib, $env);

if(file_exists(HISTORY_FILE_PATH)) {
    readline_read_history(HISTORY_FILE_PATH);
}

writeMessage("\n" . str_repeat('=', 53));
writeMessage("Наберите \033[0;33m:help\033[0m для просмотра списка доступных комманд");
writeMessage(str_repeat('=', 53) . "\n");
$replMode = new ReplMode(OutputMode::ESCAPE);
while (true) {
    $command = readline($replMode->getMode()->value);

    if(empty(str_replace(' ', '', $command))) {
        continue;
    }

    readline_add_history($command);

    try {
        if($command[0] === ':') {
            switch (true) {
                // режим экранированного вывода
                case $command === ':esc':
                    $replMode->setMode(OutputMode::ESCAPE);
                    continue 2;
                    // режим экранированного вывода
                case $command === ':unesc':
                    $replMode->setMode(OutputMode::UNESCAPE);
                    continue 2;
                    // выход
                case $command === ':quit':
                case $command === ':q':
                    break 2;
                    // переменные окружения
                case str_starts_with($command, ':e'):
                    $c = explode(' ', $command);
                    $toInt = static function (array $arr, int $key): ?int {
                        return isset($arr[$key])
                            ? (int)$arr[$key]
                            : null;
                    };
                    writeMessage(hmToString($env, $toInt($c, 1), $toInt($c, 2)), MessageType::INFO);
                    continue 2;
                    // очистка истории
                case $command === ':ch':
                    readline_clear_history();
                    @unlink(HISTORY_FILE_PATH);
                    continue 2;
                    // загрузка файла
                case str_starts_with($command, ':load'):
                case str_starts_with($command, ':l'):
                    $fileName = str_replace([':load ', ':l '], '', $command);
                    if(!file_exists($fileName)) {
                        throw new Exception(sprintf('File Not Found: "%s"', $fileName));
                    }
                    $command = file_get_contents($fileName);
                    writeMessage("OK");
                    break;
                    // показать токены команды
                case str_starts_with($command, ':t'):
                    $command = trim(substr($command, 2));
                    writeMessage(toString(tokenize($command)), MessageType::INFO);
                    continue 2;
                    // показать "скомпилированную" команду
                case str_starts_with($command, ':pt'):
                    $command = trim(substr($command, 3));
                    writeMessage(toString(parseTokens(tokenize($command))), MessageType::INFO);
                    continue 2;
                case $command === ':gc':
                    gc_collect_cycles();
                    writeMessage('GC launched', MessageType::INFO);
                    continue 2;
                case $command === ':help':
                    writeMessage("Список доступных комманд:");
                    writeMessage("\033[0;32m:esc\033[0m - режим экранированного вывода в \033[0;33mREPL\033[0m");
                    writeMessage("\033[0;32m:unesc \033[0m - режим неэкранированного вывода в \033[0;33mREPL\033[0m");
                    writeMessage("\033[0;32m:q, :quit \033[0m - выход");
                    writeMessage("\033[0;32m:e\033[0m - рекурсивный вывод переменных окружения - \033[0;33m:e 1 2\033[0m, где \033[0;33m1\033[0m - уровень вложенности, \033[0;33m2\033[0m - порядковый номер на уровне");
                    writeMessage("\033[0;32m:l, :load\033[0m - загрузка и исполнение файла - \033[0;33m:load programs/fib\033[0m");
                    writeMessage("\033[0;32m:t\033[0m - показать токены строки - \033[0;33m:t (def a 123)\033[0m");
                    writeMessage("\033[0;32m:pt\033[0m - показать сформированную команду из строки - \033[0;33m:pt (def a 123)\033[0m");
                    writeMessage("\033[0;32m:gc\033[0m - принудительный запуск сборщика мусора");
                    writeMessage("\033[0;32m:help\033[0m - отображение этой справки");
                    continue 2;
                default:
                    writeMessage(sprintf("Unknown command '%s'\n", $command), MessageType::WARNING);
                    continue 2;
            }
        }

        $result = __eval($command, $env);
        $messageType = $result === null ? MessageType::INFO : MessageType::REGULAR;
        $result = $result ?? 'OK';
    } catch (\Throwable $e) {
        $prePrint = static function (string $s, int $maxLength): string {
            $length = strlen($s);
            return substr($s, 0, $maxLength) . ($length > $maxLength ? ' ...' : '');
        };
        $result = sprintf(
            "%s\nTrace:\n================\n%s",
            $prePrint($e->getMessage(), 1000),
            $prePrint($e->getTraceAsString(), 5000)
        );
        $messageType = MessageType::ERROR;
        $replMode->setDisposableMode(OutputMode::UNESCAPE);
    }

    writeMessage(toString($result, $replMode->getMode()), $messageType);
}
readline_write_history(HISTORY_FILE_PATH);
exit(0);


function toString(mixed $value, OutputMode $mode = OutputMode::ESCAPE): string
{
    if(is_iterable($value)) {
        $acc = '';
        foreach ($value as $item) {
            $acc .= ' ';
            $acc .= toString($item);
        }

        return sprintf('(%s)', substr($acc, 1));
    }

    if(is_bool($value)) {
        $value = new Symbol($value ? 'true' : 'false');
    }

    if(is_object($value) && (!str_starts_with(get_class($value), 'Che\SimpleLisp'))) {
        $value = sprintf('class %s: %s', get_class($value), substr(serialize($value), 0, 100));
    }

    if($mode === OutputMode::UNESCAPE) {
        return (string)$value;
    }

    if(is_string($value)) {
        $value = sprintf('"%s"', $value);
    }

    return str_replace(["\r", "\n", "\t"], ['\r', '\n', '\t'], (string)$value);
}

function decorateMessage(string $message, MessageType $type): string
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

function writeMessage(string $message, MessageType $type = MessageType::REGULAR): void
{
    echo decorateMessage($message, $type). "\n";
}

function hmToString(\Traversable $map, ?int $showLevel = null, ?int $showNumber = null): string
{
    $hideEnv = fn (?int $filterArg, int $filteredArg): bool => $filterArg !== null && $filterArg !== $filteredArg;

    $acc = [];
    foreach ($map as $hmKey => $iterable) {
        [$level, $number] = $hmKey;

        if($hideEnv($showLevel, $level)) {
            continue;
        }
        if($hideEnv($showNumber, $number)) {
            continue;
        }

        $buf = [];
        foreach ($iterable as $key => $value) {
            $buf[] = sprintf("\033[0;32m%s\033[0m => \033[0;35m%s\033[0m", $key, toString($value));
        }

        $acc[] = sprintf("===level: %d | number: %d===\n%s", $level, $number, implode("\n", $buf));
    }

    return implode("\n", $acc);
}

function __eval(string $command, HashMapInterface $env): mixed
{
    $tokens = new \SplDoublyLinkedList();
    foreach (tokenize($command) as $tokenItem) {
        $tokens->push($tokenItem);
    }

    $result = null;
    while (!$tokens->isEmpty()) {
        $result = _eval(parseTokens($tokens), $env);
    }

    return $result;
}
