<?php declare(strict_types=1);

namespace PhpTuic\Runtime;

use PhpTuic\Config\NodeConfig;
use PhpTuic\Proxy\HttpProxyServer;
use PhpTuic\Proxy\Socks5ProxyServer;
use PhpTuic\Support\Platform;
use PhpTuic\Tuic\TuicClient;
use function Amp\trapSignal;

final class ProxyRunner
{
    public function __construct(
        private readonly NodeConfig $node,
        private readonly string $httpListen,
        private readonly string $socksListen,
        private readonly bool $enableHttp = true,
        private readonly bool $enableSocks = true,
    ) {
    }

    public function run(): void
    {
        $tuic = new TuicClient($this->node);
        $httpServer = null;
        $socksServer = null;

        try {
            if ($this->enableHttp) {
                $httpServer = new HttpProxyServer($tuic, $this->httpListen);
                $httpServer->start();
                fwrite(STDERR, "HTTP proxy listening on {$httpServer->getAddress()}\n");
            }

            if ($this->enableSocks) {
                $socksServer = new Socks5ProxyServer($tuic, $this->socksListen);
                $socksServer->start();
                fwrite(STDERR, "SOCKS5 proxy listening on {$socksServer->getAddress()}\n");
            }

            if ($httpServer === null && $socksServer === null) {
                throw new \RuntimeException('Both proxy listeners are disabled.');
            }

            fwrite(STDERR, "Press Ctrl+C to stop.\n");
            self::waitForShutdown();
        } finally {
            $httpServer?->stop();
            $socksServer?->stop();
            $tuic->close();
        }
    }

    public static function waitForShutdown(): void
    {
        $signals = [];
        if (\defined('SIGINT')) {
            $signals[] = SIGINT;
        }
        if (\defined('SIGTERM')) {
            $signals[] = SIGTERM;
        }

        if (Platform::canTrapSignals($signals)) {
            trapSignal($signals);

            return;
        }

        while (true) {
            usleep(250_000);
        }
    }
}
