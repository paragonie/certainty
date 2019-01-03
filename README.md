# Certainty - CA-Cert Automation for PHP Projects

[![Build Status](https://travis-ci.org/paragonie/certainty.svg?branch=master)](https://travis-ci.org/paragonie/certainty)
[![Latest Stable Version](https://poser.pugx.org/paragonie/certainty/v/stable)](https://packagist.org/packages/paragonie/certainty)
[![Latest Unstable Version](https://poser.pugx.org/paragonie/certainty/v/unstable)](https://packagist.org/packages/paragonie/certainty)
[![License](https://poser.pugx.org/paragonie/certainty/license)](https://packagist.org/packages/paragonie/certainty)
[![Downloads](https://img.shields.io/packagist/dt/paragonie/certainty.svg)](https://packagist.org/packages/paragonie/certainty)

Automate your PHP projects' cacert.pem management.
[Read the blog post introducing Certainty](https://paragonie.com/blog/2017/10/certainty-automated-cacert-pem-management-for-php-software).

**Requires PHP 5.5 or newer.**
Certainty should work on any operating system (including Windows), although the symlink
feature may not function in Virtualbox Shared Folders.

## Who is Certainty meant for?

* Open source developers with no control over where their code is deployed
  (e.g. Magento module developers).
* People whose code might be deployed in weird environments with CACert 
  bundles that are outdated or in unpredictable locations.
* People who are generally forced between:
  1. Disabling certificate validation entirely, or
  2. Increasing their support burden to deal with corner-cases where suddenly
     HTTP requests are failing on weird systems

Certainty allows your software to "just work" (which is usually the motivation
for disabling certificate validation) without being vulnerable to man-in-the-middle
attacks.

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
composer require paragonie/certainty:^2
```

Certainty will keep certificates up to date via `RemoteFetch`, so you don't need to update
Certainty library just to get fresh CA-Cert bundles. Update only for bugfixes (especially
security fixes) and new features.

### Non-Supported Use Case:

If you are not using [`RemoteFetch`](docs/features/RemoteFetch.md) (which is strongly recommended
that you do, and we only provide support for systems that *do* use `RemoteFetch`), then you want
to use `dev-master` rather than a version constraint, due to the nature of CA Certificates.

If a major CA gets compromised and their certificates are revoked, you don't want to continue
trusting these certificates.

Furthermore, in the event of avoiding `RemoteFetch`, you should be running `composer update` at least
once per week to prevent stale CA-Cert files from causing issues.

## Using Certainty

See [the documentation](docs/README.md). 

## What Certainty Does

Certainty maintains a repository of all the `cacert.pem` files since 2017, along with a sha256sum and
Ed25519 signature of each file. When you request the latest bundle, Certainty will check both these
values (the latter can only be signed by a key held by Paragon Initiative Enterprises, LLC) for each
entry in the JSON value, and return the latest bundle that passes validation.

The cacert.pem files contained within are [reproducible from Mozilla's bundle](https://curl.haxx.se/docs/mk-ca-bundle.html).

### How is Certainty different from composer/ca-bundle?

The key differences are:

* Certainty will keep the CA-Cert bundles on your system up-to-date even if you do not
  run `composer update`.
* We sign our CA-Cert bundles using Ed25519, and check every update into the
  [PHP community Chronicle](https://php-chronicle.pie-hosted.com).

## Support Contracts

If your company uses this library in their products or services, you may be
interested in [purchasing a support contract from Paragon Initiative Enterprises](https://paragonie.com/enterprise).
