<?php declare(strict_types=1);

use PhpTuic\Http\TuicCurlClient;

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', [
    'url:',
    'proxy::',
    'method::',
    'data::',
    'header::',
    'follow-redirects',
    'timeout::',
]);

if (!isset($options['url'])) {
    fwrite(STDERR, <<<TXT
使用方式:
  php examples/http_proxy_example.php --url=https://example.com/ [options]

可选参数:
  --proxy=ADDR              SOCKS5 代理地址，默认 127.0.0.1:1080
  --method=METHOD           请求方法，默认 GET；如果传了 --data，默认 POST
  --data=STRING             请求体
  --header='Name: value'    可重复传多个请求头
  --follow-redirects        是否跟随跳转
  --timeout=SECONDS         超时时间，默认 30 秒

示例:
  php examples/http_proxy_example.php --url=https://api.ipify.org?format=json
  php examples/http_proxy_example.php --url=https://example.com/
  php examples/http_proxy_example.php --url=https://postman-echo.com/post --method=POST --data='foo=bar'

TXT);
    exit(1);
}

// 文件名为兼容旧示例保留，但当前运行时只推荐本地 SOCKS5 代理。
$proxyAddress = (string) ($options['proxy'] ?? '127.0.0.1:1080');
$method = strtoupper((string) ($options['method'] ?? (isset($options['data']) ? 'POST' : 'GET')));
$headers = array_map(static fn (mixed $header): string => (string) $header, (array) ($options['header'] ?? []));

$client = new TuicCurlClient($proxyAddress, CURLPROXY_SOCKS5_HOSTNAME);

$requestOptions = [
    'headers' => $headers,
    'follow_redirects' => array_key_exists('follow-redirects', $options),
    'timeout' => (int) ($options['timeout'] ?? 30),
];

if (isset($options['data'])) {
    $requestOptions['body'] = (string) $options['data'];
}

try {
    $response = $client->request($method, (string) $options['url'], $requestOptions);

    echo "Proxy: socks5h://{$proxyAddress}\n";
    echo "Status: {$response->statusCode}\n";
    echo "Headers:\n";
    foreach ($response->headers as $name => $values) {
        foreach ($values as $value) {
            echo "{$name}: {$value}\n";
        }
    }

    echo "\nBody:\n";
    echo $response->body, "\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
