<?php

declare(strict_types=1);

namespace Quillstack\DotenvExpand;

use Quillstack\Dotenv\Dotenv;
use Quillstack\Dotenv\Interpolation;
use Quillstack\Dotenv\ValueTypes;
use Quillstack\DotenvExpand\Exceptions\UndefinedVariableException;

/**
 * Values built from other values.
 *
 * `.env` has no specification, and every library that expands `${SOMETHING}` has picked its own
 * rules — they disagree on whether `$VAR` without braces counts, on whether a name defined
 * further down the file can be used further up, and on what `\${` means. On one point they
 * agree, and it is the wrong one: an unknown name becomes an empty string. `pa${ss}word`
 * quietly becomes `paword`, and an application starts with a password nobody chose.
 *
 * This does not do that. The rules here are narrow on purpose, and every one of them was picked
 * so that nothing has to be guessed:
 *
 * - only `${NAME}` — a bare `$NAME` is a dollar sign, because passwords are full of them
 * - only names already defined: earlier in the file, or already in the environment
 * - an unknown name is an error, never an empty string
 * - `\${` is a literal `${`
 * - no `${NAME:-default}` — a default belongs in `env('KEY', 'default')`, where it can be read
 */
final class Expand
{
    private readonly Dotenv $dotenv;

    private ValueTypes $valueTypes;

    public function __construct(string|Dotenv $source)
    {
        $this->dotenv = $source instanceof Dotenv ? $source : new Dotenv($source);
        $this->valueTypes = new ValueTypes();
    }

    /**
     * Reads the file, resolves what it refers to, and puts the result into the environment.
     */
    public function load(): void
    {
        foreach ($this->parse() as $key => $value) {
            $this->saveToGlobals($key, $value);
        }
    }

    /**
     * What the file holds once everything in it has been resolved, without touching the
     * environment.
     *
     * @return array<string, mixed>
     */
    public function parse(): array
    {
        $resolved = [];

        foreach ($this->dotenv->parse() as $key => $value) {
            $resolved[$key] = is_string($value)
                ? $this->resolve($key, $value, $resolved)
                : $value;
        }

        return $resolved;
    }

    /**
     * One value, with every name in it replaced by what that name stands for.
     *
     * @param array<string, mixed> $resolved what has been read so far, which is all a value is
     *                                       allowed to refer to
     */
    private function resolve(string $key, string $value, array $resolved): mixed
    {
        if (!Interpolation::isUsed($value)) {
            return $this->finish($value);
        }

        $replaced = preg_replace_callback(
            Interpolation::PATTERN,
            fn (array $match) => $this->valueOf($key, $match[1], $resolved),
            $value
        );

        $replaced = is_string($replaced) ? $replaced : $value;

        // The type is read from what is there once the names are resolved, so `PORT=${DB_PORT}`
        // is the number `DB_PORT` was, rather than the text of it.
        return $this->finish($replaced);
    }

    /**
     * What a name stands for, or an error saying it stands for nothing.
     *
     * @param array<string, mixed> $resolved
     */
    private function valueOf(string $key, string $name, array $resolved): string
    {
        // What the file said already, then what the environment already held. Nothing further
        // down the file: a value that depends on a line below it depends on the order the file
        // happens to be written in, and every library disagrees about what that means.
        if (array_key_exists($name, $resolved)) {
            return $this->asText($resolved[$name]);
        }

        // `array_key_exists` rather than `??`, because a value this package reads back may be
        // the boolean `false` — which `??` would step over and report as a name nobody set.
        if (array_key_exists($name, $_ENV)) {
            return $this->asText($_ENV[$name]);
        }

        $environment = getenv($name);

        if ($environment !== false) {
            return $environment;
        }

        // `${NAME:-default}` is a shell expression, and three of the libraries which claim to
        // understand it return the text `:-default` instead. Saying where a default belongs is
        // more use than reporting a name nobody meant to write.
        if (str_contains($name, ':')) {
            throw new UndefinedVariableException(
                "The value of `{$key}` uses `\${{$name}}`. Shell expressions like `:-` are not "
                . "resolved here — a default belongs in `env('{$key}', 'default')`, where it can "
                . 'be read.'
            );
        }

        throw new UndefinedVariableException(
            "The value of `{$key}` uses `\${{$name}}`, and `{$name}` is not defined above it in "
            . 'the file or in the environment. A name is resolved from what is already known, '
            . 'so that nothing depends on the order the file happens to be written in.'
        );
    }

    /**
     * A value written back out as the text it was read from.
     *
     * `APP_DEBUG=false` is read as the boolean it plainly is, and casting that boolean to a
     * string gives `''` — so `${APP_DEBUG}` would resolve to nothing at all, which is the exact
     * failure this package was written to prevent.
     */
    private function asText(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * The value as it is meant to be read: nothing left to resolve, so an escape has said what
     * it had to say, and what is left can be given its type.
     */
    private function finish(string $value): mixed
    {
        $value = Interpolation::unescape($value);
        $this->valueTypes->extractValueTypes($value);

        return $value;
    }

    private function saveToGlobals(string $key, mixed $value): void
    {
        putenv($key . '=' . (is_scalar($value) ? (string) $value : ''));
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
