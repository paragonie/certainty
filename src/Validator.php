<?php
namespace ParagonIE\Certainty;
use GuzzleHttp\Exception\ConnectException;
use ParagonIE\ConstantTime\Base64UrlSafe;
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

    // Chronicle settings.
    const CHRONICLE_URL = 'https://php-chronicle.pie-hosted.com/chronicle';
    const CHRONICLE_PUBKEY = 'MoavD16iqe9-QVhIy-ewD4DMp0QRH-drKfwhfeDAUG0=';

    /**
     * Validate SHA256 checksums.
     *
     * @param Bundle $bundle
     * @return bool
     */
    public static function checkSha256Sum(Bundle $bundle)
    {
        $sha256sum = \hash_file('sha256', $bundle->getFilePath(), true);
        return \hash_equals($bundle->getSha256Sum(true), $sha256sum);
    }

    /**
     * Check Ed25519 signature for this bundle's contents.
     *
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

    /**
     * Is this update checked into a Chronicle?
     *
     * @param Bundle $bundle
     * @return bool
     * @throws \Exception
     * @throws \TypeError
     */
    public static function checkChronicleHash(Bundle $bundle)
    {
        if (empty($bundle->getChronicleHash())) {
            return false;
        }
        $chronicleUrl = static::CHRONICLE_URL;
        $publicKey = Base64UrlSafe::decode(static::CHRONICLE_PUBKEY);
        $guzzle = Certainty::getGuzzleClient();

        try {
            $response = $guzzle->get(
                \rtrim($chronicleUrl, '/') .
                '/lookup/' .
                $bundle->getChronicleHash()
            );
        } catch (ConnectException $ex) {
            return false;
        }

        /** @var string $body */
        $body = (string) $response->getBody();

        // Signature validation phase:
        $sigValid = false;
        foreach ($response->getHeader(Certainty::ED25519_HEADER) as $header) {
            // Don't catch exceptions here:
            /** @var string $signature */
            $signature = Base64UrlSafe::decode($header);
            if (!\is_string($signature)) {
                throw new \TypeError('Signature invalid');
            }
            $sigValid = $sigValid || \ParagonIE_Sodium_Compat::crypto_sign_verify_detached(
                $signature,
                $body,
                $publicKey
            );
        }
        if (!$sigValid) {
            // No valid signatures
            return false;
        }
        $json = \json_decode($body, true);
        if (!\is_array($json)) {
            throw new \TypeError('Invalid JSON response');
        }

        // If the status was successful,
        if (!\hash_equals('OK', $json['status'])) {
            if (isset($json['error'])) {
                throw new \Exception($json['error']);
            }
            return false;
        }

        // Make sure our sha256sum is present somewhere in the results
        $hashValid = false;
        foreach ($json['results'] as $results) {
            $hashValid = $hashValid || static::validateChronicleContents($bundle, $results);
        }
        return $hashValid;
    }

    /**
     * Actually validates the contents of a Chronicle entry.
     *
     * @param Bundle $bundle
     * @param array $result
     * @return bool
     */
    protected static function validateChronicleContents(Bundle $bundle, array $result = [])
    {
        if (!isset($result['signature'], $result['contents'], $result['publickey'])) {
            // Incomplete data.
            return false;
        }
        $publicKey = Hex::encode(Base64UrlSafe::decode($result['publickey']));
        if (
            !\hash_equals(static::PRIMARY_SIGNING_PUBKEY, $publicKey)
                &&
            !\hash_equals(static::BACKUP_SIGNING_PUBKEY, $publicKey)
        ) {
            // This was not one of our keys.
            return false;
        }

        // Let's validate the signature.
        $signature = Base64UrlSafe::decode($result['signature']);
        if (!\ParagonIE_Sodium_Compat::crypto_sign_verify_detached(
            $signature,
            $result['contents'],
            Hex::decode($publicKey)
        )) {
            return false;
        }

        // Lazy evaluation: SHA256 hash not present?
        if (\strpos($result['contents'], $bundle->getSha256Sum()) === false) {
            return false;
        }

        // Lazy evaluation: Repository name not fouind?
        if (\strpos($result['contents'], Certainty::REPOSITORY) === false) {
            /** @var string $altRepoName */
            $altRepoName = \json_encode(Certainty::REPOSITORY);
            if (\strpos($result['contents'], $altRepoName) === false) {
                return false;
            }
        }

        // If we've gotten here, then this Chronicle has our update logged.
        return true;
    }
}
