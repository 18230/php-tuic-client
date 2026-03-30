# php-tuic-client

[![CI](https://img.shields.io/github/actions/workflow/status/18230/php-tuic-client/ci.yml?branch=main&label=ci)](https://github.com/18230/php-tuic-client/actions/workflows/ci.yml)
[![Release](https://img.shields.io/github/v/tag/18230/php-tuic-client?label=release)](https://github.com/18230/php-tuic-client/tags)
[![Packagist Version](https://img.shields.io/packagist/v/18230/php-tuic-client?label=packagist)](https://packagist.org/packages/18230/php-tuic-client)
[![Packagist Downloads](https://img.shields.io/packagist/dt/18230/php-tuic-client?label=downloads)](https://packagist.org/packages/18230/php-tuic-client)
[![License](https://img.shields.io/github/license/18230/php-tuic-client)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2.4%2B-777bb4)](https://www.php.net/)

[中文文档](README.zh-CN.md)

`php-tuic-client` is a pure-PHP TUIC client package for PHP 8.2.4+. It provides:

- a native TUIC client implementation over QUIC/TLS
- a local HTTP and SOCKS5 proxy runtime
- a reusable request client for application code
- a Composer package layout that can be integrated into Laravel and ThinkPHP

## Features

- PHP 8.2.4+
- Project tooling works on Windows, Linux, and macOS
- Clash-style YAML and JSON node loading
- Inline TUIC node parsing for CLI startup
- Local HTTP proxy and SOCKS5 proxy listeners
- Unified `TuicRequestClient` for application code
- Cross-platform startup scripts
- Cross-platform GitHub Actions CI

Runtime note:

- The current `amphp/quic` package ships native `libquiche` artifacts for Linux and macOS.
- Windows currently supports config parsing, tests, and CLI dry-runs in this repository, but the actual TUIC runtime is blocked by the upstream native binding distribution.

## Current Scope

Implemented:

- TUIC v5 authentication over QUIC/TLS
- Native `http://` requests over TUIC
- `https://` requests through the local proxy plus PHP cURL
- Local HTTP proxy
- Local SOCKS5 proxy
- `doctor` and `run` CLI workflow
- Laravel and ThinkPHP service-provider integration

Not implemented yet:

- UDP relay
- Connection pooling
- Multi-node scheduling
- Long-duration soak validation for very high concurrency

## Installation

Install from Packagist:

```bash
composer require 18230/php-tuic-client
```

For local development in this repository:

```bash
composer install
```

## Requirements

Required PHP extensions:

- `ext-curl`
- `ext-ffi`
- `ext-json`
- `ext-openssl`
- `ext-sockets`

Important note:

- The current QUIC transport dependency is [`amphp/quic`](https://github.com/amphp/quic), and its upstream package is currently published as `dev-master`.
- `php-tuic-client` pins that dependency to a known commit for repeatable installs.
- `doctor` will tell you if `ext-ffi` or other required extensions are missing.

## Quick Start

Start the local proxy runtime with inline YAML:

```bash
php bin/tuic-client --node="{ name: 'SG 01', type: tuic, server: your-tuic-server.example.com, port: 443, uuid: 11111111-1111-1111-1111-111111111111, password: your-password, alpn: [h3], disable-sni: false, reduce-rtt: false, udp-relay-mode: native, congestion-controller: bbr, skip-cert-verify: false, sni: your-tuic-server.example.com }"
```

Start with a config file:

```bash
php bin/tuic-client --config=examples/node.example.yaml
```

Validate the runtime before you start:

```bash
php bin/tuic-client doctor --config=examples/node.example.yaml
```

Run a single request:

```bash
php bin/tuic-request --config=examples/node.example.yaml --url=https://api.ipify.org?format=json
```

Check available options:

```bash
php bin/tuic-client --help
```

## Configuration

The main CLI accepts configuration from:

- `--node` with inline YAML or JSON
- `--config` with YAML or JSON files
- `--node-name` when the config contains multiple proxies

Recommended config file structure:

```yaml
proxies:
  - name: "SG 01"
    type: tuic
    server: your-tuic-server.example.com
    port: 443
    uuid: 11111111-1111-1111-1111-111111111111
    password: replace-with-your-password
    alpn: [h3]
    disable-sni: false
    reduce-rtt: false
    udp-relay-mode: native
    congestion-controller: bbr
    skip-cert-verify: false
    sni: your-tuic-server.example.com
```

Useful runtime options:

- `--http-listen=127.0.0.1:8080`
- `--socks-listen=127.0.0.1:1080`
- `--no-http`
- `--no-socks`
- `--node-name=SG 01`
- `--dry-run`

## Application Usage

### PHP request client

```php
<?php

use PhpTuic\Http\TuicRequestClient;

require __DIR__ . '/vendor/autoload.php';

$client = new TuicRequestClient(__DIR__ . '/config/tuic.yaml');

try {
    $response = $client->get('https://api.ipify.org?format=json');
    echo $response->statusCode . PHP_EOL;
    echo $response->body . PHP_EOL;
} finally {
    $client->stop();
}
```

### CLI curl

Start the proxy:

```bash
php bin/tuic-client --config=examples/node.example.yaml
```

Then:

```bash
curl -x http://127.0.0.1:8080 https://api.ipify.org?format=json
curl --socks5-hostname 127.0.0.1:1080 https://api.ipify.org?format=json
```

## TLS / CA Configuration

Two certificate layers matter:

1. TUIC server certificate
   Controlled by the node field `skip-cert-verify`
2. Target HTTPS site certificate
   Controlled by PHP cURL CA settings when `TuicRequestClient` sends `https://` traffic through the local proxy

If your PHP runtime does not have a CA bundle configured, proxied `https://` requests can fail even if the TUIC tunnel itself is healthy.

Template:

- [resources/php/cacert.ini.example](resources/php/cacert.ini.example)

## Framework Integration

- [English framework guide](docs/en/frameworks.md)
- [中文框架接入说明](docs/zh-CN/frameworks.md)

## Production Deployment

- [English production guide](docs/en/production.md)
- [中文生产部署说明](docs/zh-CN/production.md)

Recommended minimum production posture:

- keep proxy listeners on loopback unless you explicitly need LAN access
- run `php bin/tuic-client doctor ...` before deployment
- enable `ext-ffi` in the exact PHP runtime used by the long-running process
- use Linux or macOS plus Supervisor, systemd, launchd, or another process manager for long-running deployments
- configure CA files for application-side HTTPS traffic if your business requests use `https://`

## Release Notes

- [English release checklist](docs/en/release.md)
- [English Packagist publishing guide](docs/en/packagist.md)
- [中文发布检查清单](docs/zh-CN/release.md)
- [中文 Packagist 发布说明](docs/zh-CN/packagist.md)
- [Changelog](CHANGELOG.md)

## Examples

- [Node config example](examples/node.example.yaml)
- [Local env example](examples/tuic-client.env.example)
- [PHP request example](examples/request_client.php)
- [HTTP proxy example](examples/http_proxy_example.php)
- [SOCKS5 proxy example](examples/socks5_proxy_example.php)

## Testing

```bash
composer test
php bin/tuic-client doctor --config=examples/node.example.yaml
```
