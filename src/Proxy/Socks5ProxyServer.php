<?php declare(strict_types=1);

namespace PhpTuic\Proxy;

use Amp\ByteStream\BufferedReader;
use Amp\Socket\ServerSocket;
use Amp\Socket\Socket;
use PhpTuic\Tuic\TuicClient;
use function Amp\async;
use function Amp\Socket\listen;

final class Socks5ProxyServer
{
    private ?ServerSocket $server = null;

    public function __construct(
        private readonly TuicClient $tuicClient,
        private readonly string $listenAddress,
    ) {
    }

    public function start(): void
    {
        if ($this->server !== null) {
            return;
        }

        $this->server = listen($this->listenAddress);

        async(function (): void {
            try {
                while (($client = $this->server?->accept()) !== null) {
                    async(fn () => $this->handleClient($client))->ignore();
                }
            } catch (\Throwable $e) {
                if ($this->server !== null) {
                    fwrite(STDERR, "[socks5-proxy] accept loop stopped: {$e->getMessage()}\n");
                }
            }
        })->ignore();
    }

    public function stop(): void
    {
        $this->server?->close();
        $this->server = null;
    }

    public function getAddress(): string
    {
        return (string) ($this->server?->getAddress() ?? $this->listenAddress);
    }

    private function handleClient(Socket $client): void
    {
        $reader = new BufferedReader($client);

        try {
            $greeting = $reader->readLength(2);
            [$version, $methodCount] = array_values(unpack('Cversion/Cmethod_count', $greeting));
            if ($version !== 0x05) {
                throw new \RuntimeException('Unsupported SOCKS version.');
            }

            $methods = $reader->readLength($methodCount);
            if (!str_contains($methods, "\x00")) {
                $client->write("\x05\xff");
                $client->close();
                return;
            }

            $client->write("\x05\x00");

            $requestHead = $reader->readLength(4);
            [$version, $command, $reserved, $addressType] = array_values(unpack('Cversion/Ccommand/Creserved/Caddress_type', $requestHead));
            if ($version !== 0x05 || $reserved !== 0x00) {
                throw new \RuntimeException('Invalid SOCKS5 request header.');
            }

            if ($command !== 0x01) {
                $this->writeReply($client, 0x07);
                $client->close();
                return;
            }

            [$host, $port] = $this->readAddress($reader, $addressType);
            $remote = $this->tuicClient->openTcpStream($host, $port);
            $this->writeReply($client, 0x00);
            StreamBridge::tunnel($client, $remote, $reader->drain());
        } catch (\Throwable $e) {
            fwrite(STDERR, "[socks5-proxy] {$e->getMessage()}\n");
            try {
                $this->writeReply($client, 0x01);
            } catch (\Throwable) {
            } finally {
                $client->close();
            }
        }
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function readAddress(BufferedReader $reader, int $addressType): array
    {
        return match ($addressType) {
            0x01 => [inet_ntop($reader->readLength(4)) ?: '0.0.0.0', $this->readPort($reader)],
            0x03 => [$reader->readLength(ord($reader->readLength(1))), $this->readPort($reader)],
            0x04 => [inet_ntop($reader->readLength(16)) ?: '::', $this->readPort($reader)],
            default => throw new \RuntimeException('Unsupported SOCKS5 address type.'),
        };
    }

    private function readPort(BufferedReader $reader): int
    {
        return unpack('nport', $reader->readLength(2))['port'];
    }

    private function writeReply(Socket $client, int $reply): void
    {
        $client->write(pack('C8', 0x05, $reply, 0x00, 0x01, 0, 0, 0, 0) . pack('n', 0));
    }
}
