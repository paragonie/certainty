<?php
namespace ParagonIE\Certainty\Tests;

use ParagonIE\Certainty\Exception\CertaintyException;
use ParagonIE\Certainty\Exception\CryptoException;
use ParagonIE\ConstantTime\Hex;
use ParagonIE\Certainty\Fetch;
use ParagonIE\Certainty\LocalCACertBuilder;
use PHPUnit\Framework\TestCase;


/**
 * Class CustomCASupportTest
 * @package ParagonIE\Certainty\Tests
 */
class CustomCASupportTest extends TestCase
{
    /**
     * @var string
     */
    protected $defaultDir;

    public function setUp()
    {
        $this->defaultDir = dirname(__DIR__) . '/data';
    }

    public function tearDown()
    {
        \unlink(__DIR__ . '/static/combined.pem');
        \unlink(__DIR__ . '/static/ca-certs.json');
    }

    /**
     * @covers \ParagonIE\Certainty\Tests\CustomValidator
     *
     * @throws CertaintyException
     * @throws CryptoException
     * @throws \SodiumException
     */
    public function testCustom()
    {
        $keypair = \ParagonIE_Sodium_Compat::crypto_sign_keypair();
        $secretKey = \ParagonIE_Sodium_Compat::crypto_sign_secretkey($keypair);
        $publicKey = \ParagonIE_Sodium_Compat::crypto_sign_publickey($keypair);

        $validator = new CustomValidator();
        $validator::setPublicKey(Hex::encode($publicKey));

        $latest = (new Fetch($this->defaultDir))->getLatestBundle();
        LocalCACertBuilder::fromBundle($latest)
            ->setCustomValidator(CustomValidator::class)
            ->setOutputPemFile(__DIR__ . '/static/combined.pem')
            ->setOutputJsonFile(__DIR__ . '/static/ca-certs.json')
            ->setSigningKey($secretKey)
            ->appendCACertFile(__DIR__ . '/static/repeat-globalsign.pem')
            ->save();

        $customLatest = (new Fetch(__DIR__ . '/static'))->getLatestBundle();
        $this->assertSame(
            \hash_file('sha256', __DIR__ . '/static/combined.pem'),
            $customLatest->getSha256Sum()
        );

        $this->assertTrue($validator->checkEd25519Signature($customLatest));
    }

}