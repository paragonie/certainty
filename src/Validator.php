<?php
namespace ParagonIE\Certainty;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use ParagonIE\Certainty\Exception\CertaintyException;
use ParagonIE\Certainty\Exception\CryptoException;
use ParagonIE\Certainty\Exception\EncodingException;
use ParagonIE\Certainty\Exception\InvalidResponseException;
use ParagonIE\Certainty\Exception\RemoteException;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\ConstantTime\Hex;
use ParagonIE_Sodium_Core_Util as SodiumUtil;

/**
 * Class Validator
 * @package ParagonIE\Certainty
 */
class Validator
{
    // Set this to true to not throw exceptions
    const THROW_MORE_EXCEPTIONS = false;

    /**
     * Ed25519 public keys. These are hard-coded for the class, but can be changed in inherited classes.
     */
    const PRIMARY_SIGNING_PUBKEY = '98f2dfad4115fea9f096c35485b3bf20b06e94acac3b7acf6185aa5806020342';
    const BACKUP_SIGNING_PUBKEY = '1cb438a66110689f1192b511a88030f02049c40d196dc1844f9e752531fdd195';

    // Default Chronicle settings, if none are provided.
    const CHRONICLE_URL = 'https://php-chronicle.pie-hosted.com/chronicle';
    const CHRONICLE_PUBKEY = 'Bgcc1QfkP0UNgMZuHzi0hC1hA1SoVAyUrskmSkzRw3E=';

    /**
     * @var string $chronicleUrl
     */
    protected $chronicleUrl = '';

    /**
     * @var string $chroniclePublicKey
     */
    protected $chroniclePublicKey = '';

    /**
     * Validator constructor.
     *
     * @param string $chronicleUrl
     * @param string $chroniclePublicKey
     */
    public function __construct($chronicleUrl = '', $chroniclePublicKey = '')
    {
        if (!$chronicleUrl) {
            $chronicleUrl = (string) static::CHRONICLE_URL;
        }
        if (!$chroniclePublicKey) {
            $chroniclePublicKey = (string) static::CHRONICLE_PUBKEY;
        }
        $this->chronicleUrl = $chronicleUrl;
        $this->chroniclePublicKey = $chroniclePublicKey;
    }

    /**
     * Validate SHA256 checksums.
     *
     * @param Bundle $bundle
     * @return bool
     */
    public static function checkSha256Sum(Bundle $bundle)
    {
        $sha256sum = \hash_file('sha256', $bundle->getFilePath(), true);
        try {
            return SodiumUtil::hashEquals($bundle->getSha256Sum(true), $sha256sum);
        } catch (\SodiumException $ex) {
            return false;
        }
    }

    /**
     * Check Ed25519 signature for this bundle's contents.
     *
     * @param Bundle $bundle  Which bundle to validate
     * @param bool $backupKey Use the backup key? (Only if the primary is compromised.)
     * @return bool
     * @throws \SodiumException
     */
    public function checkEd25519Signature(Bundle $bundle, $backupKey = false)
    {
        /** @var string $publicKey */
        if ($backupKey) {
            $publicKey = Hex::decode((string) static::BACKUP_SIGNING_PUBKEY);
        } else {
            $publicKey = Hex::decode((string) static::PRIMARY_SIGNING_PUBKEY);
        }

        try {
            return \ParagonIE_Sodium_Compat::crypto_sign_verify_detached(
                $bundle->getSignature(true),
                $bundle->getFileContents(),
                $publicKey
            );
        } catch (CertaintyException $ex) {
            return false;
        }
        /*
        return \ParagonIE_Sodium_File::verify(
            $bundle->getSignature(true),
            $bundle->getFilePath(),
            $publicKey
        );
        */
    }

