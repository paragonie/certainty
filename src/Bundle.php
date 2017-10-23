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
    /** @var Validator $customValidator */
    protected $customValidator;

    /** @var string $filePath */
    protected $filePath = '';

    /** @var string $sha256sum */
    protected $sha256sum = '';

    /** @var string $signature */
    protected $signature = '';

    /**
     * Bundle constructor.
     *
     * @param string $filePath
     * @param string $sha256sum
     * @param string $signature
     * @param string $customValidator
     * @throws \TypeError
     */
    public function __construct(
        $filePath = '',
        $sha256sum = '',
        $signature = '',
        $customValidator = ''
    ) {
        $this->filePath = $filePath;
        $this->sha256sum = $sha256sum;
        $this->signature = $signature;
        $newClass = new Validator();
        if (!empty($customValidator)) {
            if (\class_exists($customValidator)) {
                $newClass = new $customValidator();
                if (!($newClass instanceof Validator)) {
                    throw new \TypeError('Invalid validator class');
                }
            }
        }
        $this->customValidator = $newClass;
    }

    /**
     * Create a symbolic link that poinst to this bundle?
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

    /**
     * @return Validator
     * @throws \Exception
     */
    public function getValidator()
    {
        if (!isset($this->customValidator)) {
            throw new \Exception('Custom class not defined');
        }
        return $this->customValidator;
    }

    /**
     * @return bool
     */
    public function hasCustom()
    {
        return !empty($this->customValidator);
    }
}
