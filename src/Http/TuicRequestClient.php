<?php declare(strict_types=1);

namespace PhpTuic\Http;

use PhpTuic\Proxy\ManagedTuicProxy;

// 面向业务代码的统一入口。
// 当前统一复用本地 SOCKS5 + cURL 这条已验证的链路，避免依赖未完成的直连 helper。
final class TuicRequestClient
{
    private readonly ManagedTuicProxy $proxy;
    private ?TuicCurlClient $proxiedHttpClient = null;

    public function __construct(
        string $configPath,
        ?string $nodeName = null,
        ?int $httpPort = null,
        ?int $socksPort = null,
        private readonly string $proxyMode = 'socks5h',
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

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \RuntimeException("Unsupported URL scheme: {$url}");
        }

        return $this->client()->request($method, $url, $options);
    }

    public function start(): void
    {
        $this->proxy->start();
    }

    public function stop(): void
    {
        $this->proxy->stop();
        $this->proxiedHttpClient = null;
    }

    public function getHttpProxyUrl(): string
    {
        throw new \RuntimeException('HTTP proxy mode is not available in the current quiche runtime. Use getSocksProxyUrl().');
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

        $proxyType = match (strtolower($this->proxyMode)) {
            'socks5', 'socks5h' => CURLPROXY_SOCKS5_HOSTNAME,
            'http' => CURLPROXY_SOCKS5_HOSTNAME,
            default => throw new \RuntimeException("Unsupported proxy mode: {$this->proxyMode}"),
        };

        $proxyAddress = $this->proxy->getSocksProxyAddress();

        return $this->proxiedHttpClient = new TuicCurlClient($proxyAddress, $proxyType);
    }
}
