<?php
namespace ParagonIE\Certainty\Tests;

use ParagonIE\Certainty\Bundle;
use ParagonIE\Certainty\Validator;
use ParagonIE\ConstantTime\Hex;

/**
 * Class CustomValidator
 *
 * For unit tests only!
 *
 * @package ParagonIE\Certainty\Tests
 */
class CustomValidator extends Validator
{
    /**
     * @var string
     */
    public static $publicKey = '';

    /**
     * @param $string
     */
    public static function setPublicKey($string)
    {
        self::$publicKey = $string;
    }

    /**
     * @param Bundle $bundle  Which bundle to validate
     * @param bool $backupKey Use the backup key? (Only if the primary is compromsied.)
     * @return bool
     *
     * @throws \SodiumException
     */
    public function checkEd25519Signature(Bundle $bundle, $backupKey = false)
    {
        return \sodium_crypto_sign_verify_detached(
            $bundle->getSignature(true),
            $bundle->getFileContents(),
            Hex::decode(self::$publicKey)
        );
    }
}
