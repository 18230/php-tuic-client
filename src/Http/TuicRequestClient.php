<?php declare(strict_types=1);

namespace PhpTuic\Http;

use PhpTuic\Config\NodeLoader;
use PhpTuic\Proxy\ManagedTuicProxy;
use PhpTuic\Tuic\TuicClient;

// 面向业务代码的统一入口。
// http 直接走原生 TUIC，请求 https 时再借助本地代理 + cURL。
final class TuicRequestClient
{
    private readonly ManagedTuicProxy $proxy;
    private readonly TuicClient $tuicClient;
    private readonly TuicHttpClient $directHttpClient;
    private ?TuicCurlClient $proxiedHttpClient = null;

    public function __construct(
        string $configPath,
        ?string $nodeName = null,
        ?int $httpPort = null,
        ?int $socksPort = null,
        private readonly string $proxyMode = 'http',
        ?string $phpBinary = null,
        float $startupTimeout = 10.0,
    ) {
        $this->proxy = new ManagedTuicProxy(
            configPath: $configPath,
            nodeName: $nodeName,
            httpPort: $httpPort,
            socksPort: $socksPort,
            startupTimeout: $startupTimeout,
            phpBinary: $phpBinary,
        );

        $node = NodeLoader::fromFile($configPath, $nodeName);
        $this->tuicClient = new TuicClient($node);
        $this->directHttpClient = new TuicHttpClient($this->tuicClient);
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
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): HttpResponse
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        // 纯 http 可以直接在 TUIC 隧道里拼 HTTP 报文，链路更短。
        if ($scheme === 'http') {
            return $this->directHttpClient->request($method, $url, $this->normalizeDirectOptions($options));
        }

        if ($scheme !== 'https') {
            throw new \RuntimeException("Unsupported URL scheme: {$url}");
        }

        // https 交给本地代理层处理，这样可以复用 cURL 的 TLS 能力。
        return $this->client()->request($method, $url, $options);
    }

    public function start(): void
    {
        $this->proxy->start();
    }

    public function stop(): void
    {
        $this->proxy->stop();
        $this->tuicClient->close();
        $this->proxiedHttpClient = null;
    }

    public function getHttpProxyUrl(): string
    {
        $this->start();

        return $this->proxy->getHttpProxyUrl();
    }

    public function getSocksProxyUrl(): string
    {
        $this->start();

        return $this->proxy->getSocksProxyUrl();
    }

    public function __destruct()
    {
        $this->stop();
    }

    private function client(): TuicCurlClient
    {
        $this->start();

        if ($this->proxiedHttpClient !== null) {
            return $this->proxiedHttpClient;
        }

        // 这里决定本地请求到底走 HTTP 代理还是 SOCKS5 代理。
        $proxyType = match (strtolower($this->proxyMode)) {
            'http' => CURLPROXY_HTTP,
            'socks5', 'socks5h' => CURLPROXY_SOCKS5_HOSTNAME,
            default => throw new \RuntimeException("Unsupported proxy mode: {$this->proxyMode}"),
        };

        $proxyAddress = $proxyType === CURLPROXY_HTTP
            ? $this->proxy->getHttpProxyAddress()
            : $this->proxy->getSocksProxyAddress();

        return $this->proxiedHttpClient = new TuicCurlClient($proxyAddress, $proxyType);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function normalizeDirectOptions(array $options): array
    {
        if (!isset($options['headers']) || !is_array($options['headers'])) {
            return $options;
        }

        // 直连 http 的实现要求 header 是 name => value 结构，这里顺手做一次归一化。
        $headers = [];
        foreach ($options['headers'] as $name => $value) {
            if (is_int($name)) {
                $line = (string) $value;
                if (!str_contains($line, ':')) {
                    continue;
                }

                [$parsedName, $parsedValue] = explode(':', $line, 2);
                $headers[trim($parsedName)] = ltrim($parsedValue);
                continue;
            }

            $headers[(string) $name] = (string) $value;
        }

        $options['headers'] = $headers;

        return $options;
    }
}
