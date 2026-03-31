#!/usr/bin/env php
<?php declare(strict_types=1);

use PhpTuic\Config\NodeInputResolver;
use PhpTuic\Http\TuicRequestClient;

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', [
    'config::',
    'node::',
    'node-name::',
    'url:',
    'method::',
    'proxy-mode::',
    'data::',
    'header::',
    'follow-redirects',
]);

if ((!isset($options['config']) && !isset($options['node'])) || !isset($options['url'])) {
    fwrite(STDERR, <<<TXT
Usage:
  php bin/tuic-request.php --config=/path/to/node.yaml --url=https://example.com [options]
  php bin/tuic-request.php --node="{ ... }" --url=https://example.com [options]

Options:
  --node=YAML_OR_JSON        Inline TUIC node config in YAML or JSON.
  --node-name=NAME           Optional node name when config contains multiple proxies.
  --method=METHOD            HTTP method, default GET.
  --proxy-mode=MODE          socks5 or socks5h, default socks5h. "http" is accepted only for backward compatibility.
  --data=STRING              Request body for POST/PUT/PATCH style requests.
  --header='Name: value'     Repeatable custom header.
  --follow-redirects         Enable redirect following.

TXT);
    exit(1);
}

$configPath = null;
if (isset($options['node'])) {
    $tempFile = tempnam(sys_get_temp_dir(), 'php-tuic-inline-');
    if ($tempFile === false) {
        fwrite(STDERR, "Unable to allocate a temporary config file.\n");
        exit(1);
    }

    $resolvedNode = (new NodeInputResolver())->resolve((string) $options['node'], null);
    $inlineYaml = Symfony\Component\Yaml\Yaml::dump([
        'name' => $resolvedNode->name,
        'type' => 'tuic',
        'server' => $resolvedNode->server,
        'port' => $resolvedNode->port,
        'uuid' => $resolvedNode->uuid,
        'password' => $resolvedNode->password,
        'alpn' => $resolvedNode->alpn,
        'disable-sni' => $resolvedNode->disableSni,
        'reduce-rtt' => $resolvedNode->reduceRtt,
        'udp-relay-mode' => $resolvedNode->udpRelayMode,
        'congestion-controller' => $resolvedNode->congestionControl,
        'skip-cert-verify' => $resolvedNode->skipCertVerify,
        'sni' => $resolvedNode->sni,
    ]);
    if (file_put_contents($tempFile, $inlineYaml) === false) {
        @unlink($tempFile);
        fwrite(STDERR, "Unable to write the temporary config file.\n");
        exit(1);
    }
    $configPath = $tempFile;
} elseif (isset($options['config'])) {
    $configPath = (string) $options['config'];
}

$method = strtoupper((string) ($options['method'] ?? (isset($options['data']) ? 'POST' : 'GET')));
$headers = [];
foreach ((array) ($options['header'] ?? []) as $header) {
    $headers[] = (string) $header;
}

try {
    $client = new TuicRequestClient(
        configPath: (string) $configPath,
        nodeName: isset($options['node-name']) ? (string) $options['node-name'] : null,
        proxyMode: (string) ($options['proxy-mode'] ?? 'socks5h'),
    );

    $response = $client->request(
        $method,
        (string) $options['url'],
        [
            'headers' => $headers,
            'body' => (string) ($options['data'] ?? ''),
            'follow_redirects' => array_key_exists('follow-redirects', $options),
        ],
    );

    fwrite(STDOUT, $response->raw);
} finally {
    if (isset($client)) {
        $client->stop();
    }
    if (isset($tempFile) && is_file($tempFile)) {
        @unlink($tempFile);
    }
}
