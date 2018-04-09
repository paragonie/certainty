<?php
namespace ParagonIE\Certainty;

use GuzzleHttp\Client;

/**
 * Class Certainty
 * @package ParagonIE\Certainty
 */
class Certainty
{
    const REPOSITORY = 'paragonie/certainty';
    const CHRONICLE_CLIENT_ID = 'Chronicle-Client-Key-ID';
    const ED25519_HEADER = 'Body-Signature-Ed25519';

    /**
     * @param Fetch|null $fetch
     *
     * @return Client
     * @throws Exception\BundleException
     * @throws Exception\EncodingException
     * @throws Exception\FilesystemException
     * @throws Exception\RemoteException
     * @throws \SodiumException
     */
    public static function getGuzzleClient(Fetch $fetch = null)
    {
        if (\is_null($fetch)) {
            $fetch = new Fetch();
        }
        $options = [
            'verify' => $fetch->getLatestBundle()->getFilePath()
        ];

        if (\defined('CURLOPT_SSLVERSION') && \defined('CURL_SSLVERSION_TLSv1_2') && \defined('CURL_SSLVERSION_TLSv1')) {
            // https://github.com/curl/curl/blob/6aa86c493bd77b70d1f5018e102bc3094290d588/include/curl/curl.h#L1927
            $options['curl.options'][CURLOPT_SSLVERSION] = CURL_SSLVERSION_TLSv1_2 | (CURL_SSLVERSION_TLSv1 << 16);
        }

        return new Client($options);
    }
}
