<?php
namespace ParagonIE\Certainty;

use ParagonIE\Certainty\Exception\{
    BundleException,
    CertaintyException,
    EncodingException,
    FilesystemException
};
use SodiumException;

/**
 * Class Fetch
 * @package ParagonIE\Certainty
 */
class Fetch
{
    const bool CHECK_SIGNATURE_BY_DEFAULT = false;
    const bool CHECK_CHRONICLE_BY_DEFAULT = false;

    /**
     * @var string $dataDirectory
     */
    protected string $dataDirectory = '';

    /**
     * @var string $trustChannel
     */
    protected string $trustChannel = Certainty::TRUST_DEFAULT;

    /**
     * @var string $chronicleUrl
     */
    protected string $chronicleUrl = '';

    /**
     * @var string $chroniclePublicKey
     */
    protected string $chroniclePublicKey = '';

    /**
     * List of bundles that have just been downloaded (e.g. RemoteFetch)
     * @var array<int, string> $unverified
     */
    protected array $unverified = [];

    /**
     * Fetch constructor.
     *
     * You almost certainly want to use RemoteFetch instead.
     *
     * @param string $dataDir Where the certificates and configuration lives
     *
     * @throws CertaintyException
     */
    public function __construct(string $dataDir)
    {
        if (!\is_readable($dataDir)) {
            throw new FilesystemException('Directory is not readable: ' . $dataDir);
        }
        $this->dataDirectory = $dataDir;
    }

    /**
     * Get the latest bundle. Checks the SHA256 hash of the file versus what
     * is expected. Optionally checks the Ed25519 signature.
     *
     * @param bool|null $checkEd25519Signature Enforce Ed25519 signatures?
     * @param bool|null $checkChronicle        Require cert bundles be stored
     *                                         inside a Chronicle instance?
     * @return Bundle
     *
     * @throws CertaintyException
     * @throws SodiumException
     */
    public function getLatestBundle(bool $checkEd25519Signature = null, bool $checkChronicle = null): Bundle
    {
        $sodiumCompatIsntSlow = $this->sodiumCompatIsntSlow();
        if (\is_null($checkEd25519Signature)) {
            $checkEd25519Signature = (static::CHECK_SIGNATURE_BY_DEFAULT && $sodiumCompatIsntSlow);
        }
        $conditionalChronicle = \is_null($checkChronicle);
        if ($conditionalChronicle) {
            $checkChronicle = (static::CHECK_CHRONICLE_BY_DEFAULT && $sodiumCompatIsntSlow);
        }

        $bundleIndex = 0;
        $bundlesAvailable = $this->listBundles('', $this->trustChannel);
        if (empty($bundlesAvailable)) {
            throw new BundleException('No bundles were found in the data directory.');
        }
        foreach ($bundlesAvailable as $bundle) {
            if ($bundle->hasCustom()) {
                $validator = $bundle->getValidator();
            } else {
                $validator = new Validator($this->chronicleUrl, $this->chroniclePublicKey);
            }

            // If the SHA256 doesn't match, fail fast.
            if ($validator::checkSha256Sum($bundle)) {
                $valid = true;
                if ($checkEd25519Signature) {
                    $valid = $validator->checkEd25519Signature($bundle);
                    if (!$valid) {
                        $this->markBundleAsBad($bundleIndex, 'Ed25519 signature mismatch');
                    }
                }
                if ($conditionalChronicle && $checkChronicle) {
                    // Conditional Chronicle check (only on first brush):
                    $index = array_search($bundle->getFilePath(), $this->unverified, true);
                    if ($index !== false) {
                        $validChronicle = $validator->checkChronicleHash($bundle);
                        if ($validChronicle) {
                            unset($this->unverified[$index]);
                        } else {
                            $this->markBundleAsBad($bundleIndex, 'Chronicle');
                        }
                    }
                } elseif ($checkChronicle) {
                    // Always check Chronicle:
                    $valid = $valid && $validator->checkChronicleHash($bundle);
                }
                if ($valid) {
                    return $bundle;
                }
            } else {
                $this->markBundleAsBad($bundleIndex, 'SHA256 mismatch');
            }
            ++$bundleIndex;
        }
        throw new BundleException('No valid bundles were found in the data directory.');
    }

