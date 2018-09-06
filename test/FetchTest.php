<?php
namespace ParagonIE\Certainty\Tests;

use ParagonIE\Certainty\Bundle;
use ParagonIE\Certainty\Exception\CertaintyException;
use ParagonIE\Certainty\Fetch;
use PHPUnit\Framework\TestCase;

/**
 * Class FetchTest
 * @package ParagonIE\Certainty\Tests
 */
class FetchTest extends TestCase
{
    /**
     * @var string
     */
    protected $defaultDir;

    /** @var string */
    protected $root;

    public function setUp()
    {
        $this->defaultDir = dirname(__DIR__) . '/data';
        $this->root = __DIR__ . '/static/';
    }

    /**
     * @covers \ParagonIE\Certainty\Fetch
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
     * @covers \ParagonIE\Certainty\Fetch
     * @throws
     */
    public function testEmptyJson()
    {
        $this->assertSame(
            [],
            (new Fetch($this->root . 'data-empty'))->getAllBundles()
        );
    }

    /**
     * @covers \ParagonIE\Certainty\Fetch
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
     * @throws CertaintyException
     * @throws \SodiumException
     */
    public function testLiveDataDir()
    {
        $this->assertInstanceOf(
            Bundle::class,
            (new Fetch($this->defaultDir))->getLatestBundle(),
            'The live data directory has no valid signatures.'
        );
    }
}
