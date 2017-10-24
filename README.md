# Certainty - CA-Cert Automation for PHP Projects

[![Build Status](https://travis-ci.org/paragonie/certainty.svg?branch=master)](https://travis-ci.org/paragonie/certainty)
[![Latest Stable Version](https://poser.pugx.org/paragonie/certainty/v/stable)](https://packagist.org/packages/paragonie/certainty)
[![Latest Unstable Version](https://poser.pugx.org/paragonie/certainty/v/unstable)](https://packagist.org/packages/paragonie/certainty)
[![License](https://poser.pugx.org/paragonie/certainty/license)](https://packagist.org/packages/paragonie/certainty)
[![Downloads](https://img.shields.io/packagist/dt/paragonie/certainty.svg)](https://packagist.org/packages/paragonie/certainty)

Automate your PHP projects' cacert.pem management.
[Read the blog post introducing Certainty](https://paragonie.com/blog/2017/10/certainty-automated-cacert-pem-management-for-php-software).

**Requires PHP 5.6 or newer.**

### Motivation

Many HTTP libraries require you to specify a file path to a `cacert.pem` file in order to use TLS correctly.
Omitting this file means either disabling certificate validation entirely (which enables trivial man-in-the-middle
exploits), connection failures, or hoping that your library falls back safely to the operating system's bundle.

In short, the possible outcomes (from best to worst) are as follows:

1. Specify a cacert file, and you get to enjoy TLS as it was intended. (Secure.)
2. Omit a cacert file, and the OS maybe bails you out. (Uncertain.)
3. Omit a cacert file, and it fails closed. (Connection failed. Angry customers.)
4. Omit a cacert file, and it fails open. (Data compromised. Hurt customers. Expensive legal proceedings.)

Obviously, the first outcome is optimal. So we built *Certainty* to make it easier to ensure open
source projects do this.

## Installing Certainty

From Composer:

```bash
composer require paragonie/certainty:dev-master
```

Due to the nature of CA Certificates, you want to use `dev-master`. If a major CA gets compromised and
their certificates are revoked, you don't want to continue trusting these certificates.

## What Certainty Does

Certainty maintains a repository of all the `cacert.pem` files since 2017, along with a sha256sum and
Ed25519 signature of each file. When you request the latest bundle, Certainty will check both these
values (the latter can only be signed by a key held by Paragon Initiative Enterprises, LLC) for each
entry in the JSON value, and return the latest bundle that passes validation. This prevents sneaky
additions of unauthorized CA certificates from escaping detection.

The cacert.pem files contained within are [reproducible from Mozilla's bundle](https://curl.haxx.se/docs/mk-ca-bundle.html).

## Using Certainty

### Getting the Path to the Latest CACert Bundle at Run-Time

You can just fetch the latest bundle's path at runtime. For example, using cURL:

```php
<?php
$latestBundle = (new \ParagonIE\Certainty\Fetch())
    ->getLatestBundle();

$ch = curl_init();
//  ... snip ...
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_CAINFO, $latestBundle->getFilePath());
``` 

### Create Symlink to Latest CACert

After running `composer update`, simply run a script that executes the following.

```php
<?php
(new \ParagonIE\Certainty\Fetch())
    ->getLatestBundle()
    ->createSymlink('/path/to/cacert.pem');
```

Then, make sure your HTTP library is using the cacert path provided.

```php
<?php

$ch = curl_init();
//  ... snip ...
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_CAINFO, '/path/to/cacert.perm');
``` 

This approach is useful if you're using at third-party library that expects a cacert.pem file at
a hard-coded location. However, you should **prefer** the first approach. 
