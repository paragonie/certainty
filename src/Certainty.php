<?php
namespace ParagonIE\Certainty;

use GuzzleHttp\Client;
use ParagonIE\Certainty\Exception\CertaintyException;

/**
 * Class Certainty
 * @package ParagonIE\Certainty
 */
class Certainty
{
    const REPOSITORY = 'paragonie/certainty';
    const CHRONICLE_CLIENT_ID = 'Chronicle-Client-Key-ID';
    const ED25519_HEADER = 'Body-Signature-Ed25519';
    const TRUST_DEFAULT = 'Mozilla';

    /**
     * @param Fetch|null $fetch
     * @param int $timeout
     *
     * @return Client
     * @throws \SodiumException
     */
    public static function getGuzzleClient(Fetch $fetch = null, $timeout = 5)
    {
        $options = ['verify' => true];
        if (!\is_null($fetch)) {
            try {
                $options['verify'] = $fetch->getLatestBundle()->getFilePath();
            } catch (CertaintyException $ex) {
                // Fail closed just for usability. We're verifying anyway.
            }
        }

        if (\defined('CURLOPT_SSLVERSION') && \defined('CURL_SSLVERSION_TLSv1_2') && \defined('CURL_SSLVERSION_TLSv1')) {
            // https://github.com/curl/curl/blob/6aa86c493bd77b70d1f5018e102bc3094290d588/include/curl/curl.h#L1927
            $options['curl.options'][CURLOPT_SSLVERSION] = CURL_SSLVERSION_TLSv1_2 | (CURL_SSLVERSION_TLSv1 << 16);
        }
        $options['connect_timeout'] = (int) $timeout;

        return new Client($options);
    }
}
