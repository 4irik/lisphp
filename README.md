# Simple Lisp-like interpreter written on PHP


[![asciicast](https://asciinema.org/a/Tgr77lcJ13cXYGOuaDB3lyrOK.svg)](https://asciinema.org/a/Tgr77lcJ13cXYGOuaDB3lyrOK)

## Requirements

- PHP 8.5

If you don't want to install PHP, you'll need:

- docker
- make

`make` starts a one-off `php:8.5-alpine` container. There is no image to build.

## Usage

### With docker

Install dependencies, then start the REPL:

```shell
$ make install
$ make repl
```

### Without docker

```shell
$ ./repl
```

or

```shell
$ php repl.php
```

## Development

- `make test-current` — tests for the currently implemented functionality
- `make test` — all tests, including those marked as ignored
- `make analyze` — static analysis (Mago)
- `make lint` — linter (Mago)
- `make fmt` — format PHP (Mago)
- `make help` — list the commands

**Note:** Some tests are marked as *ignore* because they test functionality not yet implemented.

## TODO

1. [x] Implement Tail Call Optimization (TCO) (https://en.wikipedia.org/wiki/Tail_call);
2. [ ] Errors should point to lisp code;
3. [ ] Support named arguments for interoperability with PHP.
