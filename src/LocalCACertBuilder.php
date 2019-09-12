<?php
namespace ParagonIE\Certainty;

use GuzzleHttp\Psr7\Response;
use ParagonIE\Certainty\Exception\CertaintyException;
use ParagonIE\Certainty\Exception\CryptoException;
use ParagonIE\Certainty\Exception\EncodingException;
use ParagonIE\Certainty\Exception\FilesystemException;
use ParagonIE\Certainty\Exception\InvalidResponseException;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\ConstantTime\Hex;

/**
 * Class LocalCACertBuilder
 * @package ParagonIE\Certainty
 */
class LocalCACertBuilder extends Bundle
{
    /**
     * @var string $chronicleClientId
     */
    protected $chronicleClientId = '';

    /**
     * @var string $chroniclePublicKey
     */
    protected $chroniclePublicKey = '';

    /**
     * @var string $chronicleRepoName
     */
    protected $chronicleRepoName = 'paragonie/certainty';

    /**
     * @var string $chronicleUrl
     */
    protected $chronicleUrl = '';

    /**
     * @var string $contents
     */
    protected $contents = '';

    /**
     * @var string $original
     */
    protected $original = '';

    /**
     * @var string $outputPem
     */
    protected $outputPem = '';

    /**
     * @var string $outputJson
     */
    protected $outputJson = '';

    /**
     * @var string $secretKey
     */
    protected $secretKey = '';

    /**
     * @var string $trustChannel
     */
    protected $trustChannel = Certainty::TRUST_DEFAULT;

    /**
     * @param Bundle $old
     * @return self
     *
     * @throws CertaintyException
     */
    public static function fromBundle(Bundle $old)
    {
        $new = new static(
            $old->getFilePath(),
            $old->getSha256Sum(),
            $old->getSignature()
        );
        $new->customValidator = $old->getValidator();
        $new->trustChannel = $old->getTrustChannel();
        return $new;
    }

    /**
     * Load the original bundle's contents.
     *
     * @return self
     * @throws CertaintyException
     */
    public function loadOriginal()
    {
        $this->original = \file_get_contents($this->filePath);
        if (!\is_string($this->original)) {
            throw new FilesystemException('Could not read contents of CACert file provided.');
        }
        return $this;
    }

    /**
     * Append a CACert file, containing your in-house certificates, to the bundle
     * being compiled.
     *
     * @param string $path
     * @return self
     * @throws CertaintyException
     */
    public function appendCACertFile($path = '')
    {
        if (!$this->original) {
            $this->loadOriginal();
        }
        if (!$this->contents) {
            $this->contents = $this->original . "\n";
        }
        $contents = \file_get_contents($path);
        if (!\is_string($contents)) {
            throw new FilesystemException('Could not read contents of CACert file provided.');
        }
        $this->contents .= $contents . "\n";
        return $this;
    }

    /**
     * Publish the most recent CACert information to the local Chronicle.
     *
     * @param string $sha256sum
     * @param string $signature
     * @return string
     *
     * @throws CertaintyException
     * @throws \SodiumException
     */
    protected function commitToChronicle($sha256sum, $signature)
    {
        if (empty($this->chronicleUrl) || empty($this->chroniclePublicKey) || empty($this->chronicleClientId)) {
            return '';
        }

        $body = \json_encode(
            [
                'repository' => $this->chronicleRepoName,
                'sha256' => $sha256sum,
                'signature' => $signature,
                'time' => (new \DateTime())->format(\DateTime::ATOM)
            ],
            JSON_PRETTY_PRINT
        );
        if (!\is_string($body)) {
            throw new EncodingException('Could not build a valid JSON message.');
        }
        $signature = \ParagonIE_Sodium_Compat::crypto_sign_detached($body, $this->secretKey);

        $http = Certainty::getGuzzleClient(new Fetch(dirname($this->getFilePath())));
        /** @var Response $response */
        $response = $http->post(
            $this->chronicleUrl . '/publish',
            [
                'headers' => [
                    Certainty::CHRONICLE_CLIENT_ID => $this->chronicleClientId,
                    Certainty::ED25519_HEADER => Base64UrlSafe::encode($signature)
                ],
                'body' => $body,
            ]
        );

        /** @var string $responseBody */
        $responseBody = (string) $response->getBody();

        /** @var bool $validSig */
        $validSig = false;

        /** @var array<int, string> $sigHeaders */
        $sigHeaders = $response->getHeader(Certainty::ED25519_HEADER);

        /** @var string $sigLine */
        foreach ($sigHeaders as $sigLine) {
            /** @var string $sig */
            $sig = Base64UrlSafe::decode($sigLine);
            $validSig = $validSig || \ParagonIE_Sodium_Compat::crypto_sign_verify_detached(
                $sig,
                $responseBody,
                $this->chroniclePublicKey
            );
        }
        if (!$validSig) {
            throw new InvalidResponseException('No valid signature for Chronicle response.');
        }

        /** @var array<string, array<string, string>>|bool $json */
        $json = \json_decode($responseBody, true);
        if (!\is_array($json)) {
            return '';
        }
        if (!isset($json['results'])) {
            return '';
        }
        if (!isset($json['results']['summaryhash'])) {
            return '';
        }
        return (string) $json['results']['summaryhash'];
    }

