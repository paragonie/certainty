<?php
require '../vendor/autoload.php';

if (!\file_exists('keys.json')) {
    echo 'Keys don\'t exist!', PHP_EOL;
    exit(127);
}
if ($argc < 2) {
    echo 'Usage: php signer.php /path/to/cacert.pem', PHP_EOL;
    exit(1);
}
$file = \file_get_contents('keys.json');
$json = \json_decode($file, true);

$secretKey = \ParagonIE\ConstantTime\Hex::decode($json['secret-key']);
$publicKey = \ParagonIE\ConstantTime\Hex::decode($json['public-key']);

$signature = ParagonIE_Sodium_File::sign($argv[1], $secretKey);

if (!\ParagonIE_Sodium_File::verify($signature, $argv[1], $publicKey)) {
    echo 'FAIL!', PHP_EOL;
    exit(127);
}

echo \ParagonIE\ConstantTime\Hex::encode($signature), PHP_EOL;
