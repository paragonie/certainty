<?php
namespace ParagonIE\Certainty;

/**
 * Class Fetch
 * @package ParagonIE\Certainty
 */
class Fetch
{
    const CHECK_SIGNATURE_BY_DEFAULT = false;

    /** @var string $dataDirectory */
    protected $dataDirectory = '';

    /**
     * Fetch constructor.
     *
     * You almost certainly want to use RemoteFetch instead.
     *
     * @param string $dataDir
     */
    public function __construct($dataDir = '')
    {
        if (!empty($dataDir) && \is_readable($dataDir)) {
            $this->dataDirectory = $dataDir;
        } else {
            $this->dataDirectory = \dirname(__DIR__) . '/data';
        }
    }

    /**
     * Get the latest bundle. Checks the SHA256 hash of the file versus what
     * is expected. Optionally checks the Ed25519 signature.
     *
     * @param bool|null $checkEd25519Signature
     * @return Bundle
     * @throws \Exception
     */
    public function getLatestBundle($checkEd25519Signature = null)
    {
        if (\is_null($checkEd25519Signature)) {
            $checkEd25519Signature = (bool) static::CHECK_SIGNATURE_BY_DEFAULT;
        }
        /** @var Bundle $bundle */
        foreach ($this->listBundles() as $bundle) {
            if ($bundle->hasCustom()) {
                $validator = $bundle->getValidator();
                if ($validator::checkSha256Sum($bundle)) {
                    if (!$checkEd25519Signature) {
                        return $bundle;
                    } elseif ($validator::checkEd25519Signature($bundle)) {
                        return $bundle;
                    }
                }
            } elseif (Validator::checkSha256Sum($bundle)) {
                if (!$checkEd25519Signature) {
                    return $bundle;
                } elseif (Validator::checkEd25519Signature($bundle)) {
                    return $bundle;
                }
            }
        }
        throw new \Exception('No valid bundles were found in the data directory.');
    }

    /**
     * Get an array of all of the Bundles, ordered most-recent to oldest.
     *
     * No validation is perforemd automatically.
     *
     * @param string $customValidator
     * @return array<int, Bundle>
     */
    public function getAllBundles($customValidator = '')
    {
        return \array_values($this->listBundles($customValidator));
    }

    /**
     * List bundles
     *
     * @param string $customValidator
     * @return array<int, Bundle>
     * @throws \Exception
     */
    protected function listBundles($customValidator = '')
    {
        if (!\file_exists($this->dataDirectory . '/ca-certs.json')) {
            throw new \Exception('ca-certs.json not found in data directory.');
        }
        if (!\is_readable($this->dataDirectory . '/ca-certs.json')) {
            throw new \Exception('ca-certs.json is not readable.');
        }
        $contents = \file_get_contents($this->dataDirectory . '/ca-certs.json');
        if (!\is_string($contents)) {
            throw new \Exception('ca-certs.json could not be read.');
        }
        $data = \json_decode($contents, true);
        if (!\is_array($data)) {
            throw new \Exception('ca-certs.json is not a valid JSON file.');
        }
        $bundles = [];
        foreach ($data as $row) {
            if (!isset($row['date'], $row['file'], $row['sha256'], $row['signature'])) {
                // No
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
                !empty($row['custom']) ? $row['custom'] : $customValidator
            );
        }
        \krsort($bundles);
        return $bundles;
    }
}
