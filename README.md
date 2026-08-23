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
value says about another, and it is a separate package because that is a separate decision.

## Why this exists

`.env` has no specification. Running the same file through eight implementations across six
languages, they disagree about nearly every case that is not the obvious one — whether a bare
`$NAME` counts, whether a name defined further down the file may be used further up, what `\${`
means, what `${NAME:-default}` does.

On one case they agree, and it is the wrong one:

```text
PASSWORD=pa${ss}word
```

where `ss` is not defined anywhere. `symfony/dotenv`, `python-dotenv`, `dotenv-expand` for Node,
Ruby's `dotenv`, Dart's `dotenv` and `dotenv-c` all return **`paword`**. No error, nothing empty,
and an application starting with a password nobody chose.

**That is the one behaviour this package refuses to copy.** Every rule here was chosen so that
nothing has to be guessed, and where a guess would be needed it stops instead.

It is a separate package for the same reason: interpolation turns a list of pairs into a small
language, with escaping and ordering to think about. Take it and you get `${SOMETHING}`; leave
it out and you pay nothing for it — and `quillstack/dotenv` refuses a file it cannot finish
reading rather than handing you text that looks like an address and is not one.

## Requirements

- PHP 8.1 or newer

## Installation

```shell
composer require quillstack/dotenv-expand
```

## Usage

```text
APP_HOST=api.example.org
APP_URL=https://${APP_HOST}
DB_PORT=5432
DB_DSN=pgsql:host=127.0.0.1;port=${DB_PORT}
```

```php
use Quillstack\DotenvExpand\Expand;

(new Expand('.env'))->load();
```

```php
env('APP_URL');   // 'https://api.example.org'
env('DB_DSN');    // 'pgsql:host=127.0.0.1;port=5432'
env('DB_PORT');   // 5432, still a number
```

`parse()` reads without touching the environment, the same way round as the package underneath:

```php
$values = (new Expand('.env'))->parse();
```

An already-built reader can be handed over instead of a path:

```php
(new Expand(new Dotenv('.env')))->load();
```

## The rules, and why they are narrow

### An unknown name is an error

```text
PASSWORD=pa${ss}word
```

```text
UndefinedVariableException:
The value of `PASSWORD` uses `${ss}`, and `ss` is not defined above it in the file or in
the environment.
```

### Only names already known

A name is resolved from what the file has already said, or from what the environment already
held — never from further down the file:

```text
FORWARD=${LATER}/x
LATER=defined-after
```

That is an error too. Otherwise a value depends on the order the file happens to be written in,
and the libraries that allow it disagree about what it means: one resolves it, one leaves the
text, one throws.

The environment this process started with counts as already known, which is what makes a value
depend on where it is deployed rather than on what is in the file:

```text
BUCKET=uploads-${DEPLOY_REGION}
```

```php
env('BUCKET');   // 'uploads-eu-central-1'
```

### Only `${NAME}`, never `$NAME`

```text
PASSWORD=hunter2$SOMETHING
```

That is a password and it comes back whole. Half the implementations of this idea expand a bare
`$NAME` and half do not; passwords are full of dollar signs, and one quietly cut short is the
worse of the two mistakes available.

### `\${` is a literal `${`

```text
PRICE=\${9.99}
```

```php
env('PRICE');   // '${9.99}'
```

Four of the libraries measured below get this wrong the same way: they keep the backslash *and*
expand, giving `\https://example.org/v3`.

### No `${NAME:-default}`

A shell expression, not a `.env` one. Ruby's and Dart's libraries return the text `:-fallback}`
for it, which is neither the default nor an error. A default belongs where it can be read:

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

## Building on what was already resolved

A resolved value is simply a value, so the next line can use it:

```text
APP_ENV=production
LOG_PATH=/var/log/${APP_ENV}
UPLOADS=${LOG_PATH}/uploads
```

```php
env('LOG_PATH');   // '/var/log/production'
env('UPLOADS');    // '/var/log/production/uploads'
```

## Making it optional

An application can work whether or not this package is installed:

```php
use Quillstack\Dotenv\Dotenv;
use Quillstack\DotenvExpand\Expand;

$path = __DIR__ . '/../.env';

class_exists(Expand::class)
    ? (new Expand($path))->load()
    : (new Dotenv($path))->load();
```

With it installed, `${SOMETHING}` resolves. Without it, a `.env` that uses one is refused and
says so:

```text
DotenvInterpolationNotSupportedException:
The value of `API` uses `${...}`, which this package does not expand. Install
quillstack/dotenv-expand to resolve it, or write `\${` for a literal `${`.
```

Which means the fallback is honest: an application never silently loads a half-read file because
somebody forgot a dependency.

## Benchmark

Measured with [quillstack/benchmark](https://github.com/quillstack/benchmark) on **one file of
37 keys, 8 of which are built from another value**. Runs are interleaved, each figure is the
median of five, and PHP is 8.5.7.

| | Version |
| --- | --- |
| quillstack/dotenv-expand | v0.6.1 (on quillstack/dotenv v0.7.1) |
| symfony/dotenv | v7.4.15 |
| josegonzalez/dotenv | 4.0.0 (on m1/env 2.2.0) |
| vlucas/phpdotenv | v5.6.4 |

All four resolve that file to the same values. Reading it, once:

| | Per load | Relative |
| --- | --- | --- |
| **quillstack/dotenv-expand** | **190 µs** | — |
| symfony/dotenv | 266 µs | 1.40× |
| josegonzalez/dotenv | 275 µs | 1.44× |
| vlucas/phpdotenv | 470 µs | 2.47× |
| quillstack/dotenv alone | *refuses the file* | — |

The last row is the point of the arrangement rather than a gap in it:
[quillstack/dotenv](https://github.com/quillstack/dotenv) cannot finish reading a file that uses
`${…}`, so it stops and names this package instead of handing back the literal text.

### What it costs to add

On a file with no interpolation in it at all — where this package has nothing to resolve and is
pure overhead — the reader alone takes 146 µs and the pair takes 176 µs. **Adding interpolation
costs about a fifth of the reading time**, and that table is in
[quillstack/dotenv](https://github.com/quillstack/dotenv)'s README, measured the same way on the
same machine so the two can be compared.

**What the numbers do not say:** `symfony/dotenv` and `vlucas/phpdotenv` also read `.env.local`
layering, shell command substitution and `${NAME:-default}`, none of which is here. Being faster
because you do less is not being faster — what this package claims is the strictness, and the
speed is what that strictness happens to cost, which is nothing.

`benchmark:console` reports `Took` and `calls per second` too; both are dominated by PHP process
start-up, identical for every library. The figure that means anything is `avg call time`, which
each measured script reports about itself.

## Tests

```shell
composer test
composer stan
```

## The rest of Quillstack

This is one component of [Quillstack](https://github.com/quillstack), a PHP framework which is
as simple to use as it is strict about what it does.

- [quillstack/dotenv](https://github.com/quillstack/dotenv) — reading the file
- [quillstack/config](https://github.com/quillstack/config) — settings built on top of it
- [quillstack/framework](https://github.com/quillstack/framework) — where both are wired in
- [quillstack/benchmark](https://github.com/quillstack/benchmark) — what produced the table above

## License

MIT — see [LICENSE](https://github.com/quillstack/dotenv-expand/blob/main/LICENSE).
