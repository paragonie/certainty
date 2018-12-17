# Certainty Documentation

Before you begin, which problem are you trying to solve?

* [I want my users to have always-up-to-date CA-Cert files](features/RemoteFetch.md)
* [I just want an updated CA-Cert file in a predictable location](features/RemoteFetch.md#symlinks)
* [I need to run a custom/internal certificate authority while also trusting the most recent CA certs](features/LocalCACertBuilder.md) (**Advanced**)

## Troubleshooting

### Cannot connect to https://php-chronicle.pie-hosted.com/chronicle

If you're having difficulties connecting to the PHP Chronicle, you can use
a replica instance of the PHP Chronicle.

#### PHP Chronicle Replicas for Certainty

| URL | Public Key | Operator |
| --- | ---------- | -------- |
| https://php-chronicle-replica.pie-hosted.com/chronicle/replica/_vi6Mgw6KXBSuOFUwYA2H2GEPLawUmjqFJbCCuqtHzGZ/ | `MoavD16iqe9-QVhIy-ewD4DMp0QRH-drKfwhfeDAUG0=` | [Paragon Initiative Enterprises](https://paragonie.com) | 

### I'm Getting a File Permission Error When Trying to Use Certainty

Make sure the `vendor/paragonie/certainty/data` directory is writable. For example:

```bash
chown -R webuser:webuser vendor/paragonie/certainty/data
chmod 0775 vendor/paragonie/certainty/data
chmod 0664 vendor/paragonie/certainty/data/* 
``` 
