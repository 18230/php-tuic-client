<?php declare(strict_types=1);

namespace PhpTuic\Proxy;

use Amp\ByteStream\BufferedReader;
use Amp\Socket\ServerSocket;
use Amp\Socket\Socket;
use PhpTuic\Http\TuicHttpClient;
use PhpTuic\Tuic\TuicClient;
use function Amp\async;
use function Amp\Socket\listen;

final class HttpProxyServer
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
                    fwrite(STDERR, "[http-proxy] accept loop stopped: {$e->getMessage()}\n");
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
            $headerBlock = $reader->readUntil("\r\n\r\n", limit: 65536);
            $lines = explode("\r\n", $headerBlock);
            $requestLine = array_shift($lines);

            if ($requestLine === null || !preg_match('#^([A-Z]+)\s+(\S+)\s+HTTP/(1\.[01])$#', $requestLine, $matches)) {
                $this->writeError($client, 400, 'Bad Request', 'Invalid proxy request line.');
                return;
            }

            $method = $matches[1];
            $target = $matches[2];
            $version = $matches[3];
            $headers = $this->parseHeaders($lines);

            if ($method === 'CONNECT') {
                $this->handleConnect($client, $reader, $target);
                return;
            }

            [$host, $port, $path] = $this->resolveForwardRequest($target, $headers);
            $body = $this->readRequestBody($reader, $headers);
            $httpClient = new TuicHttpClient($this->tuicClient);
            $response = $httpClient->request(
                $method,
                $this->buildForwardUrl($host, $port, $path),
                [
                    'headers' => $this->buildForwardHeaders($host, $port, $headers),
                    'body' => $body,
                    'follow_redirects' => false,
                ],
            );

            $client->write($response->raw);
            $client->end();
        } catch (\Throwable $e) {
            fwrite(STDERR, "[http-proxy] {$e->getMessage()}\n");
            $this->writeError($client, 502, 'Bad Gateway', $e->getMessage());
        }
    }

    /**
     * @param list<string> $lines
     * @return list<array{0: string, 1: string}>
     */
    private function parseHeaders(array $lines): array
    {
        $headers = [];
        foreach ($lines as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[] = [trim($name), ltrim($value)];
        }

        return $headers;
    }

    /**
     * @param list<array{0: string, 1: string}> $headers
     * @return array{0: string, 1: int, 2: string}
     */
    private function resolveForwardRequest(string $target, array $headers): array
    {
        if (str_starts_with($target, 'http://')) {
            $parts = parse_url($target);
            if ($parts === false || !isset($parts['host'])) {
                throw new \RuntimeException("Invalid absolute-form URL: {$target}");
            }

            $host = $parts['host'];
            $port = (int) ($parts['port'] ?? 80);
            $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

            return [$host, $port, $path];
        }

        if (!str_starts_with($target, '/')) {
            throw new \RuntimeException("Unsupported request target: {$target}");
        }

        $hostHeader = $this->findHeader($headers, 'host');
        if ($hostHeader === null) {
            throw new \RuntimeException('Origin-form request is missing Host header.');
        }

        [$host, $port] = $this->parseHostPort($hostHeader, 80);

        return [$host, $port, $target];
    }

    private function handleConnect(Socket $client, BufferedReader $reader, string $target): void
    {
        try {
            [$host, $port] = $this->parseHostPort($target, 443);
            $remote = $this->tuicClient->openTcpStream($host, $port);
            $client->write("HTTP/1.1 200 Connection Established\r\nConnection: close\r\n\r\n");
            StreamBridge::tunnel($client, $remote, $reader->drain());
        } catch (\Throwable $e) {
            fwrite(STDERR, "[http-connect] {$e->getMessage()}\n");
            $this->writeError($client, 502, 'Bad Gateway', $e->getMessage());
        }
    }

    /**
     * @param list<array{0: string, 1: string}> $headers
     */
    private function findHeader(array $headers, string $wanted): ?string
    {
        foreach ($headers as [$name, $value]) {
            if (strtolower($name) === strtolower($wanted)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function parseHostPort(string $value, int $defaultPort): array
    {
        $value = trim($value);

        if (preg_match('/^\[(.+)\]:(\d+)$/', $value, $matches)) {
            return [$matches[1], (int) $matches[2]];
        }

        if (preg_match('/^\[(.+)\]$/', $value, $matches)) {
            return [$matches[1], $defaultPort];
        }

        $parts = explode(':', $value);
        if (count($parts) === 2 && $parts[0] !== '' && ctype_digit($parts[1])) {
            return [$parts[0], (int) $parts[1]];
        }

        return [$value, $defaultPort];
    }

    private function formatHostHeader(string $host, int $port): string
    {
        $formattedHost = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? "[{$host}]" : $host;

        return $port === 80 ? $formattedHost : "{$formattedHost}:{$port}";
    }

    /**
     * @param list<array{0: string, 1: string}> $headers
     */
    private function buildForwardHeaders(string $host, int $port, array $headers): array
    {
        $forward = [];
        $hostWritten = false;

        foreach ($headers as [$name, $value]) {
            $lower = strtolower($name);

            if (in_array($lower, ['proxy-connection', 'proxy-authorization', 'connection', 'keep-alive'], true)) {
                continue;
            }

            if ($lower === 'host') {
                $value = $this->formatHostHeader($host, $port);
                $hostWritten = true;
            }

            $forward[$name] = $value;
        }

        if (!$hostWritten) {
            $forward['Host'] = $this->formatHostHeader($host, $port);
        }

        $forward['Connection'] = 'close';

        return $forward;
    }

    /**
     * @param list<array{0: string, 1: string}> $headers
     */
    private function readRequestBody(BufferedReader $reader, array $headers): string
    {
        $length = null;

        foreach ($headers as [$name, $value]) {
            $lower = strtolower($name);
            if ($lower === 'content-length') {
                $length = (int) trim($value);
                break;
            }

            if ($lower === 'transfer-encoding') {
                throw new \RuntimeException('Chunked request bodies are not supported by the local HTTP proxy yet.');
            }
        }

        if ($length === null || $length <= 0) {
            return '';
        }

        $body = $reader->drain();
        $remaining = $length - strlen($body);
        if ($remaining > 0) {
            $body .= $reader->readLength($remaining);
        }

        return substr($body, 0, $length);
    }

    private function buildForwardUrl(string $host, int $port, string $path): string
    {
        return 'http://' . $this->formatHostHeader($host, $port) . $path;
    }

    private function writeError(Socket $client, int $status, string $reason, string $message): void
    {
        $body = $message . "\n";
        try {
            $client->write(
                "HTTP/1.1 {$status} {$reason}\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . 'Content-Length: ' . strlen($body) . "\r\n"
                . "Connection: close\r\n\r\n"
                . $body
            );
        } catch (\Throwable) {
        } finally {
            $client->close();
        }
    }
}
