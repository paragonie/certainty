<?php
namespace ParagonIE\Certainty;
use ParagonIE\ConstantTime\Hex;

/**
 * Class Validator
 * @package ParagonIE\Certainty
 */
class Validator
{
    // Ed25519 public keys
    const PRIMARY_SIGNING_PUBKEY = '98f2dfad4115fea9f096c35485b3bf20b06e94acac3b7acf6185aa5806020342';
    const BACKUP_SIGNING_PUBKEY = '1cb438a66110689f1192b511a88030f02049c40d196dc1844f9e752531fdd195';

    /**
     * @param Bundle $bundle
     * @return bool
     */
    public static function checkSha256Sum(Bundle $bundle)
    {
        $sha256sum = \hash_file('sha256', $bundle->getFilePath(), true);
        return \hash_equals($bundle->getSha256Sum(true), $sha256sum);
    }

    /**
     * @param Bundle $bundle  Which bundle to validate
     * @param bool $backupKey Use the backup key? (Only if the primary is compromised.)
     * @return bool
     */
    public static function checkEd25519Signature(Bundle $bundle, $backupKey = false)
    {
        if ($backupKey) {
            $publicKey = Hex::decode(static::BACKUP_SIGNING_PUBKEY);
        } else {
            $publicKey = Hex::decode(static::PRIMARY_SIGNING_PUBKEY);
        }
        return \ParagonIE_Sodium_File::verify(
            $bundle->getSignature(true),
            $bundle->getFilePath(),
            $publicKey
        );
    }
}
