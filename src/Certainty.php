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
    const ED25519_HEADER = 'Body-Signature-Ed25519';

    /**
     * @param Fetch|null $fetch
     * @return Client
     */
    public static function getGuzzleClient(Fetch $fetch = null)
    {
        if (\is_null($fetch)) {
            $fetch = new Fetch();
        }
        return new Client(
            [
                'curl.options' => [
                    // https://github.com/curl/curl/blob/6aa86c493bd77b70d1f5018e102bc3094290d588/include/curl/curl.h#L1927
                    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2 | (CURL_SSLVERSION_TLSv1 << 16)
                ],
                'verify' => $fetch->getLatestBundle()->getFilePath()
            ]
        );
    }
}
