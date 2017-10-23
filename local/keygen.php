<?php
require '../vendor/autoload.php';

if (file_exists('keys.json')) {
    echo 'Keys already exist!', PHP_EOL;
    exit(127);
}

$keypair = ParagonIE_Sodium_Compat::crypto_sign_keypair();
$secret = ParagonIE_Sodium_Compat::crypto_sign_secretkey($keypair);
$public = ParagonIE_Sodium_Compat::crypto_sign_publickey($keypair);

\file_put_contents(
    'keys.json',
    \json_encode(
        [
            'secret-key' => bin2hex($secret),
            'public-key' => bin2hex($public)
        ],
        JSON_PRETTY_PRINT
    )
);
