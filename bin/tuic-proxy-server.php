#!/usr/bin/env php
<?php declare(strict_types=1);

use PhpTuic\Config\NodeInputResolver;
use PhpTuic\Runtime\ProxyRunner;
use PhpTuic\Runtime\RunOptions;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputDefinition;

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', [
    'config:',
    'node::',
    'node-name::',
    'socks-listen::',
    'allow-ip::',
    'max-connections::',
    'connect-timeout::',
    'idle-timeout::',
    'handshake-timeout::',
    'status-file::',
    'status-interval::',
    'log-file::',
    'pid-file::',
    'quiche-lib::',
    'http-listen::',
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
  --socks-listen=ADDR        Local SOCKS5 proxy listen address, default 127.0.0.1:1080.
  --allow-ip=IP_OR_CIDR      Optional allow list entry for local clients. Repeat to add more.
  --max-connections=NUM      Maximum concurrent local client connections.
  --connect-timeout=SEC      Timeout for opening the TUIC UDP socket.
  --idle-timeout=SEC         QUIC idle timeout in seconds.
  --handshake-timeout=SEC    Maximum time to finish QUIC + TUIC authentication.
  --status-file=PATH         Optional JSON status file.
  --status-interval=SEC      Status file refresh interval.
  --log-file=PATH            Optional runtime log file.
  --pid-file=PATH            Optional PID file for supervisor/systemd integration.
  --quiche-lib=PATH          Absolute path to a custom libquiche file.

Notes:
  --http-listen and --no-http are accepted for backward compatibility, but
  the current quiche runtime only exposes a local SOCKS5 listener.

TXT);
    exit(1);
}

if (array_key_exists('no-socks', $options)) {
    fwrite(STDERR, "The current quiche runtime requires the SOCKS5 listener; --no-socks is not supported.\n");
    exit(1);
}

if (isset($options['http-listen']) || !array_key_exists('no-http', $options)) {
    fwrite(STDERR, "[tuic-proxy-server] HTTP proxy mode is not available in the current quiche runtime. Starting SOCKS5 only.\n");
}

try {
    $node = (new NodeInputResolver())->resolve(
        inlineNode: isset($options['node']) ? (string) $options['node'] : null,
        configPath: isset($options['config']) ? (string) $options['config'] : null,
        nodeName: isset($options['node-name']) ? (string) $options['node-name'] : null,
    );

    $definition = new InputDefinition([
        new InputOption('listen', null, InputOption::VALUE_REQUIRED),
        new InputOption('allow-ip', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY),
        new InputOption('max-connections', null, InputOption::VALUE_REQUIRED),
        new InputOption('connect-timeout', null, InputOption::VALUE_REQUIRED),
        new InputOption('idle-timeout', null, InputOption::VALUE_REQUIRED),
        new InputOption('handshake-timeout', null, InputOption::VALUE_REQUIRED),
        new InputOption('status-file', null, InputOption::VALUE_REQUIRED),
        new InputOption('status-interval', null, InputOption::VALUE_REQUIRED),
        new InputOption('log-file', null, InputOption::VALUE_REQUIRED),
        new InputOption('pid-file', null, InputOption::VALUE_REQUIRED),
    ]);

    $inputValues = [
        '--listen' => (string) ($options['socks-listen'] ?? '127.0.0.1:1080'),
        '--allow-ip' => isset($options['allow-ip'])
            ? (array) $options['allow-ip']
            : [],
        '--max-connections' => isset($options['max-connections']) ? (string) $options['max-connections'] : '1024',
        '--connect-timeout' => isset($options['connect-timeout']) ? (string) $options['connect-timeout'] : '10',
        '--idle-timeout' => isset($options['idle-timeout']) ? (string) $options['idle-timeout'] : '300',
        '--handshake-timeout' => isset($options['handshake-timeout']) ? (string) $options['handshake-timeout'] : '15',
        '--status-interval' => isset($options['status-interval']) ? (string) $options['status-interval'] : '10',
    ];

    foreach (['status-file', 'log-file', 'pid-file'] as $name) {
        if (isset($options[$name]) && (string) $options[$name] !== '') {
            $inputValues['--' . $name] = (string) $options[$name];
        }
    }

    $arrayInput = new ArrayInput($inputValues, $definition);

    $runner = new ProxyRunner(
        node: $node,
        options: RunOptions::fromInput($arrayInput),
        quicheLibrary: isset($options['quiche-lib']) ? (string) $options['quiche-lib'] : null,
    );

    $runner->run();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
