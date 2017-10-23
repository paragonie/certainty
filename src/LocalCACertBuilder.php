<?php
namespace ParagonIE\Certainty;

use ParagonIE\ConstantTime\Hex;

/**
 * Class LocalCACertBuilder
 * @package ParagonIE\Certainty
 */
class LocalCACertBuilder extends Bundle
{
    /**
     * @var string
     */
    protected $contents = '';

    /**
     * @var string
     */
    protected $original = '';

    /**
     * @var string
     */
    protected $outputPem = '';

    /**
     * @var string
     */
    protected $outputJson = '';

    /**
     * @var string
     */
    protected $secretKey = '';

    /**
     * @param Bundle $old
     * @return self
     */
    public static function fromBundle(Bundle $old)
    {
        return new static(
            $old->getFilePath(),
            $old->getSha256Sum(),
            $old->getSignature(),
            $old->getValidator()
        );
    }

    /**
     * @return self
     * @throws \Exception
     */
    public function loadOriginal()
    {
        $this->original = \file_get_contents($this->filePath);
        if (!\is_string($this->original)) {
            throw new \Exception('Could not read contents of CACert file provided.');
        }
        return $this;
    }

    /**
     * @param string $path
     * @return self
     * @throws \Exception
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
            throw new \Exception('Could not read contents of CACert file provided.');
        }
        $this->contents .= $contents . "\n";
        return $this;
    }

    /**
     * @param bool $raw
     * @return string
     * @throws \Error
     * @throws \Exception
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
     * @throws \Exception
     * @return bool
     */
    public function save()
    {
        if (!$this->secretKey) {
            throw new \Exception('No signing key provided.');
        }
        if (!$this->outputJson) {
            throw new \Exception('No output file path for JSON data specified.');
        }
        if (!$this->outputPem) {
            throw new \Exception('No output file path for combined certificates specified.');
        }
        $return = \file_put_contents($this->outputPem, $this->contents);
        if (\is_bool($return)) {
            throw new \Exception('Could not save PEM file.');
        }
        $sha256sum = \hash('sha256', $this->contents);
        $signature = \ParagonIE_Sodium_Compat::crypto_sign_detached($this->contents, $this->secretKey);

        if (\file_exists($this->outputJson)) {
            $fileData = \file_get_contents($this->outputJson);
            $json = \json_decode($fileData, true);
            if (!\is_array($json)) {
                throw new \Exception('Invalid JSON data stored in file.');
            }
        } else {
            $json = [];
        }
        $pieces = \explode('/', \trim($this->outputPem, '/'));

        // Put at the front of the array
        \array_unshift($json, [
            'custom' => $this->customValidatorClass,
            'date' => \date('Y-m-d'),
            'file' => \array_pop($pieces),
            'sha256' => $sha256sum,
            'signature' => Hex::encode($signature)
        ]);
        $jsonSave = \json_encode($json, JSON_PRETTY_PRINT);
        if (!\is_string($jsonSave)) {
            throw new \Exception(\json_last_error_msg());
        }
        $this->sha256sum = $sha256sum;
        $this->signature = $signature;

        $return = \file_put_contents($this->outputJson, $jsonSave);
        return \is_int($return);
    }

    /**
     * @param string $string
     * @return self
     */
    public function setCustomValidator($string = '')
    {
        $this->customValidatorClass = $string;
        return $this;
    }

    /**
     * @param string $string
     * @return self
     */
    public function setOutputPemFile($string = '')
    {
        $this->outputPem = $string;
        return $this;
    }

    /**
     * @param string $string
     * @return self
     */
    public function setOutputJsonFile($string = '')
    {
        $this->outputJson = $string;
        return $this;
    }

    /**
     * @param string $secretKey
     * @return self
     */
    public function setSigningKey($secretKey = '')
    {
        $this->secretKey = $secretKey;
        return $this;
    }
}
