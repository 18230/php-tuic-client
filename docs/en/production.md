# Production Guide

## Topology

Run `php-tuic-client` as a standalone local SOCKS5 process.

```text
app / curl / scraper -> 127.0.0.1:1080 -> php-tuic-client -> TUIC server -> target site
```

## Platform Status

- Linux: recommended production platform
- macOS: acceptable for long-running developer or small production setups
- Windows: supported, with official x64 prebuilt `quiche.dll` bundled in release tags

## Required Runtime Pieces

- PHP `8.2.4+`
- `ext-ffi`
- `ext-json`
- `ext-openssl`
- `ext-sockets`
- `libquiche` / `quiche.dll`

Official tagged releases bundle these triplets directly in the repository content and therefore in the Composer dist package:

- `resources/native/windows-x64/quiche.dll`
- `resources/native/linux-x64/libquiche.so`
- `resources/native/macos-x64/libquiche.dylib`

If your target machine is not one of those x64 triplets, build your own library and either place it under `resources/native/<platform>-<arch>/` or point `QUICHE_LIB` at the absolute path.

## Startup

Shell:

```bash
export PHP_BIN=/usr/bin/php
export TUIC_CONFIG=/opt/php-tuic-client/config/tuic.yaml
export TUIC_SOCKS_LISTEN=127.0.0.1:1080
export QUICHE_LIB=/opt/php-tuic-client/resources/native/linux-x64/libquiche.so

./scripts/start-tuic-client.sh
```

PowerShell:

```powershell
$env:PHP_BIN = 'E:\phpEnv\php\php-8.4\php.exe'
$env:TUIC_CONFIG = 'E:\proxy\tuic.yaml'
$env:TUIC_SOCKS_LISTEN = '127.0.0.1:1080'
$env:QUICHE_LIB = 'E:\proxy\resources\native\windows-x64\quiche.dll'

.\scripts\start-tuic-client.ps1
```

## Build Native Library

Use this only for `dev-main`, local development, or unsupported architectures. Official tagged releases already vendor the x64 libraries.

Windows:

```powershell
.\scripts\build-quiche.ps1
```

Linux / macOS:

```bash
./scripts/build-quiche.sh
```

## Operational Guidance

- Keep the SOCKS5 listener on loopback unless you explicitly need broader access.
- Run `php bin/tuic-client doctor ...` during deployment validation.
- Treat `skip-cert-verify: true` as a deliberate trust tradeoff.
- Use a supervisor such as `systemd`, `supervisord`, or `launchd`.
- Release tags already ship the x64 native libraries inside the Composer package. Keep your deployment process on the tagged archive or mirror the same `resources/native/` tree in your own artifact.
