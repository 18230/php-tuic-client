<?php declare(strict_types=1);

namespace PhpTuic\Runtime;

use PhpTuic\Config\NodeConfig;
use PhpTuic\Proxy\Socks5ProxyServer;
use PhpTuic\Tuic\TuicClient;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

final class ProxyRunner
{
    private readonly LoopInterface $loop;

    public function __construct(
        private readonly NodeConfig $node,
        private readonly string $socksListen,
        private readonly ?string $quicheLibrary = null,
        ?LoopInterface $loop = null,
    ) {
        $this->loop = $loop ?? Loop::get();
    }

    public function run(): void
    {
        $tuic = new TuicClient(
            node: $this->node,
            loop: $this->loop,
            quicheLibrary: $this->quicheLibrary,
        );
        $socks = new Socks5ProxyServer(
            tuicClient: $tuic,
            listenAddress: $this->socksListen,
            loop: $this->loop,
        );

        try {
            $socks->start();
            fwrite(STDERR, "SOCKS5 proxy listening on {$socks->getAddress()}\n");
            $this->registerSignals($socks, $tuic);
            $this->loop->run();
        } finally {
            $socks->stop();
            $tuic->close();
        }
    }

    private function registerSignals(Socks5ProxyServer $socks, TuicClient $tuic): void
    {
        if (PHP_OS_FAMILY === 'Windows' || !extension_loaded('pcntl')) {
            return;
        }

        foreach ([SIGINT, SIGTERM] as $signal) {
            $this->loop->addSignal($signal, function () use ($socks, $tuic): void {
                $socks->stop();
                $tuic->close();
                $this->loop->stop();
            });
        }
    }
}
