# Production Guide

## Recommended Topology

Run `php-tuic-client` as a standalone local proxy process and let your application reuse that endpoint.

```text
App / curl / browser -> 127.0.0.1:8080 or 127.0.0.1:1080 -> php-tuic-client -> TUIC server -> target site
```

## Platform Status

- Linux: intended runtime platform
- macOS: intended runtime platform
- Windows: package tooling works, but the current upstream `amphp/quic` distribution does not ship Windows `libquiche` binaries, so the TUIC runtime is not yet available there

## Startup Scripts

Cross-platform scripts are included:

- Windows PowerShell: [scripts/start-tuic-client.ps1](../../scripts/start-tuic-client.ps1)
- Windows CMD: [scripts/start-tuic-client.bat](../../scripts/start-tuic-client.bat)
- Linux/macOS shell: [scripts/start-tuic-client.sh](../../scripts/start-tuic-client.sh)

Supported environment variables:

- `PHP_BIN`
- `TUIC_CONFIG`
- `TUIC_NODE`
- `TUIC_NODE_NAME`
- `TUIC_HTTP_LISTEN`
- `TUIC_SOCKS_LISTEN`
- `TUIC_NO_HTTP`
- `TUIC_NO_SOCKS`

Example:

- [examples/tuic-client.env.example](../../examples/tuic-client.env.example)

## Linux / macOS

```bash
export PHP_BIN=/usr/bin/php
export TUIC_CONFIG=/opt/php-tuic-client/config/tuic.yaml
export TUIC_HTTP_LISTEN=127.0.0.1:8080
export TUIC_SOCKS_LISTEN=127.0.0.1:1080

./scripts/start-tuic-client.sh
```

Templates:

- systemd: [resources/systemd/php-tuic-client.service](../../resources/systemd/php-tuic-client.service)
- Supervisor: [resources/supervisor/php-tuic-client.conf](../../resources/supervisor/php-tuic-client.conf)
- launchd: [resources/launchd/io.github.18230.php-tuic-client.plist](../../resources/launchd/io.github.18230.php-tuic-client.plist)

## Windows

```powershell
$env:PHP_BIN = 'E:\phpEnv\php\php-8.2\php.exe'
$env:TUIC_CONFIG = 'E:\proxy\tuic.yaml'
$env:TUIC_HTTP_LISTEN = '127.0.0.1:8080'
$env:TUIC_SOCKS_LISTEN = '127.0.0.1:1080'

.\scripts\start-tuic-client.ps1
```

Use this only after you have supplied a compatible Windows `libquiche` build. Out of the box, the current upstream `amphp/quic` package does not include one.

## FFI Requirement

`ext-ffi` must be enabled in the exact PHP runtime used by the long-running proxy process.

Typical `php.ini` line:

```ini
extension=ffi
```

## TLS / CA Bundle

Two certificate chains matter:

1. TUIC server certificate
   Controlled by `skip-cert-verify` in the TUIC node config
2. Target HTTPS site certificate
   Controlled by PHP cURL CA settings

CA bundle template:

- [resources/php/cacert.ini.example](../../resources/php/cacert.ini.example)

## Operational Notes

- Keep listeners on loopback unless you explicitly need LAN access
- Run `php bin/tuic-client doctor --config=/path/to/tuic.yaml` during deployment
- Prefer Linux for long-running production workloads
- Use a process supervisor for restarts and log capture
- Treat `skip-cert-verify: true` as a trust tradeoff and document that choice
