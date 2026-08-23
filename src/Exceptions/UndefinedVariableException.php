<?php

declare(strict_types=1);

namespace Quillstack\DotenvExpand\Exceptions;

use RuntimeException;

/**
 * Thrown where a value refers to a name that stands for nothing.
 *
 * Every other implementation of this idea puts an empty string there instead, which turns
 * `pa${ss}word` into `paword` and starts the application with a password nobody chose.
 */
class UndefinedVariableException extends RuntimeException
{
}