    /**
     * Get an array of all the Bundles, ordered most-recent to oldest.
     *
     * No validation is performed automatically.
     *
     * @param class-string $customValidator Fully-qualified class name for Validator
     * @return array<int, Bundle>
     *
     * @throws CertaintyException
     */
    public function getAllBundles(string $customValidator = ''): array
    {
        return \array_values(
            $this->listBundles(
                $customValidator,
                $this->trustChannel
            )
        );
    }

    /**
     * @param string $url
     * @param string $publicKey
     * @return self
     */
    public function setChronicle(string $url, string $publicKey): static
    {
        $this->chronicleUrl = $url;
        $this->chroniclePublicKey = $publicKey;
        return $this;
    }

    /**
     * @param int $index
     * @param string $reason
     * @return void
     * @throws EncodingException
     * @throws FilesystemException
     */
    protected function markBundleAsBad(int $index = 0, string $reason = ''): void
    {
        /** @var array<int, array<string, string>> $data */
        $data = $this->loadCaCertsFile();
        $now = (new \DateTime())->format(\DateTime::ATOM);
        $data[$index]['bad-bundle'] = 'Marked bad on ' . $now . ' for reason: ' . $reason;
        \file_put_contents(
            $this->dataDirectory . '/ca-certs.json',
            json_encode($data, JSON_PRETTY_PRINT)
        );
    }

    /**
     * @return array
     * @throws EncodingException
     * @throws FilesystemException
     */
    protected function loadCaCertsFile(): array
    {
        if (!\file_exists($this->dataDirectory . '/ca-certs.json')) {
            throw new FilesystemException('ca-certs.json not found in data directory.');
        }
        if (!\is_readable($this->dataDirectory . '/ca-certs.json')) {
            throw new FilesystemException('ca-certs.json is not readable.');
        }
        $contents = \file_get_contents($this->dataDirectory . '/ca-certs.json');
        if (!\is_string($contents)) {
            throw new FilesystemException('ca-certs.json could not be read.');
        }
        /** @var array|bool $data */
        $data = \json_decode($contents, true);
        if (!\is_array($data)) {
            throw new EncodingException('ca-certs.json is not a valid JSON file.');
        }
        return (array) $data;
    }

    /**
     * List bundles
     *
     * @param string $customValidator Fully-qualified class name for Validator
     * @param string $trustChannel
     * @return array<int, Bundle>
     *
     * @throws CertaintyException
     */
    protected function listBundles(
        string $customValidator = '',
        string $trustChannel = Certainty::TRUST_DEFAULT
    ): array {
        $data = $this->loadCaCertsFile();
        $bundles = [];
        /** @var array<string, string> $row */
        foreach ($data as $row) {
            if (!isset($row['date'], $row['file'], $row['sha256'], $row['signature'], $row['trust-channel'])) {
                // The necessary keys are not defined.
                continue;
            }
            if (!file_exists($this->dataDirectory . '/' . $row['file'])) {
                // Skip nonexistent files
                continue;
            }
            if (!empty($row['bad-bundle'])) {
                // Bundle marked as "bad"
                continue;
            }
            if ($row['trust-channel'] !== $trustChannel) {
                // Only include these.
                continue;
            }
            $key = (int) (\preg_replace('/[^0-9]/', '', $row['date']) . '0000');
            while (isset($bundles[$key])) {
                ++$key;
            }
            $bundles[$key] = new Bundle(
                $this->dataDirectory . '/' . $row['file'],
                $row['sha256'],
                $row['signature'],
                !empty($row['custom']) ? $row['custom'] : $customValidator,
                isset($row['chronicle']) ? $row['chronicle'] : '',
                $trustChannel
            );
        }
        \krsort($bundles);
        return $bundles;
    }

    /**
     * @return bool
     *
     * @psalm-suppress RedundantCondition PHP_INT_SIZE is env-specific
     */
    protected function sodiumCompatIsntSlow(): bool
    {
        if (\extension_loaded('sodium')) {
            return true;
        }
        if (\extension_loaded('libsodium')) {
            return true;
        }
        return PHP_INT_SIZE !== 4;
    }
}
