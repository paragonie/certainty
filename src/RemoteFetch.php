<?php
namespace ParagonIE\Certainty;

use GuzzleHttp\Client;
use ParagonIE\Certainty\Exception\EncodingException;
use ParagonIE\Certainty\Exception\FilesystemException;
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
     *
     * @throws Exception\BundleException
     * @throws \Exception
     * @throws \TypeError
     * @psalm-suppress RedundantConditionGivenDocblockType
     */
    public function __construct(
        $dataDir = '',
        $url = self::DEFAULT_URL,
        Client $http = null,
        $timeout = null
    ) {
        parent::__construct($dataDir);
        $this->url = $url;

        if (\is_null($http)) {
            if (\file_exists($this->dataDirectory . '/ca-certs.json')) {
                $http = Certainty::getGuzzleClient(new Fetch($this->dataDirectory));
            } else {
                $http = new Client();
            }
        }
        /** @var Client $http */
        $this->http = $http;

        if (\is_null($timeout)) {
            /* Default: 24 hours */
            $timeoutObj = new \DateInterval('P01D');
        } elseif (\is_string($timeout)) {
            $timeoutObj = new \DateInterval($timeout);
        } elseif ($timeout instanceof \DateInterval) {
            $timeoutObj = $timeout;
        } else {
            throw new \TypeError('Invalid timeout. Expected a DateInterval or string.');
        }
        /** @var \DateInterval $timeoutObj */
        $this->cacheTimeout = $timeoutObj;
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
        $expires = (new \DateTime($cacheTime))->add($this->cacheTimeout);
        return $expires <= new \DateTime('NOW');
    }

    /**
     * List bundles
     *
     * @param string $customValidator
     * @return array<int, Bundle>
     * @throws EncodingException
     * @throws FilesystemException
     * @throws NetworkException
     */
    protected function listBundles($customValidator = '')
    {
        if ($this->cacheExpired()) {
            if (!$this->remoteFetchBundles()) {
                throw new NetworkException('Could not download bundles');
            }
        }
        return parent::listBundles($customValidator);
    }

    /**
     * This handles the actual HTTP request.
     *
     * @return bool
     * @throws EncodingException
     */
    protected function remoteFetchBundles()
    {
        $request = $this->http->get($this->url . '/ca-certs.json');
        $body = (string) $request->getBody();
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
                $request = $this->http->get($this->url . '/' . $filename);
                $body = (string) $request->getBody();
                \file_put_contents($this->dataDirectory . '/' . $filename, $body);
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
