<?php
namespace ParagonIE\Certainty\Tests;

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
    public function tearDown()
    {
        \unlink(__DIR__ . '/static/combined.pem');
        \unlink(__DIR__ . '/static/ca-certs.json');
    }

    /**
     * @covers CustomValidator
     */
    public function testCustom()
    {
        $keypair = \ParagonIE_Sodium_Compat::crypto_sign_keypair();
        $secretKey = \ParagonIE_Sodium_Compat::crypto_sign_secretkey($keypair);
        $publicKey = \ParagonIE_Sodium_Compat::crypto_sign_publickey($keypair);

        $validator = new CustomValidator();
        $validator::setPublicKey(Hex::encode($publicKey));

        $latest = (new Fetch())->getLatestBundle();
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

        $this->assertTrue($validator::checkEd25519Signature($customLatest));
    }

}