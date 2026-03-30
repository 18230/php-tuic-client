<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', [
    'url:',
    'proxy::',
    'proxy-type::',
    'method::',
    'data::',
    'header::',
    'timeout::',
]);

if (!isset($options['url'])) {
    fwrite(STDERR, <<<TXT
Usage:
  php examples/manual_proxy_request.php --url=https://example.com/ [options]

Options:
  --proxy=ADDR              Proxy address, default 127.0.0.1:1080.
  --proxy-type=TYPE         socks5 or socks5h, default socks5h.
  --method=METHOD           HTTP method, default GET.
  --data=STRING             Request body.
  --header='Name: value'    Repeatable header.
  --timeout=SECONDS         Request timeout, default 30.

Examples:
  php examples/manual_proxy_request.php --url=https://api.ipify.org?format=json
  php examples/manual_proxy_request.php --proxy-type=socks5h --proxy=127.0.0.1:1080 --url=https://example.com/

TXT);
    exit(1);
}

if (!function_exists('curl_init')) {
    fwrite(STDERR, "The PHP cURL extension is required.\n");
    exit(1);
}

$proxyType = strtolower((string) ($options['proxy-type'] ?? 'socks5h'));
$proxyAddress = (string) ($options['proxy'] ?? '127.0.0.1:1080');
$method = strtoupper((string) ($options['method'] ?? (isset($options['data']) ? 'POST' : 'GET')));
$timeout = (int) ($options['timeout'] ?? 30);
$headers = [];

foreach ((array) ($options['header'] ?? []) as $header) {
    $headers[] = (string) $header;
}

$curlProxyType = match ($proxyType) {
    'socks5', 'socks5h' => CURLPROXY_SOCKS5_HOSTNAME,
    default => throw new RuntimeException("Unsupported proxy type: {$proxyType}. Use socks5 or socks5h."),
};

$ch = curl_init((string) $options['url']);
if ($ch === false) {
    throw new RuntimeException('Failed to initialize cURL.');
}

$curlOptions = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_PROXY => $proxyAddress,
    CURLOPT_PROXYTYPE => $curlProxyType,
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_CONNECTTIMEOUT => min($timeout, 10),
    CURLOPT_HTTPHEADER => $headers,
];

if ($method === 'HEAD') {
    $curlOptions[CURLOPT_NOBODY] = true;
} elseif (isset($options['data'])) {
    $curlOptions[CURLOPT_POSTFIELDS] = (string) $options['data'];
}

curl_setopt_array($ch, $curlOptions);

$raw = curl_exec($ch);
if ($raw === false) {
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
    fwrite(STDERR, "Request failed ({$errno}): {$error}\n");
    exit(1);
}

$statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$responseHeaders = substr($raw, 0, $headerSize);
$responseBody = substr($raw, $headerSize);
curl_close($ch);

fwrite(STDOUT, "Proxy: {$proxyType}://{$proxyAddress}\n");
fwrite(STDOUT, "Status: {$statusCode}\n");
fwrite(STDOUT, "Headers:\n{$responseHeaders}\n");
fwrite(STDOUT, "Body Preview:\n" . substr($responseBody, 0, 500) . "\n");