    /**
     * Get the public key.
     *
     * @param bool $raw
     * @return string
     * @throws \SodiumException
     */
    public function getPublicKey($raw = false)
    {
        if ($raw) {
            return \ParagonIE_Sodium_Compat::crypto_sign_publickey_from_secretkey($this->secretKey);
        }
        return Hex::encode(
            \ParagonIE_Sodium_Compat::crypto_sign_publickey_from_secretkey($this->secretKey)
        );
    }

    /**
     * Sign and save the combined CA-Cert file.
     *
     * @return bool
     * @throws CertaintyException
     * @throws \SodiumException
     */
    public function save()
    {
        if (!$this->secretKey) {
            throw new CertaintyException(
                'No signing key provided.'
            );
        }
        if (!$this->outputJson) {
            throw new CertaintyException(
                'No output file path for JSON data specified.'
            );
        }
        if (!$this->outputPem) {
            throw new CertaintyException(
                'No output file path for combined certificates specified.'
            );
        }
        /** @var string $return */
        $return = \file_put_contents($this->outputPem, $this->contents);
        if (!\is_int($return)) {
            throw new FilesystemException('Could not save PEM file.');
        }
        $sha256sum = \hash('sha256', $this->contents);
        $signature = \ParagonIE_Sodium_Compat::crypto_sign_detached(
            $this->contents,
            $this->secretKey
        );

        if (\file_exists($this->outputJson)) {
            /** @var string $fileData */
            $fileData = \file_get_contents($this->outputJson);
            /** @var array|bool $json */
            $json = \json_decode($fileData, true);
            if (!\is_array($json)) {
                throw new EncodingException('Invalid JSON data stored in file.');
            }
        } else {
            $json = [];
        }
        $pieces = \explode('/', \trim($this->outputPem, '/'));

        // Put at the front of the array
        $entry = [
            'custom' => \get_class($this->customValidator),
            'date' => \date('Y-m-d'),
            'file' => \array_pop($pieces),
            'sha256' => $sha256sum,
            'signature' => Hex::encode($signature),
            'trust-channel' => $this->trustChannel
        ];

        $chronicleHash = $this->commitToChronicle($sha256sum, $signature);
        if (!empty($chronicleHash)) {
            $entry['chronicle'] = $chronicleHash;
        }

        \array_unshift($json, $entry);
        $jsonSave = \json_encode($json, JSON_PRETTY_PRINT);
        if (!\is_string($jsonSave)) {
            throw new EncodingException(\json_last_error_msg());
        }
        $this->sha256sum = $sha256sum;
        $this->signature = $signature;

        $return = \file_put_contents($this->outputJson, $jsonSave);
        return \is_int($return);
    }

    /**
     * Configure the local Chronicle.
     *
     * @param string $url
     * @param string $publicKey
     * @param string $clientId
     * @param string $repository
     * @return self
     * @throws CryptoException
     */
    public function setChronicle(
        $url = '',
        $publicKey = '',
        $clientId = '',
        $repository = 'paragonie/certainty'
    ) {
        if (\ParagonIE_Sodium_Core_Util::strlen($publicKey) === 64) {
            $publicKey = Hex::decode($publicKey);
        } elseif (\ParagonIE_Sodium_Core_Util::strlen($publicKey) !== 32) {
            throw new CryptoException(
                'Signing secret keys must be SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES bytes long.'
            );
        }
        $this->chronicleClientId = $clientId;
        $this->chroniclePublicKey = $publicKey;
        $this->chronicleUrl = $url;
        $this->chronicleRepoName = $repository;
        return $this;
    }

    /**
     * Specify the fully qualified class name for your custom
     * Validator class.
     *
     * @param string $string
     * @return self
     * @throws \TypeError
     */
    public function setCustomValidator($string = '')
    {
        if (\class_exists($string)) {
            $newClass = new $string();
            if (!($newClass instanceof Validator)) {
                throw new \TypeError('Invalid validator class');
            }
            $this->customValidator = $newClass;
        }
        return $this;
    }

    /**
     * Specify the full path of the file that the combined CA-cert will be
     * written to when save() is invoked.
     *
     * @param string $string
     * @return self
     */
    public function setOutputPemFile($string = '')
    {
        $this->outputPem = $string;
        return $this;
    }

    /**
     * Specify the full path of the file that will contain the updated
     * sha256/Ed25519 metadata.
     *
     * @param string $string
     * @return self
     */
    public function setOutputJsonFile($string = '')
    {
        $this->outputJson = $string;
        return $this;
    }

    /**
     * Specify the signing key to be used.
     *
     * @param string $secretKey
     * @return self
     * @throws CryptoException
     */
    public function setSigningKey($secretKey = '')
    {
        // Handle hex-encoded strings.
        if (\ParagonIE_Sodium_Core_Util::strlen($secretKey) === 128) {
            $secretKey = Hex::decode($secretKey);
        } elseif (\ParagonIE_Sodium_Core_Util::strlen($secretKey) !== 64) {
            throw new CryptoException(
                'Signing secret keys must be SODIUM_CRYPTO_SIGN_SECRETKEYBYTES bytes long.'
            );
        }
        $this->secretKey = $secretKey;
        return $this;
    }

    /**
     * Don't leak secret keys.
     *
     * @return array
     */
    public function __debugInfo()
    {
        return [];
    }
}
