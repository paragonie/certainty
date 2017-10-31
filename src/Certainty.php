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
                    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
                ],
                'verify' => $fetch->getLatestBundle()->getFilePath()
            ]
        );
    }
}
