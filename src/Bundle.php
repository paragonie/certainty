<?php
namespace ParagonIE\Certainty;

use ParagonIE\Certainty\Exception\CertaintyException;
use ParagonIE\Certainty\Exception\FilesystemException;
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
    /**
     * @var string $chronicleHash
     */
    protected $chronicleHash = '';

    /**
     * @var Validator $customValidator
     */
    protected $customValidator;

    /**
     * @var string $filePath
     */
    protected $filePath = '';

    /**
     * @var string $sha256sum
     */
    protected $sha256sum = '';

    /**
     * @var string $signature
     */
    protected $signature = '';

    /**
     * @var string $trustChannel
     */
    protected $trustChannel = Certainty::TRUST_DEFAULT;

    /**
     * Bundle constructor.
     *
     * @param string $filePath        Path to the CACert bundle
     * @param string $sha256sum       Hex-encoded string
     * @param string $signature       Hex-encoded string
     * @param string $customValidator Fully-Qualified Class Name
     * @param string $chronicleHash   Chronicle Hash
     * @param string $trustChannel    Default trust channel for this bundle
     * @throws \TypeError
     */
    public function __construct(
        $filePath = '',
        $sha256sum = '',
        $signature = '',
        $customValidator = '',
        $chronicleHash = '',
        $trustChannel = Certainty::TRUST_DEFAULT
    ) {
        $this->filePath = $filePath;
        $this->sha256sum = $sha256sum;
        $this->signature = $signature;
        $this->chronicleHash = $chronicleHash;
        if (!empty($customValidator)) {
            if (\class_exists($customValidator)) {
                $newClass = new $customValidator();
                if (!($newClass instanceof Validator)) {
                    throw new \TypeError('Invalid validator class');
                }
            }
        }
        if (!isset($newClass)) {
            $newClass = new Validator();
        }
        /** @var Validator $newClass */
        $this->customValidator = $newClass;
        $this->trustChannel = $trustChannel;
    }

    /**
     * Creates a symbolic link that points to this bundle.
     *
     * @param string $destination
     * @param bool $unlinkIfExists
     * @return bool
     * @throws CertaintyException
     */
    public function createSymlink($destination = '', $unlinkIfExists = false)
    {
        if (\file_exists($destination)) {
            if ($unlinkIfExists) {
                \unlink($destination);
            } else {
                throw new FilesystemException('Destination already exists.');
            }
        }
        return \symlink($this->filePath, $destination);
    }

    /**
     * @return string
     * @throws CertaintyException
     */
    public function getFileContents()
    {
        $contents = \file_get_contents($this->filePath);
        if (!\is_string($contents)) {
            throw new FilesystemException('Could not read file ' . $this->filePath);
        }
        return (string) $contents;
    }

    /**
     * @return string
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * Get the SHA256 hash of this bundle's contents. Defaults
     * to returning a hex-encoded string.
     *
     * @param bool $raw Return a raw binary string rather than hex-encoded?
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
     * Get the Ed25519 signature for this bundle. Defaults
     * to returning a hex-encoded string.
     *
     * @param bool $raw Return a raw binary string rather than hex-encoded?
     * @return string
     */
    public function getSignature($raw = false)
    {
        if ($raw) {
            return Hex::decode($this->signature);
        }
        return $this->signature;
    }

    /**
     * @return string
     */
    public function getTrustChannel()
    {
        return $this->trustChannel;
    }

    /**
     * Get the Chronicle hash (always base64url-encoded)
     *
     * @return string
     */
    public function getChronicleHash()
    {
        return $this->chronicleHash;
    }

    /**
     * Get the custom validator (assuming one is defined).
     *
     * @return Validator
     * @throws CertaintyException
     */
    public function getValidator()
    {
        if (!isset($this->customValidator)) {
            throw new CertaintyException('Custom class not defined');
        }
        return $this->customValidator;
    }

    /**
     * Does this Bundle need a custom validator? This is typically only true
     * if a custom CA cert is being employed in addition to the Mozilla bundles.
     *
     * @return bool
     */
    public function hasCustom()
    {
        return !empty($this->customValidator);
    }
}
