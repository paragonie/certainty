<?php
namespace ParagonIE\Certainty\Tests;


use ParagonIE\Certainty\Bundle;
use ParagonIE\Certainty\Fetch;
use PHPUnit\Framework\TestCase;

class FetchTest extends TestCase
{
    /** @var string */
    protected $root;

    public function setUp()
    {
        $this->root = __DIR__ . '/static/';
    }

    /**
     * @covers Fetch
     */
    public function testEmptyDir()
    {
        try {
            (new Fetch($this->root . 'empty-dir'))->getAllBundles();
            $this->fail('Expected an exception.');
        } catch (\Exception $ex) {
            $this->assertSame(
                'ca-certs.json not found in data directory.',
                $ex->getMessage()
            );
        }
    }

    /**
     * @covers Fetch
     */
    public function testEmptyJson()
    {
        $this->assertSame(
            [],
            (new Fetch($this->root . 'data-empty'))->getAllBundles()
        );
    }

    /**
     * @covers Fetch
     */
    public function testInvalid()
    {
        try {
            (new Fetch($this->root . 'data-invalid'))->getLatestBundle();
            $this->fail('Expected an exception.');
        } catch (\Exception $ex) {
            $this->assertSame(
                'No valid bundles were found in the data directory.',
                $ex->getMessage()
            );
        }
    }

    /**
     *
     */
    public function testLiveDataDir()
    {
        $this->assertInstanceOf(
            Bundle::class,
            (new Fetch())->getLatestBundle(),
            'The live data directory has no valid signatures.'
        );
    }
}
