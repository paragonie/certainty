# Using Certainty with Custom CA Certificates

So you want to run your own in-house certificate authority. Certainty was designed to make
your life easier, by allowing you to:

* Keep your users up-to-date with the latest CA-Cert bundles (like all Certainty users)
* Bundle your in-house CA Certificate(s) alongside the Mozilla list

This allows your internal CA to be trusted by the software deployed on your network without
causing CA validation pains for external Internet resources. 

However, this is an advanced use-case, so it requires a little bit of work to get started.

## Getting Started with Custom CAs

### Generate a Keypair

We include a script in the `local` directory for generating a keypair.

```bash
php local/keygen.php
```

This will save an Ed25519 keypair to a file called keys.json.

### Create a Custom Validator

For example:

```php
<?php
namespace AcmeCorp\Certainty;

use ParagonIE\Certainty\Validator;

class CustomValidator extends Validator
{
    const PRIMARY_SIGNING_PUBKEY = 'your hex-encoded public key goes here';
    
    // Blank these values to disable Chronicle verification:
    const CHRONICLE_URL = '';
    const CHRONICLE_PUBKEY = ''; // Base64url-encoded
}
```

This is what your end users will need.

### Sign and Publish Your CA-Cert Bundle

At a very high level overview, this is what the workflow will look like:

1. Update the CA-Cert bundle locally.
2. Append your CA certificates to the latest verified bundle.
3. Sign your latest bundle and prepend it to the ca-certs.json file.
4. Publish your updated data directory.

It sounds like a lot, but you really only need to execute a script that does this:

```php
<?php
use ParagonIE\Certainty\LocalCACertBuilder;
use ParagonIE\Certainty\RemoteFetch;
use ParagonIE\ConstantTime\Hex;

$latest = (new RemoteFetch('/path/to/certainty/data'))->getLatestBundle();

LocalCACertBuilder::fromBundle($latest)
    ->setSigningKey(Hex::decode('your hex-encoded secret key goes here'))
    ->appendCACertFile('/path/to/your-in-house-ca-certs.pem')
    ->setOutputJsonFile('/path/to/output/ca-certs.json')
    ->setOutputPemFile('/path/to/output/cacert-' . date('Y-m-d') . '.pem')
    ->save();
```

Once you are satisfied, you can publish your data directory and your users, whom will be using
your custom Validator alongside [`RemoteFetch`](RemoteFetch.md), should always be up-to-date.

### Chronicle Verification for Custom CAs

The default Validator is configured to verify that all updates are published to the PHP
Community's Chronicle instance. You can either chose to opt out of verification, or 
[run your own Chronicle](https://github.com/paragonie/chronicle/tree/master/docs). 

Once you have one setup, all you need to do is update your `LocalCACertBuilder` code with
the Client ID, Public Key, and URL.

```php
<?php
use ParagonIE\Certainty\LocalCACertBuilder;
use ParagonIE\Certainty\RemoteFetch;
use ParagonIE\ConstantTime\Hex;

$latest = (new RemoteFetch('/path/to/certainty/data'))->getLatestBundle();

/* This snippet is mostly identical from the previous one. */
LocalCACertBuilder::fromBundle($latest)
    ->setSigningKey(Hex::decode('your hex-encoded secret key goes here'))
    ->appendCACertFile('/path/to/your-in-house-ca-certs.pem')
    ->setOutputJsonFile('/path/to/output/ca-certs.json')
    ->setOutputPemFile('/path/to/output/cacert-' . date('Y-m-d') . '.pem')
    /* This is the new part: */
    ->setChronicle(
        'https://foo-chronicle.example.com/',
         '<your public key (base64url)>',
         '<client id for this public key>',
         'acme-company/local-certainty-ca'
     )
    /* You always save() at the end */
    ->save();
```