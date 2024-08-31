# Simple Lisp-like interpreter written on PHP


[![asciicast](https://asciinema.org/a/Tgr77lcJ13cXYGOuaDB3lyrOK.svg)](https://asciinema.org/a/Tgr77lcJ13cXYGOuaDB3lyrOK)

## Requirements

- PHP 8.3

If you don't want to install PHP, you'll need::

- docker
- docker compose
- make

## Usage

### If you use docker

Run the following commands to build and install dependencies:

```shell
$ make build && make install
```

then start the REPL: 

```shell
$ make repl
```

### If you dont use docker

```shell
$ ./repl
```

or 

```shell
$ php repl.php
```

## Development

I highly recommended use docker (and docker compose) for a consistent development environment across collaborators. Makefiles simplify running frequently used commands.

Makefile defines various tasks:

- `make test-current` - run tests for the currently implemented functionality;
- `make test` - run all tests (including some currently marked as ignored);
- `make type-check` - run static type analysis (using phpstan) to detect potential issues;
- `make style-fix` - check and fix code style for consistency and readability;
- `make help` - display all available make command.

**Note:** Some tests are marked as *ignore* because they test functionality not yet implemented.

## TODO

1. [ ] Implement Tail Call Optimization (TCO) (https://en.wikipedia.org/wiki/Tail_call);
2. [ ] Errors should point to lisp code;
3. [ ] Support named arguments for interoperability with PHP.
