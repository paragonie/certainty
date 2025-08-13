<?php
namespace ParagonIE\Certainty\Tests;

use ParagonIE\Certainty\Bundle;
use ParagonIE\Certainty\Exception\CertaintyException;
use ParagonIE\Certainty\Validator;
use ParagonIE\ConstantTime\Hex;
use SodiumException;

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
    public static string $publicKey = '';

    /**
     * @param $string
     */
    public static function setPublicKey($string): void
    {
        self::$publicKey = $string;
    }

    /**
     * @param Bundle $bundle  Which bundle to validate
     * @param bool $backupKey Use the backup key? (Only if the primary is compromsied.)
     * @return bool
     *
     * @throws SodiumException
     * @throws CertaintyException
     */
    public function checkEd25519Signature(Bundle $bundle, $backupKey = false): bool
    {
        return \sodium_crypto_sign_verify_detached(
            $bundle->getSignature(true),
            $bundle->getFileContents(),
            Hex::decode(self::$publicKey)
        );
    }
}
