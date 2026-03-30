# php-tuic-client

[![CI](https://img.shields.io/github/actions/workflow/status/18230/php-tuic-client/ci.yml?branch=main&label=ci)](https://github.com/18230/php-tuic-client/actions/workflows/ci.yml)
[![Native](https://img.shields.io/github/actions/workflow/status/18230/php-tuic-client/native-build.yml?branch=main&label=native)](https://github.com/18230/php-tuic-client/actions/workflows/native-build.yml)
[![Release](https://img.shields.io/github/v/tag/18230/php-tuic-client?label=release)](https://github.com/18230/php-tuic-client/tags)
[![Packagist Version](https://img.shields.io/packagist/v/18230/php-tuic-client?label=packagist)](https://packagist.org/packages/18230/php-tuic-client)
[![License](https://img.shields.io/github/license/18230/php-tuic-client)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2.4%2B-777bb4)](https://www.php.net/)

[中文文档](README.zh-CN.md)

`php-tuic-client` is a pure PHP TUIC v5 client focused on one job: start a local `socks5://127.0.0.1:1080` style proxy and relay it through a TUIC node.

The QUIC transport is provided by `cloudflare/quiche` through PHP FFI. The local accept loop, node parsing, CLI flow, and runtime control stay in PHP.

## Scope

- TUIC v5 authentication
- local SOCKS5 server
- Clash-style YAML / JSON node parsing
- Windows, Linux, and macOS project support
- cross-platform startup scripts
- `doctor` and `run` CLI workflow
- compatibility request helpers for framework projects, built on top of the same local SOCKS5 runtime

Not in scope right now:

- UDP relay
- HTTP proxy runtime
- multi-node scheduling

## Requirements

- PHP `8.2.4+`
- `ext-ffi`
- `ext-json`
- `ext-openssl`
- `ext-sockets`
- a loadable `libquiche` shared library

## Install

```bash
composer require 18230/php-tuic-client
```

Official release tags vendor prebuilt `libquiche` binaries for:

- `windows-x64`
- `linux-x64`
- `macos-x64`

That means a normal `composer require` from a tagged release can use the bundled shared library directly. If you install from `dev-main` or run on another architecture, build your own library and point `QUICHE_LIB` or `--quiche-lib` at it.

## Build libquiche

Official releases already include prebuilt x64 libraries. Build manually only if you are developing locally, using `dev-main`, or targeting another architecture.

You need a shared `quiche` build with the `ffi` feature enabled.

Windows:

```powershell
.\scripts\build-quiche.ps1
```

Linux / macOS:

```bash
./scripts/build-quiche.sh
```

If your library lives outside the default search locations, point `QUICHE_LIB` or `--quiche-lib` to the absolute path.

## Quick Start

Validate runtime prerequisites:

```bash
php bin/tuic-client doctor --config=examples/node.example.yaml
```

The doctor command prints the detected native triplet and the library search order. On official release installs it should resolve the bundled file under `resources/native/<platform>-<arch>/`.

Start with inline YAML:

```bash
php bin/tuic-client \
  --node="{ name: 'SG 01', type: tuic, server: your-tuic-server.example.com, port: 443, uuid: 11111111-1111-1111-1111-111111111111, password: your-password, alpn: [h3], disable-sni: false, reduce-rtt: false, udp-relay-mode: native, congestion-controller: bbr, skip-cert-verify: false, sni: your-tuic-server.example.com }" \
  --listen=127.0.0.1:1080
```

Or start from a config file:

```bash
php bin/tuic-client --config=examples/node.example.yaml --listen=127.0.0.1:1080
```

Then point your tools at:

```text
socks5://127.0.0.1:1080
```

For command-line tools, prefer `socks5h://` so DNS resolution also goes through the proxy:

```bash
curl --proxy socks5h://127.0.0.1:1080 https://api.ipify.org?format=json
```

Examples:

```php
<?php

$ch = curl_init('https://api.ipify.org?format=json');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_PROXY => '127.0.0.1:1080',
    CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME,
]);

echo curl_exec($ch);
curl_close($ch);
```

## Node Fields

Required TUIC node fields:

- `name`: local display name
- `type`: must be `tuic`
- `server`: remote TUIC server hostname or IP
- `port`: remote TUIC port
- `uuid`: TUIC user UUID
- `password`: TUIC password / token
- `alpn`: usually `[h3]`
- `sni`: TLS server name

Supported optional fields:

- `skip-cert-verify`: defaults to `false`
- `disable-sni`: defaults to `false`
- `reduce-rtt`: defaults to `false`
- `udp-relay-mode`: parsed but UDP relay is not implemented yet
- `congestion-controller`: typically `bbr`

Minimal example:

```yaml
proxies:
  - name: demo-tuic
    type: tuic
    server: example.com
    port: 443
    uuid: 00000000-0000-4000-8000-000000000000
    password: replace-with-your-password
    alpn: [h3]
    sni: example.com
```

## CLI

Main options:

- `--node`
- `--config`
- `--node-name`
- `--listen`
- `--allow-ip`
- `--max-connections`
- `--connect-timeout`
- `--idle-timeout`
- `--handshake-timeout`
- `--status-file`
- `--status-interval`
- `--log-file`
- `--pid-file`
- `--quiche-lib`
- `--dry-run`

Production-style example:

```bash
php bin/tuic-client \
  --config=/opt/php-tuic-client/config/tuic.yaml \
  --listen=127.0.0.1:1080 \
  --allow-ip=127.0.0.1 \
  --max-connections=2048 \
  --connect-timeout=12 \
  --idle-timeout=600 \
  --handshake-timeout=20 \
  --status-file=/opt/php-tuic-client/runtime/status.json \
  --log-file=/opt/php-tuic-client/runtime/proxy.log \
  --pid-file=/opt/php-tuic-client/runtime/proxy.pid
```

## Node Example

```yaml
proxies:
  - name: demo-tuic
    type: tuic
    server: example.com
    port: 443
    uuid: 00000000-0000-4000-8000-000000000000
    password: replace-with-your-password
    alpn: [h3]
    disable-sni: false
    reduce-rtt: false
    udp-relay-mode: native
    congestion-controller: bbr
    skip-cert-verify: false
    sni: example.com
```

## Runtime Outputs

When you run the proxy in production, these files are the most useful:

- `log-file`: human-readable runtime log
- `status-file`: JSON snapshot of listener, node, and counters
- `pid-file`: current process ID

The `status-file` includes counters such as:

- `accepted_connections_total`
- `closed_connections_total`
- `handshake_timeouts_total`
- `tuic_auth_success_total`
- `tuic_auth_failure_total`

## Production Notes

- Keep the SOCKS5 listener on loopback unless you truly need LAN access.
- Run `doctor` against the exact PHP binary that will host the long-running process.
- Official release tags already ship bundled x64 native libraries inside the Composer package.
- Production flags stay intentionally small and explicit: `allow-ip`, `max-connections`, `connect-timeout`, `idle-timeout`, `handshake-timeout`, `status-file`, `log-file`, and `pid-file`.
- If you deploy on another architecture, ship your own `libquiche` with the same artifact and either place it under the matching triplet directory or set `QUICHE_LIB`.
- Prefer Linux for long-running production workloads.

## Troubleshooting

- `doctor` fails before startup:
  Run `php bin/tuic-client doctor ...` with the exact PHP binary you will use in production.
- `ext-ffi` is disabled:
  Enable FFI in `php.ini`, then rerun `doctor`.
- `libquiche` cannot be found:
  Use the tagged release package or point `QUICHE_LIB` / `--quiche-lib` at the absolute library path.
- The process starts but local requests fail:
  Check `log-file` and `status-file` first. If `tuic_auth_failure_total` is non-zero, verify node credentials, SNI, and TLS settings.
- Windows / Linux / macOS mismatch:
  Make sure the packaged native library matches the current platform and architecture.

## Framework Helpers

The package still ships `TuicRequestClient` and framework service providers for Laravel / ThinkPHP. These helpers are convenience layers built on top of the same local SOCKS5 runtime plus cURL. They are optional and not required if you only want the protocol-level local proxy.

More detail:

- [English production guide](docs/en/production.md)
- [中文生产部署说明](docs/zh-CN/production.md)

## Testing

```bash
composer test
php bin/tuic-client --config=examples/node.example.yaml --dry-run
php bin/tuic-client doctor --config=examples/node.example.yaml
```

The release pipeline also runs a real-node end-to-end probe on `ubuntu-22.04`, `windows-latest`, and `macos-15-intel` using the packaged `libquiche` binaries and a local `socks5://127.0.0.1:11084` listener.
