<?php declare(strict_types=1);

namespace PhpTuic\Http;

use PhpTuic\Tuic\TuicClient;

// 直接在 TUIC 连接里发送普通 HTTP/1.1 请求。
// 当前只处理 http，不负责目标站点的 https TLS。
final class TuicHttpClient
{
    public function __construct(
        private readonly TuicClient $tuicClient,
        private readonly string $defaultUserAgent = 'php-tuic-http/0.1',
    ) {
    }

    /**
     * @param array<string, string> $options
     */
    public function get(string $url, array $options = []): HttpResponse
    {
        return $this->request('GET', $url, $options);
    }

    /**
     * @param array<string, string> $options
     */
    public function post(string $url, string|array $body = '', array $options = []): HttpResponse
    {
        $options['body'] = $body;

        return $this->request('POST', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function put(string $url, string|array $body = '', array $options = []): HttpResponse
    {
        $options['body'] = $body;

        return $this->request('PUT', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function patch(string $url, string|array $body = '', array $options = []): HttpResponse
    {
        $options['body'] = $body;

        return $this->request('PATCH', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function delete(string $url, string|array $body = '', array $options = []): HttpResponse
    {
        $options['body'] = $body;

        return $this->request('DELETE', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function head(string $url, array $options = []): HttpResponse
    {
        return $this->request('HEAD', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function options(string $url, string|array $body = '', array $options = []): HttpResponse
    {
        $options['body'] = $body;

        return $this->request('OPTIONS', $url, $options);
    }

    /**
     * @param array{
      *   headers?: array<string, string>,
     *   body?: string|array,
     *   follow_redirects?: bool,
     *   max_redirects?: int,
     *   max_attempts?: int,
     *   http_version?: string,
     *   user_agent?: string
     * } $options
     */
    public function request(string $method, string $url, array $options = []): HttpResponse
    {
        $method = strtoupper(trim($method));
        if ($method === '') {
            throw new \RuntimeException('HTTP method must not be empty.');
        }

        // 这里做的是“请求级重试”，失败时会重建底层 TUIC 连接再试一次。
        $redirects = 0;
        $followRedirects = (bool) ($options['follow_redirects'] ?? false);
        $maxRedirects = (int) ($options['max_redirects'] ?? 5);
        $maxAttempts = max(1, (int) ($options['max_attempts'] ?? 3));

        while (true) {
            $response = null;
            $lastError = null;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $response = $this->requestOnce($method, $url, $options);
                    break;
                } catch (\Throwable $e) {
                    $lastError = $e;
                    $this->tuicClient->close();
                    if ($attempt < $maxAttempts) {
                        usleep($attempt * 100_000);
                    }
                }
            }

            if ($response === null) {
                throw $lastError ?? new \RuntimeException('HTTP request failed without an explicit error.');
            }

            if (
                !$followRedirects
                || $redirects >= $maxRedirects
                || !in_array($response->statusCode, [301, 302, 303, 307, 308], true)
            ) {
                return $response;
            }

            $location = $response->header('location');
            if ($location === null || $location === '') {
                return $response;
            }

            $url = $this->resolveRedirect($url, $location);
            if (in_array($response->statusCode, [301, 302, 303], true)) {
                $method = 'GET';
                unset($options['body']);
            }
            $redirects++;
        }
    }

    /**
     * @param array{
     *   headers?: array<string, string>,
     *   body?: string|array,
     *   http_version?: string,
     *   user_agent?: string
     * } $options
     */
    private function requestOnce(string $method, string $url, array $options): HttpResponse
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \RuntimeException("Invalid URL: {$url}");
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http') {
            throw new \RuntimeException("Only http:// URLs are supported by TuicHttpClient right now: {$url}");
        }

        $host = $parts['host'];
        $port = (int) ($parts['port'] ?? 80);
        $path = ($parts['path'] ?? '/')
            . (isset($parts['query']) ? '?' . $parts['query'] : '');

        // 把业务层 header 统一整理成内部使用的二维结构。
        $headers = [];
        foreach (($options['headers'] ?? []) as $name => $value) {
            $headers[strtolower($name)] = [$name, $value];
        }

        $body = $this->normalizeBody($options['body'] ?? '', $headers);
        $httpVersion = (string) ($options['http_version'] ?? '1.1');
        $headers['host'] ??= ['Host', $this->hostHeader($host, $port)];
        $headers['user-agent'] ??= ['User-Agent', (string) ($options['user_agent'] ?? $this->defaultUserAgent)];
        $headers['accept'] ??= ['Accept', '*/*'];
        $headers['accept-encoding'] ??= ['Accept-Encoding', 'identity'];
        $headers['connection'] ??= ['Connection', 'close'];
        if ($method === 'HEAD') {
            $headers['connection'] = ['Connection', 'close'];
        }

        if ($body !== '' && !isset($headers['content-length'])) {
            $headers['content-length'] = ['Content-Length', (string) strlen($body)];
        }

        // 这里是最原始的做法：自己拼 HTTP/1.1 请求报文，再走 TUIC 发出去。
        $request = "{$method} {$path} HTTP/{$httpVersion}\r\n";
        foreach ($headers as [$name, $value]) {
            $request .= "{$name}: {$value}\r\n";
        }
        $request .= "\r\n" . $body;

        $this->tuicClient->connect();
        $raw = $this->tuicClient->tcpRequest($host, $port, $request);

        return $this->parseResponse($raw);
    }

    private function normalizeBody(string|array $body, array &$headers): string
    {
        if (is_string($body)) {
            return $body;
        }

        $headers['content-type'] ??= ['Content-Type', 'application/x-www-form-urlencoded'];

        return http_build_query($body);
    }

    private function parseResponse(string $raw): HttpResponse
    {
        // 先按 HTTP 报文拆头和 body，再做基础解析。
        $separatorPos = strpos($raw, "\r\n\r\n");
        if ($separatorPos === false) {
            throw new \RuntimeException('Invalid HTTP response: missing header separator.');
        }

        $headerBlock = substr($raw, 0, $separatorPos);
        $body = substr($raw, $separatorPos + 4);
        $lines = explode("\r\n", $headerBlock);
        $statusLine = array_shift($lines);

        if ($statusLine === null || !preg_match('#^HTTP/([0-9.]+)\s+(\d{3})(?:\s+(.*))?$#', $statusLine, $matches)) {
            throw new \RuntimeException('Invalid HTTP status line: ' . ($statusLine ?? '<empty>'));
        }

        $headers = [];
        foreach ($lines as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))][] = ltrim($value);
        }

        if (($headers['transfer-encoding'][0] ?? null) !== null
            && str_contains(strtolower($headers['transfer-encoding'][0]), 'chunked')) {
            $body = $this->decodeChunked($body);
        }

        return new HttpResponse(
            protocolVersion: $matches[1],
            statusCode: (int) $matches[2],
            reasonPhrase: $matches[3] ?? '',
            headers: $headers,
            body: $body,
            raw: $raw,
        );
    }

    private function decodeChunked(string $body): string
    {
        // 只做最基础的 chunked 解码，够常见接口返回使用。
        $decoded = '';
        $offset = 0;
        $length = strlen($body);

        while ($offset < $length) {
            $lineEnd = strpos($body, "\r\n", $offset);
            if ($lineEnd === false) {
                throw new \RuntimeException('Invalid chunked response: missing chunk size terminator.');
            }

            $sizeLine = substr($body, $offset, $lineEnd - $offset);
            $sizeHex = trim(strtok($sizeLine, ';'));
            if ($sizeHex === '' || !ctype_xdigit($sizeHex)) {
                throw new \RuntimeException('Invalid chunked response: bad chunk size.');
            }

            $chunkSize = hexdec($sizeHex);
            $offset = $lineEnd + 2;

            if ($chunkSize === 0) {
                break;
            }

            $chunk = substr($body, $offset, $chunkSize);
            if (strlen($chunk) !== $chunkSize) {
                throw new \RuntimeException('Invalid chunked response: truncated chunk.');
            }

            $decoded .= $chunk;
            $offset += $chunkSize + 2;
        }

        return $decoded;
    }

    private function hostHeader(string $host, int $port): string
    {
        return $port === 80 ? $host : "{$host}:{$port}";
    }

    private function resolveRedirect(string $baseUrl, string $location): string
    {
        if (str_starts_with($location, 'http://')) {
            return $location;
        }

        if (str_starts_with($location, '//')) {
            return 'http:' . $location;
        }

        $base = parse_url($baseUrl);
        if ($base === false || !isset($base['host'])) {
            throw new \RuntimeException("Invalid base URL for redirect: {$baseUrl}");
        }

        $host = $base['host'];
        $port = isset($base['port']) ? ':' . $base['port'] : '';

        if (str_starts_with($location, '/')) {
            return "http://{$host}{$port}{$location}";
        }

        $basePath = $base['path'] ?? '/';
        $dir = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
        $dir = $dir === '' ? '' : $dir;

        return "http://{$host}{$port}{$dir}/{$location}";
    }
}
