<?php declare(strict_types=1);

namespace PhpTuic\Runtime;

use PhpTuic\Config\NodeConfig;
use PhpTuic\Proxy\Socks5ProxyServer;
use PhpTuic\Support\Platform;
use PhpTuic\Tuic\TuicClient;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

final class ProxyRunner
{
    private readonly LoopInterface $loop;
    private readonly RuntimeLogger $logger;
    private readonly ServerStats $stats;
    private readonly IpAccessList $accessList;
    private ?StatusFileWriter $statusWriter = null;
    private ?TimerInterface $statusTimer = null;

    public function __construct(
        private readonly NodeConfig $node,
        private readonly RunOptions $options,
        private readonly ?string $quicheLibrary = null,
        ?LoopInterface $loop = null,
    ) {
        $this->loop = $loop ?? Loop::get();
        $this->logger = new RuntimeLogger($this->options->verbose, $this->options->logFile);
        $this->stats = new ServerStats();
        $this->accessList = IpAccessList::fromStrings($this->options->allowIps);
    }

    public function run(): void
    {
        $this->installErrorHandlers();
        $this->writePidFile();

        $tuic = new TuicClient(
            node: $this->node,
            loop: $this->loop,
            options: $this->options,
            stats: $this->stats,
            logger: $this->logger,
            quicheLibrary: $this->quicheLibrary,
        );
        $socks = new Socks5ProxyServer(
            tuicClient: $tuic,
            listenAddress: $this->options->listenAddress,
            loop: $this->loop,
            accessList: $this->accessList,
            stats: $this->stats,
            options: $this->options,
            logger: $this->logger,
        );

        try {
            $socks->start();
            $this->startStatusReporting();
            $this->logger->info('SOCKS5 proxy listening.', [
                'listen' => $socks->getAddress(),
                'server' => $this->node->server,
                'port' => $this->node->port,
                'max_connections' => $this->options->maxConnections,
                'allow_ips' => $this->accessList->entries(),
                'pid' => getmypid(),
            ]);
            $this->registerSignals($socks, $tuic);
            $this->loop->run();
        } finally {
            $this->flushStatus();
            if ($this->statusTimer !== null) {
                $this->loop->cancelTimer($this->statusTimer);
                $this->statusTimer = null;
            }
            $socks->stop();
            $tuic->close();
            $this->removePidFile();
        }
    }

    private function registerSignals(Socks5ProxyServer $socks, TuicClient $tuic): void
    {
        $signals = [];
        if (defined('SIGINT')) {
            $signals[] = SIGINT;
        }
        if (defined('SIGTERM')) {
            $signals[] = SIGTERM;
        }

        if (!Platform::canTrapSignals($signals)) {
            return;
        }

        foreach ($signals as $signal) {
            $this->loop->addSignal($signal, function () use ($socks, $tuic): void {
                $this->logger->info('Received shutdown signal.', ['signal' => $signal]);
                $socks->stop();
                $tuic->close();
                $this->loop->stop();
            });
        }
    }

    private function startStatusReporting(): void
    {
        if ($this->options->statusFile === null) {
            return;
        }

        $this->statusWriter = new StatusFileWriter($this->options->statusFile);
        $this->flushStatus();
        $this->statusTimer = $this->loop->addPeriodicTimer($this->options->statusInterval, function (): void {
            $this->flushStatus();
        });
    }

    private function flushStatus(): void
    {
        if ($this->statusWriter === null) {
            return;
        }

        try {
            $path = $this->statusWriter->write([
                'updated_at' => date(DATE_ATOM),
                'pid' => getmypid(),
                'listen' => $this->options->listenAddress,
                'node' => [
                    'name' => $this->node->name,
                    'server' => $this->node->server,
                    'port' => $this->node->port,
                    'sni' => $this->node->sni,
                    'alpn' => $this->node->alpn,
                    'skip_cert_verify' => $this->node->skipCertVerify,
                ],
                'runtime' => [
                    'max_connections' => $this->options->maxConnections,
                    'allow_ips' => $this->accessList->entries(),
                    'connect_timeout' => $this->options->connectTimeout,
                    'idle_timeout_seconds' => $this->options->idleTimeoutSeconds,
                    'handshake_timeout' => $this->options->handshakeTimeout,
                    'status_interval' => $this->options->statusInterval,
                    'log_file' => $this->options->logFile,
                    'pid_file' => $this->options->pidFile,
                ],
                'stats' => $this->stats->toArray(),
            ]);

            $this->logger->debug('Status snapshot written.', ['path' => $path]);
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to write status file.', ['message' => $throwable->getMessage()]);
        }
    }

    private function installErrorHandlers(): void
    {
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            $this->logger->error('PHP runtime error.', [
                'severity' => $severity,
                'message' => $message,
                'file' => $file,
                'line' => $line,
            ]);

            return false;
        });

        set_exception_handler(function (\Throwable $throwable): void {
            $this->logger->error('Unhandled exception.', [
                'type' => $throwable::class,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ]);
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error === null || !in_array($error['type'] ?? null, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                return;
            }

            $this->logger->error('Fatal shutdown error.', $error);
        });
    }

    private function writePidFile(): void
    {
        if ($this->options->pidFile === null) {
            return;
        }

        $directory = dirname($this->options->pidFile);
        if ($directory !== '' && $directory !== '.' && !is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($this->options->pidFile, (string) getmypid() . PHP_EOL, LOCK_EX);
    }

    private function removePidFile(): void
    {
        if ($this->options->pidFile !== null && is_file($this->options->pidFile)) {
            @unlink($this->options->pidFile);
        }
    }
}
