<?php
namespace ParagonIE\Certainty\Tests;

use ParagonIE\Certainty\Bundle;
use ParagonIE\Certainty\Exception\CertaintyException;
use ParagonIE\Certainty\RemoteFetch;
use ParagonIE\Certainty\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Class ValidatorTest
 * @package ParagonIE\Certainty\Tests
 */
class ValidatorTest extends TestCase
{
    /** @var Bundle $bundle */
    protected $bundle;

    /** @var string */
    protected $dir;

    /** @var string */
    protected $dir2;

    /** @var Validator $validator */
    protected $validator;

    /**
     * Sets up the Validator test.
     */
    public function setUp()
    {
        $this->validator = new Validator();
        $this->bundle = new Bundle(
            __DIR__ . '/static/test-file.txt',
            '7b8eb84bbaa30c648f3fc9b28d720ab247314032cc4c1f8ad7bd13f7eb2a40a8',
            '456729f1ea34ea0712476e82a904664ead413157291ec47d7c1595795032f004cf6e5532cd8f80d54a8cb86e92dac71367677f110daba1cc2a1bbbcef4ef1a04'
        );
        $this->dir = __DIR__ . '/static/data-valid';
        if (!\is_dir($this->dir)) {
            \mkdir($this->dir);
        }
        $this->dir2 = __DIR__ . '/static/data-valid2';
        if (!\is_dir($this->dir2)) {
            \mkdir($this->dir2);
        }
    }

    /**
     *
     */
    public function tearDown()
    {
        foreach ([$this->dir, $this->dir2] as $d) {
            if (\file_exists($d . '/ca-certs.json')) {
                \unlink($d . '/ca-certs.json');
            }
            if (\file_exists($d . '/ca-certs.cache')) {
                \unlink($d . '/ca-certs.cache');
            }
            foreach (\glob($d . '/*.pem') as $f) {
                $real = \realpath($f);
                if (\strpos($real, $d) === 0) {
                    \unlink($f);
                }
            }
        }
    }

    /**
     * @covers Validator::checkSha256Sum()
     */
    public function testSha256sum()
    {
        $this->assertTrue(
            $this->validator->checkSha256Sum($this->bundle),
            'Sha256sum of test case is wrong.'
        );
    }

    /**
     * @throws CertaintyException
     * @throws \SodiumException
     */
    public function testChronicle()
    {
        if (\getenv('TRAVIS')) {
            $this->markTestSkipped('Unknown GnuTLS errors are breaking TravisCI but the tests succeed locally.');
        }
        $remoteFetch = new RemoteFetch($this->dir);
        $remoteFetch2 = new RemoteFetch($this->dir2);
        $remoteFetch2->setChronicle(
            'https://php-chronicle-replica.pie-hosted.com/chronicle/replica/_vi6Mgw6KXBSuOFUwYA2H2GEPLawUmjqFJbCCuqtHzGZ',
            'MoavD16iqe9-QVhIy-ewD4DMp0QRH-drKfwhfeDAUG0='
        );

        $this->assertSame(
            $remoteFetch->getLatestBundle()->getSha256Sum(),
            $remoteFetch2->getLatestBundle()->getSha256Sum()
        );

        $this->assertSame(
            $remoteFetch->getLatestBundle()->getChronicleHash(),
            $remoteFetch2->getLatestBundle()->getChronicleHash()
        );
    }

    /**
     * @covers Validator::checkEd25519Signature()
     * @throws \SodiumException
     */
    public function testEd25519()
    {
        $this->assertTrue($this->validator->checkEd25519Signature($this->bundle));
        $this->assertFalse($this->validator->checkEd25519Signature($this->bundle, true));
    }
}
