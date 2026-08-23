# Quillstack Dotenv Expand

[![Tests](https://github.com/quillstack/dotenv-expand/actions/workflows/tests.yml/badge.svg)](https://github.com/quillstack/dotenv-expand/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/quillstack/dotenv-expand.svg)](https://packagist.org/packages/quillstack/dotenv-expand)
[![Downloads](https://img.shields.io/packagist/dt/quillstack/dotenv-expand.svg)](https://packagist.org/packages/quillstack/dotenv-expand)
[![PHP Version](https://img.shields.io/packagist/php-v/quillstack/dotenv-expand)](https://packagist.org/packages/quillstack/dotenv-expand)
[![StyleCI](https://github.styleci.io/repos/1344010795/shield?branch=main)](https://github.styleci.io/repos/1344010795?branch=main)
[![CodeFactor](https://www.codefactor.io/repository/github/quillstack/dotenv-expand/badge)](https://www.codefactor.io/repository/github/quillstack/dotenv-expand)
[![Quality Gate](https://sonarcloud.io/api/project_badges/measure?project=quillstack_dotenv-expand&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=quillstack_dotenv-expand)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=quillstack_dotenv-expand&metric=coverage)](https://sonarcloud.io/summary/new_code?id=quillstack_dotenv-expand)
[![Maintainability](https://sonarcloud.io/api/project_badges/measure?project=quillstack_dotenv-expand&metric=sqale_rating)](https://sonarcloud.io/summary/new_code?id=quillstack_dotenv-expand)
[![Reliability](https://sonarcloud.io/api/project_badges/measure?project=quillstack_dotenv-expand&metric=reliability_rating)](https://sonarcloud.io/summary/new_code?id=quillstack_dotenv-expand)
[![Security](https://sonarcloud.io/api/project_badges/measure?project=quillstack_dotenv-expand&metric=security_rating)](https://sonarcloud.io/summary/new_code?id=quillstack_dotenv-expand)
[![License](https://img.shields.io/packagist/l/quillstack/dotenv-expand)](https://github.com/quillstack/dotenv-expand/blob/main/LICENSE)

Values built from other values in a `.env` file, resolved strictly: an unknown name is an error,
never an empty string.

[quillstack/dotenv](https://github.com/quillstack/dotenv) reads the file. This resolves what one
value says about another, and it is a separate package because that is a separate decision —
take it and you get `${SOMETHING}`; leave it and you pay nothing for a feature you do not use.

### Requirements

- PHP 8.1 or newer

### Installation

```shell
composer require quillstack/dotenv-expand
```

### Usage

```text
BASE=https://example.org
API=${BASE}/v1
PORT=5432
DSN=pgsql:host=localhost;port=${PORT}
```

```php
use Quillstack\DotenvExpand\Expand;

(new Expand('.env'))->load();
```

```php
env('API');   // 'https://example.org/v1'
env('DSN');   // 'pgsql:host=localhost;port=5432'
env('PORT');  // 5432, still a number
```

`parse()` reads without touching the environment, the same way round as the package underneath:

```php
$values = (new Expand('.env'))->parse();
```

An already-built reader can be handed over instead of a path:

```php
(new Expand(new Dotenv('.env')))->load();
```

### The rules, and why they are narrow

`.env` has no specification. Every library that expands `${SOMETHING}` has picked its own rules,
and they disagree with each other about nearly every case that is not the obvious one. On one
case they agree, and it is the wrong one.

#### An unknown name is an error

```text
PASSWORD=pa${ss}word
```

```text
UndefinedVariableException:
The value of `PASSWORD` uses `${ss}`, and `ss` is not defined above it in the file or in
the environment.
```

Every other implementation compared against — `symfony/dotenv`, `python-dotenv`, `dotenv-expand`
for Node, Ruby's `dotenv`, Dart's `dotenv`, `dotenv-c` — returns **`paword`**. No error, nothing
empty, and an application starting with a password nobody chose. **That is not a feature to
match.**

#### Only names already known

A name is resolved from what the file has already said, or from what the environment already
held. Not from further down the file:

```text
FORWARD=${LATER}/x
LATER=defined-after
```

That is an error too. Otherwise a value depends on the order the file happens to be written in —
and the libraries which allow it disagree about what it means: one resolves it, one leaves the
text, one throws.

The environment this process started with counts as already known:

```text
BIN=${HOME}/bin
```

#### Only `${NAME}`, never `$NAME`

```text
PASSWORD=hunter2$SOMETHING
```

That is a password, and it comes back whole. Half the implementations of this idea expand a bare
`$NAME` and half do not; passwords are full of dollar signs, and one quietly cut short is the
worse of the two mistakes available.

#### `\${` is a literal `${`

```text
PRICE=\${9.99}
```

```php
env('PRICE');   // '${9.99}'
```

Four of the libraries compared against get this wrong the same way: they keep the backslash
*and* expand, giving `\https://example.org/v3`.

#### No `${NAME:-default}`

A shell expression, not a `.env` one. Ruby and Dart return the text `:-fallback}` for it, which
is neither the default nor an error. A default belongs where it can be read:

```php
env('MISSING', 'fallback');
```

### Types survive

`quillstack/dotenv` reads `false` as a boolean and `5432` as a number, and a name standing for
one of those still stands for it:

```text
DEBUG=false
MESSAGE=debug is ${DEBUG} here
```

```php
env('MESSAGE');   // 'debug is false here'
```

### Tests

```shell
composer test
composer stan
```

### The rest of Quillstack

This is one component of [Quillstack](https://github.com/quillstack), a PHP framework which is
as simple to use as it is strict about what it does.

- [quillstack/dotenv](https://github.com/quillstack/dotenv) — reading the file
- [quillstack/config](https://github.com/quillstack/config) — settings built on top of it

### License

MIT — see [LICENSE](LICENSE).
