# RemoteFetch

This downloads the latest CA certificates from our Github repository and caches them locally.

## Basic Usage

Using the `RemoteFetch` class is rather straightforward.

#### Basic Usage with cURL

```php
<?php
use ParagonIE\Certainty\RemoteFetch;

$fetcher = new RemoteFetch('/path/to/certainty/data');
$latestCACertBundle = $fetcher->getLatestBundle();

$ch = curl_init();
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_CAINFO, $latestCACertBundle->getFilePath());
```

#### Basic Usage with Guzzle

```php
<?php
use ParagonIE\Certainty\RemoteFetch;
use GuzzleHttp\Client;

$fetcher = new RemoteFetch('/path/to/certainty/data');
$latestCACertBundle = $fetcher->getLatestBundle();
$client = new Client();

$response = $client->request('POST', '/url', [
    'verify' => $latestCACertBundle->getFilePath() 
]);
```

#### Basic Usage with Streams

```php
<?php
use ParagonIE\Certainty\RemoteFetch;

$fetcher = new RemoteFetch('/path/to/certainty/data');
$latestCACertBundle = $fetcher->getLatestBundle();

$context = stream_context_create([
    'ssl' => [
        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
        'verify_peer' => true,
        'cafile' => $latestCACertBundle->getFilePath(),
        'verify_depth' => 5
    ]
]);

$data = file_get_contents(
    'https://php-chronicle.pie-hosted.com/chronicle/lookup/HuICLQCF_DWnQGbosC6fK8PuifQgIrRi2WYshB2erZY=',
    false,
    $context
);
```

#### Composer Integration

**Since version 2.2.0.**

You can have Certainty request an up-to-date bundle at runtime by ensuring
you add this entry to your composer.json file:

```json
{
  "scripts": {
    "post-autoload-dump": [
      "ParagonIE\\Certainty\\Composer::postAutoloadDump"
    ]
  }
}
```

Then, you can simply use the local `Fetch` class instead of `RemoteFetch` in
your application code. Every time you run `composer update`, it will fetch
the latest bundles from Certainty.
 
This is a great way to reduce your runtime performance overhead while
guaranteeing that you have the latest CACert bundle.

### Changing the Path or URL

By default, Certainty's `RemoteFetch` feature pulls from Github and uses the most recent CA-Cert
bundled with the source code to ensure Github is actually Github.

You can change the URL or local save directory either by passing string arguments to the constructor,
like so:

```php
<?php
use ParagonIE\Certainty\RemoteFetch;

// Custom local path and remote URL:
$fetcher = new RemoteFetch(
    '/var/www/common/certs',
    'https://raw.githubusercontent.com/your-organization/certainty-fork/master/data/'
);
```

### Changing the Time Between Remote Fetches

By default, RemoteFetch will check for new certificates at most once per day. To change this
timeout, you have two options: Pass a `DateInterval` to the constructor, or change it after the
object has been created.


```php
<?php
use ParagonIE\Certainty\RemoteFetch;

// Cleaner.
$fetcher = (new RemoteFetch('/path/to/certainty/data'))
    ->setCacheTimeout(new \DateInterval('PT06H'));

// Alternatively, the constructor approach:
$fetcher = new RemoteFetch(
    '/path/to/certainty/data',
    RemoteFetch::DEFAULT_URL,
    null, // automatically selects/configures Guzzle
    new \DateInterval('PT06H') // 6 hours
);
```

## Symlinks

Being able to fetch the most recent CA-Cert bundle's file path at runtime is the preferred usage
for Certainty, but some will prefer to create a symlink at a predictable location so they can use
that path in their code.

Certainty supports this usage.

```php
<?php
use ParagonIE\Certainty\RemoteFetch;

$latest = (new RemoteFetch('/path/to/certainty/data'))->getLatestBundle();

$latest->createSymlink('/path/to/cacert.pem', true);
```

The second argument, `true`, tells Certainty to remove the existing symlink if it already exists.

## Using a Different Chronicle

To use a different Chronicle instance (i.e. a replica of the PHP Chronicle
instead of the main instance), you can configure your `RemoteFetch` object
by calling the `setChronicle()` method with a URL and a public key.

```php
<?php
use ParagonIE\Certainty\RemoteFetch;
$remoteFetch = new RemoteFetch('/var/www/my-project/data/certs');
$remoteFetch->setChronicle(
    'https://php-chronicle-replica.pie-hosted.com/chronicle/replica/_vi6Mgw6KXBSuOFUwYA2H2GEPLawUmjqFJbCCuqtHzGZ',
    'MoavD16iqe9-QVhIy-ewD4DMp0QRH-drKfwhfeDAUG0='
);
```
