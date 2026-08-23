<?php

declare(strict_types=1);

namespace Quillstack\DotenvExpand\Tests\Unit;

use Quillstack\DotenvExpand\Expand;
use Quillstack\UnitTests\AssertEqual;

class TestExpand
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

    public function aNameStandsForWhatItWasGiven()
    {
        $parsed = $this->parse('expand');

        $this->assertEqual->equal('https://example.org/v1', $parsed['SIMPLE']);
    }

    /**
     * A resolved value can be used by the next one, because by then it is simply a value.
     */
    public function andForWhateverItStandsFor()
    {
        $this->assertEqual->equal('https://example.org/v1/users', $this->parse('expand')['NESTED']);
    }

    public function aNameCanSitInsideALongerValue()
    {
        $this->assertEqual->equal(
            'pgsql:host=localhost;port=5432',
            $this->parse('expand')['DSN']
        );
    }

    /**
     * The type is read from what is there once the names are resolved, so a port stays a
     * number rather than becoming the text of one.
     */
    public function theTypeIsReadFromWhatIsLeft()
    {
        $this->assertEqual->equal(5432, $this->parse('expand')['JUST_A_NUMBER']);
    }

    /**
     * `APP_DEBUG=false` is read as the boolean it plainly is, and casting a boolean to a string
     * gives `''` — so a name standing for `false` resolved to nothing at all, which is exactly
     * the failure this package exists to prevent. It went in here first.
     */
    public function aNameStandingForFalseIsStillFalse()
    {
        $parsed = $this->parse('types');

        $this->assertEqual->equal(false, $parsed['FLAG']);
        $this->assertEqual->equal('debug is false and enabled is true', $parsed['MESSAGE']);
        $this->assertEqual->equal('localhost:5432', $parsed['ADDRESS']);
    }
}
