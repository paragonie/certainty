<?php
namespace ParagonIE\Certainty;

/**
 * Class Fetch
 * @package ParagonIE\Certainty
 */
class Fetch
{
    /** @var string $dataDirectory */
    protected $dataDirectory = '';

    /**
     * Fetch constructor.
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
     * @return Bundle
     * @throws \Exception
     */
    public function getLatestBundle()
    {
        foreach ($this->listBundles() as $bundle) {
            if (Validator::checkSha256Sum($bundle) && Validator::checkEd25519Signature($bundle)) {
                return $bundle;
            }
        }
        throw new \Exception('No valid bundles were found in the data directory.');
    }

    /**
     * @return array<int, Bundle>
     */
    public function getAllBundles()
    {
        return \array_values($this->listBundles());
    }

    /**
     * @return array<int, Bundle>
     * @throws \Exception
     */
    protected function listBundles()
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
            $key = (int) (\preg_replace('/[^0-9]/', '', $row['date']));
            $bundles[$key] = new Bundle(
                $this->dataDirectory . '/' . $row['file'],
                $row['sha256'],
                $row['signature']
            );
        }
        \krsort($bundles);
        return $bundles;
    }
}
