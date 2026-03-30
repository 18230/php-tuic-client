<?php declare(strict_types=1);

namespace PhpTuic\Proxy;

use PhpTuic\Runtime\IpAccessList;
use PhpTuic\Runtime\RunOptions;
use PhpTuic\Runtime\RuntimeLogger;
use PhpTuic\Runtime\ServerStats;
use PhpTuic\Tuic\TuicClient;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

final class Socks5ProxyServer
{
    /** @var resource|null */
    private $server = null;
    private ?TimerInterface $sessionSweepTimer = null;

    /**
     * @var array<int, array{
     *     stream: resource,
     *     buffer: string,
     *     stage: 'greeting'|'request'|'connecting'|'closed',
     *     remote_ip: ?string,
     *     expires_at: float
     * }>
     */
    private array $sessions = [];

    private readonly LoopInterface $loop;

    public function __construct(
        private readonly TuicClient $tuicClient,
        private readonly string $listenAddress,
        private readonly IpAccessList $accessList,
        private readonly ServerStats $stats,
        private readonly RunOptions $options,
        private readonly RuntimeLogger $logger,
        ?LoopInterface $loop = null,
    ) {
        $this->loop = $loop ?? Loop::get();
    }

    public function start(): void
    {
        if (is_resource($this->server)) {
            return;
        }

        $endpoint = str_starts_with($this->listenAddress, 'tcp://')
            ? $this->listenAddress
            : 'tcp://' . $this->listenAddress;

        $server = @stream_socket_server($endpoint, $errno, $error);
        if (!is_resource($server)) {
            throw new \RuntimeException("Failed to listen on {$endpoint}: {$error} ({$errno})");
        }

        stream_set_blocking($server, false);
        $this->server = $server;
        $this->loop->addReadStream($server, fn () => $this->acceptClient());
        $this->sessionSweepTimer = $this->loop->addPeriodicTimer(1.0, function (): void {
            $this->closeExpiredSessions();
        });
    }

    public function stop(): void
    {
        if (is_resource($this->server)) {
            $this->loop->removeReadStream($this->server);
            fclose($this->server);
            $this->server = null;
        }

        if ($this->sessionSweepTimer !== null) {
            $this->loop->cancelTimer($this->sessionSweepTimer);
            $this->sessionSweepTimer = null;
        }

        foreach (array_keys($this->sessions) as $sessionId) {
            $this->closeSession($sessionId);
        }
    }

    public function getAddress(): string
    {
        if (!is_resource($this->server)) {
            return $this->listenAddress;
        }

        $address = stream_socket_get_name($this->server, false);

        return $address === false ? $this->listenAddress : $address;
    }

    private function acceptClient(): void
    {
        if (!is_resource($this->server)) {
            return;
        }

        while (($client = @stream_socket_accept($this->server, 0)) !== false) {
            $remoteAddress = stream_socket_get_name($client, true);
            $remoteIp = $this->extractRemoteIp($remoteAddress === false ? null : $remoteAddress);

            if (!$this->accessList->allows($remoteIp)) {
                $this->stats->recordAccessDenied();
                $this->logger->warning('Rejected local client outside allow list.', [
                    'remote_address' => $remoteAddress,
                ]);
                fclose($client);

                continue;
            }

            if ($this->stats->activeConnections() >= $this->options->maxConnections) {
                $this->stats->recordConnectionLimitRejected();
                $this->logger->warning('Rejected local client because the connection limit was reached.', [
                    'remote_address' => $remoteAddress,
                    'max_connections' => $this->options->maxConnections,
                ]);
                fclose($client);

                continue;
            }

            stream_set_blocking($client, false);
            $id = (int) $client;
            $this->sessions[$id] = [
                'stream' => $client,
                'buffer' => '',
                'stage' => 'greeting',
                'remote_ip' => $remoteIp,
                'expires_at' => microtime(true) + $this->options->handshakeTimeout,
            ];
            $this->stats->recordAcceptedConnection();
            $this->logger->debug('Accepted local client.', [
                'remote_address' => $remoteAddress,
                'active_connections' => $this->stats->activeConnections(),
            ]);

            $this->loop->addReadStream($client, fn () => $this->handleClient($id));
        }
    }

    private function handleClient(int $sessionId): void
    {
        if (!isset($this->sessions[$sessionId])) {
            return;
        }

        $stream = $this->sessions[$sessionId]['stream'];
        $chunk = @fread($stream, 65535);
        if ($chunk === false) {
            $this->closeSession($sessionId);

            return;
        }

        if ($chunk === '') {
            if (feof($stream)) {
                $this->closeSession($sessionId);
            }

            return;
        }

        $this->sessions[$sessionId]['expires_at'] = microtime(true) + $this->options->handshakeTimeout;
        $this->sessions[$sessionId]['buffer'] .= $chunk;

        if ($this->sessions[$sessionId]['stage'] === 'greeting') {
            $this->handleGreeting($sessionId);
        }

        if (($this->sessions[$sessionId]['stage'] ?? null) === 'request') {
            $this->handleRequest($sessionId);
        }
    }

    private function handleGreeting(int $sessionId): void
    {
        $buffer = $this->sessions[$sessionId]['buffer'];
        if (strlen($buffer) < 2) {
            return;
        }

        $version = ord($buffer[0]);
        $methodCount = ord($buffer[1]);
        if (strlen($buffer) < 2 + $methodCount) {
            return;
        }

        if ($version !== 0x05) {
            $this->logger->warning('Rejected client with invalid SOCKS version.', [
                'remote_ip' => $this->sessions[$sessionId]['remote_ip'],
            ]);
            $this->closeSession($sessionId);

            return;
        }

        $methods = substr($buffer, 2, $methodCount);
        if (!str_contains($methods, "\x00")) {
            @fwrite($this->sessions[$sessionId]['stream'], "\x05\xff");
            $this->closeSession($sessionId);

            return;
        }

        @fwrite($this->sessions[$sessionId]['stream'], "\x05\x00");
        $this->sessions[$sessionId]['buffer'] = substr($buffer, 2 + $methodCount);
        $this->sessions[$sessionId]['stage'] = 'request';
    }

