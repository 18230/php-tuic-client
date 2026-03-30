#!/usr/bin/env php
<?php declare(strict_types=1);

use PhpTuic\Config\NodeInputResolver;
use PhpTuic\Runtime\ProxyRunner;

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', [
    'config:',
    'node::',
    'node-name::',
    'http-listen::',
    'socks-listen::',
    'no-http',
    'no-socks',
]);

if (!isset($options['config']) && !isset($options['node'])) {
    fwrite(STDERR, <<<TXT
Usage:
  php bin/tuic-proxy-server.php --config=/path/to/node.yaml [options]
  php bin/tuic-proxy-server.php --node="{ ... }" [options]

Options:
  --node=YAML_OR_JSON        Inline TUIC node config in YAML or JSON.
  --node-name=NAME           Optional node name when config contains multiple proxies.
  --http-listen=ADDR         Local HTTP proxy listen address, default 127.0.0.1:8080.
  --socks-listen=ADDR        Local SOCKS5 proxy listen address, default 127.0.0.1:1080.
  --no-http                  Disable the HTTP proxy listener.
  --no-socks                 Disable the SOCKS5 proxy listener.

TXT);
    exit(1);
}

try {
    $node = (new NodeInputResolver())->resolve(
        inlineNode: isset($options['node']) ? (string) $options['node'] : null,
        configPath: isset($options['config']) ? (string) $options['config'] : null,
        nodeName: isset($options['node-name']) ? (string) $options['node-name'] : null,
    );

    $runner = new ProxyRunner(
        node: $node,
        httpListen: (string) ($options['http-listen'] ?? '127.0.0.1:8080'),
        socksListen: (string) ($options['socks-listen'] ?? '127.0.0.1:1080'),
        enableHttp: !array_key_exists('no-http', $options),
        enableSocks: !array_key_exists('no-socks', $options),
    );

    $runner->run();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
