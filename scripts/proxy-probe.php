<?php declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/proxy-probe.php <url> <proxy-host:port>\n");
    exit(1);
}

$url = $argv[1];
$proxy = $argv[2];

$ch = curl_init($url);
if ($ch === false) {
    fwrite(STDERR, "Failed to initialize cURL.\n");
    exit(1);
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_PROXY => $proxy,
    CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_USERAGENT => 'php-tuic-client-probe/1.0',
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($response === false) {
    fwrite(STDERR, "Probe failed: {$error}\n");
    exit(1);
}

if ($httpCode < 200 || $httpCode >= 300) {
    fwrite(STDERR, "Probe returned HTTP {$httpCode}: {$response}\n");
    exit(1);
}

echo $response;