    private function handleRequest(int $sessionId): void
    {
        $buffer = $this->sessions[$sessionId]['buffer'];
        if (strlen($buffer) < 4) {
            return;
        }

        $version = ord($buffer[0]);
        $command = ord($buffer[1]);
        $reserved = ord($buffer[2]);
        $type = ord($buffer[3]);

        if ($version !== 0x05 || $reserved !== 0x00) {
            $this->replyAndClose($sessionId, 0x01);

            return;
        }

        if ($command !== 0x01) {
            $this->replyAndClose($sessionId, 0x07);

            return;
        }

        try {
            $parsed = $this->parseAddress($buffer, $type);
        } catch (\Throwable) {
            $this->replyAndClose($sessionId, 0x08);

            return;
        }

        if ($parsed === null) {
            return;
        }

        [$host, $port, $consumed] = $parsed;
        $initialData = substr($buffer, $consumed);
        $stream = $this->sessions[$sessionId]['stream'];
        $this->sessions[$sessionId]['buffer'] = '';
        $this->sessions[$sessionId]['stage'] = 'connecting';
        $this->loop->removeReadStream($stream);

        $this->tuicClient->queueTcpRelay(
            local: $stream,
            host: $host,
            port: $port,
            initialData: $initialData,
            onConnected: function () use ($sessionId, $stream): void {
                @fwrite($stream, pack('C8', 0x05, 0x00, 0x00, 0x01, 0, 0, 0, 0) . pack('n', 0));
                unset($this->sessions[$sessionId]);
            },
            onError: function (\Throwable $throwable) use ($sessionId): void {
                if (isset($this->sessions[$sessionId])) {
                    $this->replyAndClose($sessionId, 0x01);
                    return;
                }

                $this->logger->warning('SOCKS relay failed after the handshake completed.', [
                    'message' => $throwable->getMessage(),
                ]);
            },
        );
    }

    /**
     * @return array{0: string, 1: int, 2: int}|null
     */
    private function parseAddress(string $buffer, int $addressType): ?array
    {
        return match ($addressType) {
            0x01 => $this->parseIpv4($buffer),
            0x03 => $this->parseDomain($buffer),
            0x04 => $this->parseIpv6($buffer),
            default => throw new \RuntimeException('Unsupported SOCKS5 address type.'),
        };
    }

    /**
     * @return array{0: string, 1: int, 2: int}|null
     */
    private function parseIpv4(string $buffer): ?array
    {
        if (strlen($buffer) < 10) {
            return null;
        }

        $host = inet_ntop(substr($buffer, 4, 4)) ?: '0.0.0.0';
        $port = unpack('nport', substr($buffer, 8, 2))['port'];

        return [$host, $port, 10];
    }

    /**
     * @return array{0: string, 1: int, 2: int}|null
     */
    private function parseDomain(string $buffer): ?array
    {
        if (strlen($buffer) < 5) {
            return null;
        }

        $length = ord($buffer[4]);
        if (strlen($buffer) < 5 + $length + 2) {
            return null;
        }

        $host = substr($buffer, 5, $length);
        $port = unpack('nport', substr($buffer, 5 + $length, 2))['port'];

        return [$host, $port, 5 + $length + 2];
    }

    /**
     * @return array{0: string, 1: int, 2: int}|null
     */
    private function parseIpv6(string $buffer): ?array
    {
        if (strlen($buffer) < 22) {
            return null;
        }

        $host = inet_ntop(substr($buffer, 4, 16)) ?: '::';
        $port = unpack('nport', substr($buffer, 20, 2))['port'];

        return [$host, $port, 22];
    }

    private function replyAndClose(int $sessionId, int $reply): void
    {
        if (!isset($this->sessions[$sessionId])) {
            return;
        }

        @fwrite(
            $this->sessions[$sessionId]['stream'],
            pack('C8', 0x05, $reply, 0x00, 0x01, 0, 0, 0, 0) . pack('n', 0),
        );

        $this->closeSession($sessionId);
    }

    private function closeSession(int $sessionId): void
    {
        if (!isset($this->sessions[$sessionId])) {
            return;
        }

        $stream = $this->sessions[$sessionId]['stream'];
        $this->loop->removeReadStream($stream);
        $this->loop->removeWriteStream($stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        unset($this->sessions[$sessionId]);
        $this->stats->recordConnectionClosed();
    }

    private function closeExpiredSessions(): void
    {
        $now = microtime(true);
        foreach (array_keys($this->sessions) as $sessionId) {
            if (($this->sessions[$sessionId]['expires_at'] ?? 0.0) > $now) {
                continue;
            }

            $this->stats->recordHandshakeTimeout();
            $this->logger->warning('Closed local client because the SOCKS handshake timed out.', [
                'remote_ip' => $this->sessions[$sessionId]['remote_ip'],
            ]);
            $this->replyAndClose($sessionId, 0x01);
        }
    }

    private function extractRemoteIp(?string $remoteAddress): ?string
    {
        if ($remoteAddress === null || $remoteAddress === '') {
            return null;
        }

        if (preg_match('/^\[(.+)\]:(\d+)$/', $remoteAddress, $matches) === 1) {
            return $matches[1];
        }

        $position = strrpos($remoteAddress, ':');
        if ($position === false) {
            return $remoteAddress;
        }

        return substr($remoteAddress, 0, $position);
    }
}