    /**
     * Is this update checked into a Chronicle?
     *
     * @param Bundle $bundle
     * @return bool
     * @throws \Exception
     * @throws ConnectException
     * @throws EncodingException
     * @throws RemoteException
     */
    public function checkChronicleHash(Bundle $bundle)
    {
        if (empty($this->chronicleUrl) && empty($this->chroniclePublicKey)) {
            // Custom validator has opted to fail open here. Who are we to dissent?
            return true;
        }
        if (empty($bundle->getChronicleHash())) {
            // No chronicle hash? This check fails closed.
            return false;
        }
        // Inherited classes can override this.
        /** @var string $chronicleUrl */
        $chronicleUrl = $this->chronicleUrl;

        /** @var string $publicKey */
        $publicKey = Base64UrlSafe::decode($this->chroniclePublicKey);

        /** @var Client $guzzle */
        $guzzle = Certainty::getGuzzleClient(new Fetch(dirname($bundle->getFilePath())));

        // We could catch the ConnectException, but let's not.
        /** @var Response $response */
        $response = $guzzle->get(
            \rtrim($chronicleUrl, '/') .
            '/lookup/' .
            $bundle->getChronicleHash()
        );

        /** @var string $body */
        $body = (string) $response->getBody();

        // Signature validation phase:
        $sigValid = false;

        /** @var array<string, string> $sigHeaders */
        $sigHeaders = $response->getHeader(Certainty::ED25519_HEADER);

        /** @var string $header */
        foreach ($sigHeaders as $header) {
            // Don't catch exceptions here:
            $signature = Base64UrlSafe::decode($header);
            $sigValid = $sigValid || \ParagonIE_Sodium_Compat::crypto_sign_verify_detached(
                (string) $signature,
                (string) $body,
                (string) $publicKey
            );
        }
        if (!$sigValid) {
            if (static::THROW_MORE_EXCEPTIONS) {
                throw new CryptoException('Invalid signature.');
            }
            // No valid signatures
            return false;
        }
        /** @var array<string, mixed>|bool $json */
        $json = \json_decode($body, true);
        if (!\is_array($json)) {
            throw new EncodingException('Invalid JSON response');
        }

        /** @var string $status */
        $jsonStatus = (string) $json['status'];
        // If the status was successful,
        try {
            $ok = SodiumUtil::hashEquals('OK', $jsonStatus);
        } catch (\SodiumException $ex) {
            $ok = false;
        }
        if (!$ok) {
            if (self::THROW_MORE_EXCEPTIONS) {
                if (isset($json['error'])) {
                    /** @var string $jsonError */
                    $jsonError = $json['error'];
                    throw new RemoteException($jsonError);
                }
                throw new RemoteException('Invalid status returned by the API');
            }
            return false;
        }

        // Make sure our sha256sum is present somewhere in the results
        $hashValid = false;
        /** @var array<string, array> $jsonResults */
        $jsonResults = $json['results'];
        foreach ($jsonResults as $results) {
            /** @var array<string, string> $results */
            $hashValid = $hashValid || static::validateChronicleContents($bundle, $results);
        }
        return $hashValid;
    }

    /**
     * Actually validates the contents of a Chronicle entry.
     *
     * @param Bundle $bundle
     * @param array<string, string> $result  Chronicle API response (post signature validation)
     * @return bool
     * @throws CryptoException
     * @throws InvalidResponseException
     * @throws \SodiumException
     */
    protected static function validateChronicleContents(Bundle $bundle, array $result = [])
    {
        if (!isset($result['signature'], $result['contents'], $result['publickey'])) {
            if (static::THROW_MORE_EXCEPTIONS) {
                throw new InvalidResponseException('Incomplete data');
            }
            // Incomplete data.
            return false;
        }
        /** @var string $publicKey */
        $publicKey = (string) Hex::encode(
            (string) Base64UrlSafe::decode($result['publickey'])
        );
        if (
            !SodiumUtil::hashEquals(
                (string) static::PRIMARY_SIGNING_PUBKEY,
                (string) $publicKey
            )
                &&
            !SodiumUtil::hashEquals(
                (string) static::BACKUP_SIGNING_PUBKEY,
                (string) $publicKey
            )
        ) {
            // This was not one of our keys.
            return false;
        }

        // Let's validate the signature.
        /** @var string $signature */
        $signature = (string) Base64UrlSafe::decode($result['signature']);
        if (!\ParagonIE_Sodium_Compat::crypto_sign_verify_detached(
            $signature,
            $result['contents'],
            Hex::decode($publicKey)
        )) {
            if (static::THROW_MORE_EXCEPTIONS) {
                throw new CryptoException('Invalid signature.');
            }
            return false;
        }

        // Lazy evaluation: SHA256 hash not present?
        if (\strpos($result['contents'], $bundle->getSha256Sum()) === false) {
            if (static::THROW_MORE_EXCEPTIONS) {
                throw new InvalidResponseException('SHA256 hash not present in response body');
            }
            return false;
        }

        // Lazy evaluation: Repository name not fouind?
        if (\strpos($result['contents'], Certainty::REPOSITORY) === false) {
            /** @var string $altRepoName */
            $altRepoName = \json_encode(Certainty::REPOSITORY);
            if (\strpos($result['contents'], $altRepoName) === false) {
                if (static::THROW_MORE_EXCEPTIONS) {
                    throw new InvalidResponseException('Repository name not present in response body');
                }
                return false;
            }
        }

        // If we've gotten here, then this Chronicle has our update logged.
        return true;
    }
}
