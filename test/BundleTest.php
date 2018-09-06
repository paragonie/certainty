<?php
namespace ParagonIE\Certainty\Tests;

use ParagonIE\ConstantTime\Binary;
use ParagonIE\Certainty\Bundle;
use ParagonIE\Certainty\Exception\CertaintyException;
use ParagonIE\Certainty\Fetch;
use PHPUnit\Framework\TestCase;

/**
 * Class BundleTest
 * @package ParagonIE\Certainty\Tests
 */
class BundleTest extends TestCase
{
    /**
     * @var string
     */
    protected $defaultDir;

    /** @var string $link */
    protected $link;

    public function setUp()
    {
        $this->defaultDir = dirname(__DIR__) . '/data';
        $this->link = __DIR__ . '/static/symlink-test';
    }

    public function tearDown()
    {
        if (\file_exists($this->link)) {
            \unlink($this->link);
        }
    }

    /**
     * @covers Bundle::createSymlink()
     * @throws CertaintyException
     * @throws \SodiumException
     */
    public function testCreateSymlink()
    {
        if (\file_exists($this->link)) {
            \unlink($this->link);
        }
        $test = __DIR__ . '/static/test-file.txt';
        if (!@\symlink($test, $this->link)) {
            $this->markTestSkipped('Possibly a read-only file-system (e.g. VirtualBox shared folder). Skipping.');
            return;
        }

        $latest = (new Fetch($this->defaultDir))->getLatestBundle();

        $latest->createSymlink($this->link, true);

        $this->assertSame(
            \hash_file('sha384', $this->link),
            \hash_file('sha384', $latest->getFilePath()),
            'Symlink creation failed.'
        );
    }

    /**
     * @covers Bundle::getFilePath()
     * @covers Bundle::getSha256Sum()
     * @covers Bundle::getSignature()
     * @throws CertaintyException
     * @throws \SodiumException
     */
    public function testGetters()
    {
        $latest = (new Fetch($this->defaultDir))->getLatestBundle();
        $this->assertTrue(\is_string($latest->getFilePath()));
        $this->assertTrue(\is_string($latest->getSha256Sum()));
        $this->assertTrue(\is_string($latest->getSignature()));
        $this->assertTrue(\is_string($latest->getSha256Sum(true)));
        $this->assertTrue(\is_string($latest->getSignature(true)));

        $this->assertSame(64, Binary::safeStrlen($latest->getSha256Sum()));
        $this->assertSame(128, Binary::safeStrlen($latest->getSignature()));
        $this->assertSame(32, Binary::safeStrlen($latest->getSha256Sum(true)));
        $this->assertSame(64, Binary::safeStrlen($latest->getSignature(true)));
    }
}
