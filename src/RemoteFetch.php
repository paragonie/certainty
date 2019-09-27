<?php
namespace ParagonIE\Certainty;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use ParagonIE\Certainty\Exception\CertaintyException;
use ParagonIE\Certainty\Exception\EncodingException;
use ParagonIE\Certainty\Exception\NetworkException;

/**
 * Class RemoteFetch
 *
 * Fetches data over the network. Caches locally.
 *
 * @package ParagonIE\Certainty
 */
class RemoteFetch extends Fetch
{
    const CHECK_SIGNATURE_BY_DEFAULT = true;
    const CHECK_CHRONICLE_BY_DEFAULT = true;
    const DEFAULT_URL = 'https://raw.githubusercontent.com/paragonie/certainty/master/data/';

    /**
     * @var \DateInterval $cacheTimeout
     */
    protected $cacheTimeout;

    /**
     * @var Client $http
     */
    protected $http;

    /**
     * @var string $url
     */
    protected $url = '';

    /**
     * RemoteFetch constructor.
     *
     * @param string $dataDir
     * @param string $url
     * @param Client|null $http
     * @param \DateInterval|string|null $timeout
     * @param string $chronicleUrl
     * @param string $chroniclePublicKey
     * @param int $connectTimeout
     *
     * @throws CertaintyException
     * @throws \SodiumException
     * @throws \TypeError
     * @psalm-suppress RedundantConditionGivenDocblockType
     */
    public function __construct(
        $dataDir = '',
        $url = self::DEFAULT_URL,
        Client $http = null,
        $timeout = null,
        $chronicleUrl = '',
        $chroniclePublicKey = '',
        $connectTimeout = 5
    ) {
        parent::__construct($dataDir);
        $this->url = $url;

        if (\is_null($http)) {
            if (\file_exists($this->dataDirectory . '/ca-certs.json')) {
                $http = Certainty::getGuzzleClient(new Fetch($this->dataDirectory), $connectTimeout);
            } else {
                $http = Certainty::getGuzzleClient(new Fetch(__DIR__."/../data/"), $connectTimeout);
            }
        }
        /** @var Client $http */
        $this->http = $http;

        if (\is_null($timeout)) {
            /* Default: 24 hours */
            try {
                $timeoutObj = new \DateInterval('P01D');
            } catch (\Exception $ex) {
                throw new CertaintyException('Invalid DateInterval', 0, $ex);
            }
        } elseif (\is_string($timeout)) {
            try {
                $timeoutObj = new \DateInterval($timeout);
            } catch (\Exception $ex) {
                throw new CertaintyException('Invalid DateInterval', 0, $ex);
            }
        } elseif ($timeout instanceof \DateInterval) {
            $timeoutObj = $timeout;
        } else {
            throw new \TypeError('Invalid timeout. Expected a DateInterval or string.');
        }
        /** @var \DateInterval $timeoutObj */
        $this->cacheTimeout = $timeoutObj;
        if (isset($chronicleUrl, $chroniclePublicKey)) {
            $this->setChronicle($chronicleUrl, $chroniclePublicKey);
        }
    }

    /**
     * Do we need to fetch updates?
     *
     * @return bool
     */
    public function cacheExpired()
    {
        if (!\file_exists($this->dataDirectory . '/ca-certs.cache')) {
            return true;
        }
        $cacheTime = \file_get_contents($this->dataDirectory . '/ca-certs.cache');
        if (!\is_string($cacheTime)) {
            return true;
        }
        try {
            $expires = (new \DateTime($cacheTime))->add($this->cacheTimeout);
            return $expires <= new \DateTime('NOW');
        } catch (\Exception $ex) {
        }
        return true;
    }

    /**
     * List bundles
     *
     * @param string $customValidator
     * @param string $trustChannel
     *
     * @return array<int, Bundle>
     * @throws CertaintyException
     */
    protected function listBundles(
        $customValidator = '',
        $trustChannel = Certainty::TRUST_DEFAULT
    ) {
        if ($this->cacheExpired()) {
            if (!$this->remoteFetchBundles()) {
                throw new NetworkException('Could not download bundles');
            }
        }
        return parent::listBundles($customValidator, $trustChannel);
    }

    /**
     * This handles the actual HTTP request.
     *
     * @return bool
     * @throws EncodingException
     */
    protected function remoteFetchBundles()
    {
        /** @var Request $request */
        $request = $this->http->get($this->url . '/ca-certs.json');
        /** @var string $body */
        $body = (string) $request->getBody();
        /** @var array|bool $jsonDecoded */
        $jsonDecoded = \json_decode($body, true);
        if (!\is_array($jsonDecoded)) {
            throw new EncodingException(\json_last_error_msg());
        }

        if (\file_exists($this->dataDirectory . '/ca-certs.json')) {
            \rename(
                $this->dataDirectory . '/ca-certs.json',
                $this->dataDirectory . '/ca-certs-backup-' . \date('YmdHis') . '.json'
            );
        }
        \file_put_contents($this->dataDirectory . '/ca-certs.json', $body);

        /**
         * @var array<string, string> $item
         */
        foreach ($jsonDecoded as $item) {
            if (!isset($item['file'])) {
                continue;
            }
            $filename = $item['file'];
            if (!\preg_match('#^cacert(\-[0-9]{4}\-[0-9]{2}\-[0-9]{2})?\.pem$#', $filename)) {
                // Invalid filename
                continue;
            }
            if (!\file_exists($this->dataDirectory . '/' . $filename)) {
                /** @var Request $request */
                $request = $this->http->get($this->url . '/' . $filename);
                /** @var string $body */
                $body = (string) $request->getBody();
                \file_put_contents($this->dataDirectory . '/' . $filename, $body);
                $this->unverified []= $this->dataDirectory . '/' . $item['file'];
            }
        }

        return !\is_bool(
            \file_put_contents(
                $this->dataDirectory . '/ca-certs.cache',
                (new \DateTime())->format(\DateTime::ATOM)
            )
        );
    }

    /**
     * @param \DateInterval $interval
     * @return self
     */
    public function setCacheTimeout(\DateInterval $interval)
    {
        $this->cacheTimeout = $interval;
        return $this;
    }

    /**
     * Replace the HTTP client with a new one.
     *
     * @param Client $client
     * @return $this
     */
    public function setHttpClient(Client $client)
    {
        $this->http = $client;
        return $this;
    }

    /**
     *
     * @param string $url
     * @return self
     */
    public function setRemoteSource($url = '')
    {
        $this->url = $url;
        return $this;
    }
}
