<?php
namespace ParagonIE\Certainty;

use Composer\Script\Event;

/**
 * Class Composer
 * @package ParagonIE\Certainty
 */
class Composer
{
    /**
     * @param Event $event
     *
     * @throws Exception\CertaintyException
     * @throws \SodiumException
     * @return void
     * @psalm-suppress UnresolvableInclude
     */
    public static function postAutoloadDump(Event $event)
    {
        if (\getenv('TRAVIS')) {
            // GnuTLS error
            return;
        }
        /** @var string $vendorDir */
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        require_once $vendorDir . '/autoload.php';

        $dataDir = \dirname($vendorDir) . '/data';
        (new RemoteFetch($dataDir))->getLatestBundle();

        echo '[OK] Remote Fetch of latest CACert Bundle', PHP_EOL;
    }
}
