<?php
namespace ParagonIE\Certainty\Tests;

use ParagonIE\Certainty\Bundle;
use ParagonIE\Certainty\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    /** @var Bundle $bundle */
    protected $bundle;

    /**
     * Sets up the Validator test.
     */
    public function setUp()
    {
        $this->bundle = new Bundle(
            __DIR__ . '/static/test-file.txt',
            '7b8eb84bbaa30c648f3fc9b28d720ab247314032cc4c1f8ad7bd13f7eb2a40a8',
            '456729f1ea34ea0712476e82a904664ead413157291ec47d7c1595795032f004cf6e5532cd8f80d54a8cb86e92dac71367677f110daba1cc2a1bbbcef4ef1a04'
        );
    }

    /**
     * @covers Validator::checkSha256Sum()
     */
    public function testSha256sum()
    {
        $this->assertTrue(Validator::checkSha256Sum($this->bundle), 'Sha256sum of test case is wrong.');
    }

    /**
     * @covers Validator::checkEd25519Signature()
     */
    public function testEd25519()
    {
        $this->assertTrue(Validator::checkEd25519Signature($this->bundle));
        $this->assertFalse(Validator::checkEd25519Signature($this->bundle, true));
    }
}
