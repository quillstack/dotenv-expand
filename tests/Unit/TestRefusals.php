<?php

declare(strict_types=1);

namespace Quillstack\DotenvExpand\Tests\Unit;

use Quillstack\DotenvExpand\Exceptions\UndefinedVariableException;
use Quillstack\DotenvExpand\Expand;
use Quillstack\UnitTests\AssertExceptions;

/**
 * What this refuses, and what everybody else does instead.
 */
class TestRefusals
{
    public function __construct(private AssertExceptions $assertExceptions)
    {
        //
    }

    private function parse(string $fixture): void
    {
        (new Expand(dirname(__FILE__) . "/../Fixtures/{$fixture}.env"))->parse();
    }

    /**
     * Every other implementation puts an empty string here. `pa${ss}word` becomes `paword`, and
     * the application starts with a password nobody chose — no error, nothing empty, nothing to
     * find in a log.
     */
    public function anUnknownNameIsAnError()
    {
        $this->assertExceptions->expect(UndefinedVariableException::class);

        $this->parse('undefined');
    }

    /**
     * A value that depends on a line below it depends on the order the file happens to be
     * written in, and the libraries which allow it disagree about what it means: one resolves
     * it, one leaves the text, one throws.
     */
    public function aNameDefinedFurtherDownIsAnError()
    {
        $this->assertExceptions->expect(UndefinedVariableException::class);

        $this->parse('forward');
    }

    /**
     * `${NAME:-default}` is a shell expression. Two of the libraries compared against return
     * the text `:-fallback}` for it, which is neither the default nor an error.
     */
    public function aShellDefaultIsAnError()
    {
        $this->assertExceptions->expect(UndefinedVariableException::class);

        $this->parse('no-default');
    }
}
