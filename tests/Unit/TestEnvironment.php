<?php

declare(strict_types=1);

namespace Quillstack\DotenvExpand\Tests\Unit;

use Quillstack\DotenvExpand\Expand;
use Quillstack\UnitTests\AssertEqual;
use Quillstack\UnitTests\Types\AssertBoolean;

class TestEnvironment
{
    public function __construct(
        private AssertEqual $assertEqual,
        private AssertBoolean $assertBoolean
    ) {
        //
    }

    /**
     * A name the file never mentions can still be known: the environment this process was
     * started with is the other place a value can come from.
     */
    public function aNameCanComeFromTheEnvironment()
    {
        putenv('QUILLSTACK_TEST_ROOT=/opt/app');
        $_ENV['QUILLSTACK_TEST_ROOT'] = '/opt/app';

        $path = sys_get_temp_dir() . '/quillstack-expand-environment.env';
        file_put_contents($path, 'BIN=${QUILLSTACK_TEST_ROOT}/bin' . "\n");

        $this->assertEqual->equal('/opt/app/bin', (new Expand($path))->parse()['BIN']);

        unlink($path);
        putenv('QUILLSTACK_TEST_ROOT');
        unset($_ENV['QUILLSTACK_TEST_ROOT']);
    }

    /**
     * `parse()` reads and `load()` is what changes anything, the same way round as the package
     * this builds on.
     */
    public function readingChangesNothing()
    {
        (new Expand(dirname(__FILE__) . '/../Fixtures/expand.env'))->parse();

        $this->assertBoolean->isFalse(array_key_exists('SIMPLE', $_ENV));
    }

    public function loadingPutsItThere()
    {
        (new Expand(dirname(__FILE__) . '/../Fixtures/expand.env'))->load();

        $this->assertEqual->equal('https://example.org/v1', $_ENV['SIMPLE']);
        $this->assertEqual->equal('https://example.org/v1', getenv('SIMPLE'));
        $this->assertEqual->equal(5432, $_SERVER['PORT']);
    }
}
