# php-tuic-client

[![Ubuntu CI](https://img.shields.io/github/actions/workflow/status/18230/php-tuic-client/ubuntu-ci.yml?branch=main&label=ubuntu%20ci)](https://github.com/18230/php-tuic-client/actions/workflows/ubuntu-ci.yml)
[![Release](https://img.shields.io/github/v/tag/18230/php-tuic-client?label=release)](https://github.com/18230/php-tuic-client/tags)
[![Packagist Version](https://img.shields.io/packagist/v/18230/php-tuic-client?label=packagist)](https://packagist.org/packages/18230/php-tuic-client)
[![Packagist Downloads](https://img.shields.io/packagist/dt/18230/php-tuic-client?label=downloads)](https://packagist.org/packages/18230/php-tuic-client)
[![License](https://img.shields.io/github/license/18230/php-tuic-client)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)](https://www.php.net/)

[中文文档](README.zh-CN.md)

`php-tuic-client` is a pure-PHP TUIC client scaffold. It aligns with the structure, tooling, and release posture of `php-shadowsocks-client`, while keeping the TUIC transport layer intentionally unimplemented until the next phase.

## Features

- PHP 8.2+
- Windows, Linux, and macOS
- Composer package with a CLI entrypoint
- YAML / JSON config loading for TUIC nodes
- `doctor` environment checks
- `run --dry-run` runtime validation
- Cross-platform startup scripts for local validation
- GitHub Actions Ubuntu CI workflow

## Current Scope

Implemented:

- TUIC config parsing
- CLI runtime skeleton
- Doctor command
- Dry-run validation path
- Bilingual docs and release scaffolding

Not implemented yet:

- QUIC / TUIC transport runtime
- Local SOCKS5 frontend
- UDP relay handling
- Framework integration helpers

## Installation

Install from Packagist:

```bash
composer require 18230/php-tuic-client
```

For local development in this repository:

```bash
composer install
```

## Quick Start

Validate the example config:

```bash
php bin/tuic-client doctor --config=examples/node.example.yaml
```

Resolve the runtime configuration without starting a transport:

```bash
php bin/tuic-client run --config=examples/node.example.yaml --dry-run
```

Start from explicit options:

```bash
php bin/tuic-client run \
  --server=tuic.example.com \
  --port=443 \
  --uuid=11111111-2222-3333-4444-555555555555 \
  --password=change-me \
  --local=127.0.0.1:1080 \
  --dry-run
```

Check available options:

```bash
php bin/tuic-client --help
```

## Configuration

Recommended config file structure:

```yaml
node:
  server: tuic.example.com
  port: 443
  uuid: 11111111-2222-3333-4444-555555555555
  password: change-me
  sni: tuic.example.com
  alpn:
    - h3
  udp_relay_mode: native
  congestion_controller: bbr
  allow_insecure: false

runtime:
  local: 127.0.0.1:1080
  log_level: info
```

See [examples/node.example.yaml](examples/node.example.yaml) for a ready-to-run example.

## Notes

- This project currently provides an initialization scaffold, not a production TUIC transport implementation.
- The current `run` command is meant for configuration validation and future runtime wiring.
- The CLI exits successfully only when `--dry-run` is used.

## Documentation

- [English framework guide](docs/en/frameworks.md)
- [中文框架接入说明](docs/zh-CN/frameworks.md)
- [English production guide](docs/en/production.md)
- [中文生产部署说明](docs/zh-CN/production.md)
- [English release checklist](docs/en/release.md)
- [English Packagist publishing guide](docs/en/packagist.md)
- [中文发布检查清单](docs/zh-CN/release.md)
- [中文 Packagist 发布说明](docs/zh-CN/packagist.md)
- [Changelog](CHANGELOG.md)

## Testing

```bash
composer test
php bin/tuic-client doctor --config=examples/node.example.yaml
php bin/tuic-client run --config=examples/node.example.yaml --dry-run
```
