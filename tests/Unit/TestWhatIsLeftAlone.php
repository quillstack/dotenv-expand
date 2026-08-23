<?php

declare(strict_types=1);

namespace Quillstack\DotenvExpand\Tests\Unit;

use Quillstack\DotenvExpand\Expand;
use Quillstack\UnitTests\AssertEqual;

/**
 * The narrow rules, and why each is narrow.
 */
class TestWhatIsLeftAlone
{
    public function __construct(private AssertEqual $assertEqual)
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    private function parse(string $fixture): array
    {
        return (new Expand(dirname(__FILE__) . "/../Fixtures/{$fixture}.env"))->parse();
    }

    /**
     * Half the implementations of this idea expand a bare `$NAME` and half do not. Passwords
     * are full of dollar signs, and a password quietly cut short is the worst of the two
     * mistakes available.
     */
    public function aDollarWithoutBracesIsADollar()
    {
        $parsed = $this->parse('literal');

        $this->assertEqual->equal('$BASE/v2', $parsed['BRACELESS']);
        $this->assertEqual->equal('hunter2$SOMETHING', $parsed['PASSWORD']);
    }

    /**
     * Four of the libraries compared against get this wrong in the same way: they keep the
     * backslash and expand anyway, giving `\https://example.org/v3`.
     */
    public function anEscapedNameIsText()
    {
        $this->assertEqual->equal('${BASE}/v3', $this->parse('literal')['ESCAPED']);
    }
}
