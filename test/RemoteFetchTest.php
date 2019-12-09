<?php
namespace ParagonIE\Certainty\Tests;

use ParagonIE\Certainty\Exception\CertaintyException;
use ParagonIE\Certainty\RemoteFetch;
use PHPUnit\Framework\TestCase;

/**
 * Class RemoteFetchTest
 * @package ParagonIE\Certainty\Tests
 */
class RemoteFetchTest extends TestCase
{
    /** @var string */
    protected $dir;

    public function setUp()
    {
        if (\getenv('TRAVIS')) {
            $this->markTestSkipped('Unknown GnuTLS errors are breaking TravisCI but the tests succeed locally.');
        }
        $this->dir = __DIR__ . '/static/data-remote';
        if (!\is_dir($this->dir)) {
            \mkdir($this->dir);
        }
    }

    public function tearDown()
    {
        if (file_exists($this->dir . '/ca-certs.json')) {
            \unlink($this->dir . '/ca-certs.json');
        }
        if (file_exists($this->dir . '/ca-certs.cache')) {
            \unlink($this->dir . '/ca-certs.cache');
        }
        foreach(\glob($this->dir . '/*.pem') as $f) {
            $real = \realpath($f);
            if (\strpos($real, $this->dir) === 0) {
                \unlink($f);
            }
        }
    }

    /**
     * @covers \ParagonIE\Certainty\RemoteFetch
     * @throws CertaintyException
     * @throws \SodiumException
     */
    public function testRemoteFetch()
    {
        $this->assertFalse(\file_exists($this->dir . '/ca-certs.json'));
        $fetch = new RemoteFetch($this->dir);
        $fetch->getLatestBundle();
        $this->assertTrue(\file_exists($this->dir . '/ca-certs.json'));

        // Force a cache expiration
        \file_put_contents(
            $this->dir . '/ca-certs.cache',
            (new \DateTime())
                ->sub(new \DateInterval('PT06H'))
                ->format(\DateTime::ATOM)
        );
        $fetch->setCacheTimeout(new \DateInterval('PT01M'));
        $this->assertTrue($fetch->cacheExpired());


        $latest = $fetch->getLatestBundle();
        file_put_contents($latest->getFilePath(), ' corrupt', FILE_APPEND);
        (new RemoteFetch($this->dir))->getLatestBundle();

        $cacerts = json_decode(file_get_contents($this->dir . '/ca-certs.json'), true);
        $this->assertTrue(!empty($cacerts[0]['bad-bundle']));
    }

    public function testLatest()
    {
        $files = array();
        $dir = dirname(__DIR__) . '/data';
        foreach (glob($dir . '/cacert-*.pem') as $file) {
            $pieces = explode('/', $file);
            $filename = array_pop($pieces);
            if (preg_match('/^cacert\-(\d+)\-(\d+)\-(\d+).pem$/', $filename, $m)) {
                $i = (int) ($m[1] . $m[2] . $m[3]);
                $files[$i] = $file;
            }
        }
        krsort($files);
        $path = array_shift($files);

        $fetch = new RemoteFetch($dir);
        $this->assertSame(
            $path,
            $fetch->getLatestBundle()->getFilePath()
        );
        $this->assertSame(
            hash_file('sha256', $path),
            $fetch->getLatestBundle()->getSha256Sum()
        );
    }
}
