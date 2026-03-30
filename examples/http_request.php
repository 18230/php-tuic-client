<?php declare(strict_types=1);

use PhpTuic\Http\TuicRequestClient;

require __DIR__ . '/../vendor/autoload.php';

// 文件名沿用旧示例，但当前推荐路径是通过本地 SOCKS5 运行时完成请求。
$client = new TuicRequestClient(__DIR__ . '/node.user.yaml');

try {
    $response = $client->get('https://postman-echo.com/get?source=http_request_example');

    echo "Proxy: {$client->getSocksProxyUrl()}\n";
    echo "Status: {$response->statusCode}\n";
    echo "Body preview:\n";
    echo substr($response->body, 0, 200), "\n";
} finally {
    $client->stop();
}
