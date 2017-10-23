<?php
namespace ParagonIE\Certainty\Tests;


use ParagonIE\Certainty\Bundle;
use ParagonIE\Certainty\Fetch;
use PHPUnit\Framework\TestCase;

class BundleTest extends TestCase
{
    /** @var string $link */
    protected $link;

    public function setUp()
    {
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

        $latest = (new Fetch())->getLatestBundle();

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
     */
    public function testGetters()
    {
        $latest = (new Fetch())->getLatestBundle();
        $this->assertTrue(\is_string($latest->getFilePath()));
        $this->assertTrue(\is_string($latest->getSha256Sum()));
        $this->assertTrue(\is_string($latest->getSignature()));
        $this->assertTrue(\is_string($latest->getSha256Sum(true)));
        $this->assertTrue(\is_string($latest->getSignature(true)));

        $this->assertSame(64, \mb_strlen($latest->getSha256Sum(), '8bit'));
        $this->assertSame(128, \mb_strlen($latest->getSignature(), '8bit'));
        $this->assertSame(32, \mb_strlen($latest->getSha256Sum(true), '8bit'));
        $this->assertSame(64, \mb_strlen($latest->getSignature(true), '8bit'));
    }
}
