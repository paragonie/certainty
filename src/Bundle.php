<?php
namespace ParagonIE\Certainty;

use ParagonIE\ConstantTime\Hex;

/**
 * Class Bundle
 *
 * Encapsulates a certificate bundle
 *
 * @package ParagonIE\Certainty
 */
class Bundle
{
    /** @var string $filePath */
    protected $filePath = '';

    /** @var string $sha256sum */
    protected $sha256sum = '';

    /** @var string $signature */
    protected $signature = '';

    /**
     * Bundle constructor.
     * @param string $filePath
     * @param string $sha256sum
     * @param string $signature
     */
    public function __construct(
        $filePath = '',
        $sha256sum = '',
        $signature = ''
    ) {
        $this->filePath = $filePath;
        $this->sha256sum = $sha256sum;
        $this->signature = $signature;
    }

    /**
     * Create a symbolic link that points to this bundle?
     *
     * @param string $destination
     * @param bool $unlinkIfExists
     * @return bool
     * @throws \Exception
     */
    public function createSymlink($destination = '', $unlinkIfExists = false)
    {
        if (\file_exists($destination)) {
            if ($unlinkIfExists) {
                \unlink($destination);
            } else {
                throw new \Exception('Destination already exists.');
            }
        }
        return \symlink($this->filePath, $destination);
    }

    /**
     * @return string
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * @param bool $raw
     * @return string
     */
    public function getSha256Sum($raw = false)
    {
        if ($raw) {
            return Hex::decode($this->sha256sum);
        }
        return $this->sha256sum;
    }

    /**
     * @param bool $raw
     * @return string
     */
    public function getSignature($raw = false)
    {
        if ($raw) {
            return Hex::decode($this->signature);
        }
        return $this->signature;
    }
}
