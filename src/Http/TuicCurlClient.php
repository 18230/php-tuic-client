<?php declare(strict_types=1);

namespace PhpTuic\Http;

// 借助本地 HTTP/SOCKS5 代理来发请求。
// 这层主要给 https 和通用 cURL 能力复用。
final class TuicCurlClient
{
    public function __construct(
        private readonly string $proxyAddress,
        private readonly int $proxyType = CURLPROXY_HTTP,
        private readonly string $defaultUserAgent = 'php-tuic-curl/0.1',
    ) {
        if (!\function_exists('curl_init')) {
            throw new \RuntimeException('The PHP cURL extension is required for TuicCurlClient.');
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    public function get(string $url, array $options = []): HttpResponse
    {
        return $this->request('GET', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
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
     *   headers?: array<string, string>|list<string>,
     *   body?: string|array,
     *   follow_redirects?: bool,
     *   max_redirects?: int,
     *   timeout?: int|float,
     *   connect_timeout?: int|float,
     *   user_agent?: string
     * } $options
     */
    public function request(string $method, string $url, array $options = []): HttpResponse
    {
        $method = strtoupper(trim($method));
        if ($method === '') {
            throw new \RuntimeException('HTTP method must not be empty.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL.');
        }

        $responseHeaders = [];
        $protocolVersion = '1.1';
        $reasonPhrase = '';

        $body = $this->normalizeBody($options['body'] ?? '', $options);
        $requestHeaders = $this->normalizeHeaders($options['headers'] ?? [], (string) ($options['user_agent'] ?? $this->defaultUserAgent));

        // 这里本质上就是一层“预配置好的 cURL”，把代理地址和常用选项统一塞进去。
        $curlOptions = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_PROXY => $this->proxyAddress,
            CURLOPT_PROXYTYPE => $this->proxyType,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => (bool) ($options['follow_redirects'] ?? false),
            CURLOPT_MAXREDIRS => max(0, (int) ($options['max_redirects'] ?? 5)),
            CURLOPT_TIMEOUT => (int) ceil((float) ($options['timeout'] ?? 30)),
            CURLOPT_CONNECTTIMEOUT => (int) ceil((float) ($options['connect_timeout'] ?? 10)),
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders, &$protocolVersion, &$reasonPhrase): int {
                $trimmed = trim($line);

                if ($trimmed === '') {
                    return strlen($line);
                }

                if (preg_match('#^HTTP/([0-9.]+)\s+(\d{3})(?:\s+(.*))?$#', $trimmed, $matches)) {
                    $responseHeaders = [];
                    $protocolVersion = $matches[1];
                    $reasonPhrase = $matches[3] ?? '';

                    return strlen($line);
                }

                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))][] = ltrim(rtrim($value, "\r\n"));
                }

                return strlen($line);
            },
        ];

        if (\defined('CURLOPT_SUPPRESS_CONNECT_HEADERS')) {
            $curlOptions[CURLOPT_SUPPRESS_CONNECT_HEADERS] = true;
        }

        if ($method === 'HEAD') {
            $curlOptions[CURLOPT_NOBODY] = true;
        } elseif ($body !== '') {
            $curlOptions[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $curlOptions);
        $bodyContent = curl_exec($ch);
        if ($bodyContent === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);

            throw new \RuntimeException("cURL request failed ({$errno}): {$error}");
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $raw = $this->buildRawResponse($protocolVersion, $statusCode, $reasonPhrase, $responseHeaders, $bodyContent);

        return new HttpResponse(
            protocolVersion: $protocolVersion,
            statusCode: $statusCode,
            reasonPhrase: $reasonPhrase,
            headers: $responseHeaders,
            body: (string) $bodyContent,
            raw: $raw,
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function normalizeBody(string|array $body, array &$options): string
    {
        if (\is_string($body)) {
            return $body;
        }

        // 传数组时默认按普通表单编码，和常见 PHP / Nginx 表单接口一致。
        $headers = $options['headers'] ?? [];
        if (!$this->hasHeader($headers, 'Content-Type')) {
            if (\is_array($headers)) {
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
                $options['headers'] = $headers;
            }
        }

        return http_build_query($body);
    }

    /**
     * @param array<string, string>|list<string> $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers, string $userAgent): array
    {
        $normalized = [];
        $hasUserAgent = false;
        $hasAccept = false;
        $hasConnection = false;

        // 同时兼容两种写法：
        // 1. ['Content-Type' => 'application/json']
        // 2. ['Content-Type: application/json']
        foreach ($headers as $name => $value) {
            if (\is_int($name)) {
                $normalized[] = $value;
                $headerName = strtolower(trim((string) strtok($value, ':')));
            } else {
                $normalized[] = "{$name}: {$value}";
                $headerName = strtolower($name);
            }

            $hasUserAgent = $hasUserAgent || $headerName === 'user-agent';
            $hasAccept = $hasAccept || $headerName === 'accept';
            $hasConnection = $hasConnection || $headerName === 'connection';
        }

        if (!$hasUserAgent) {
            $normalized[] = 'User-Agent: ' . $userAgent;
        }

        if (!$hasAccept) {
            $normalized[] = 'Accept: */*';
        }

        if (!$hasConnection) {
            $normalized[] = 'Connection: close';
        }

        return $normalized;
    }

    /**
     * @param array<string, string>|list<string> $headers
     */
    private function hasHeader(array $headers, string $wanted): bool
    {
        $wanted = strtolower($wanted);

        foreach ($headers as $name => $value) {
            if (\is_int($name)) {
                $headerName = strtolower(trim((string) strtok($value, ':')));
            } else {
                $headerName = strtolower($name);
            }

            if ($headerName === $wanted) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function buildRawResponse(
        string $protocolVersion,
        int $statusCode,
        string $reasonPhrase,
        array $headers,
        string $body,
    ): string {
        // 返回 raw 是为了让 CLI 和调试场景能直接看到完整响应。
        $raw = "HTTP/{$protocolVersion} {$statusCode}";
        if ($reasonPhrase !== '') {
            $raw .= " {$reasonPhrase}";
        }
        $raw .= "\r\n";

        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $raw .= $this->formatHeaderName($name) . ': ' . $value . "\r\n";
            }
        }

        return $raw . "\r\n" . $body;
    }

    private function formatHeaderName(string $name): string
    {
        return implode('-', array_map(static fn (string $part): string => ucfirst($part), explode('-', $name)));
    }
}
