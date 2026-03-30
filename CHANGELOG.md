# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

## [v0.2.2] - 2026-03-30

### Changed

- Expanded the English and Simplified Chinese docs with node field guidance, runtime file explanations, troubleshooting, and deployment checklists
- Aligned framework config templates and command stubs with the current SOCKS5-only runtime model
- Refreshed the bundled examples so they demonstrate the recommended local SOCKS5 workflow instead of the removed HTTP proxy runtime

## [v0.2.1] - 2026-03-30

### Added

- Production-oriented runtime options for local SOCKS5 startup, including `allow-ip`, `max-connections`, `connect-timeout`, `idle-timeout`, `handshake-timeout`, `status-file`, `log-file`, and `pid-file`
- Runtime ACL matching, status snapshot writing, and lightweight file/stderr logging
- Unit coverage for runtime option parsing and IP allow-list evaluation
- Automatic real-node TUIC E2E verification on `main` pushes and version tags

### Changed

- Raised the default QUIC idle timeout from 30 seconds to 300 seconds
- Fixed the local SOCKS5 runtime to enforce connection limits and close stalled handshakes
- Reworked the managed request client to rely on the local SOCKS5 proxy path instead of unfinished direct HTTP/TCP helper methods
- Aligned `bin/tuic-proxy-server.php` and the startup scripts with the current SOCKS5-only runtime model

## [v0.2.0] - 2026-03-30

### Changed

- Renamed the Composer package to `18230/php-tuic-client`
- Aligned the repository structure, docs, CI, and release assets with `php-shadowsocks-client`
- Stabilized the runtime around packaged `cloudflare/quiche` FFI libraries instead of a source-build-only workflow

### Added

- Unified `bin/tuic-client` CLI with `run` and `doctor`
- PHPUnit test suite and `composer test`
- Laravel and ThinkPHP service-provider integration
- Cross-platform project scripts and CI for Windows, Linux, and macOS
- Packagist sync workflow
- English and Simplified Chinese documentation structure
- Production deployment templates for systemd, Supervisor, and launchd
- Prebuilt `libquiche` binaries vendored into release artifacts for `windows-x64`, `linux-x64`, and `macos-x64`
- Real-node end-to-end verification on `ubuntu-22.04`, `windows-latest`, and `macos-15-intel`

### Notes

- `ext-ffi` must be enabled in the PHP runtime that executes this package

## [v0.1.0] - 2026-03-29

Initial public release.

### Added

- TUIC v5 client implementation in PHP
- Clash-style YAML / JSON node loading
- Native `http://` request support over TUIC
- Managed local `HTTP` and `SOCKS5` proxy server
- Unified request client with `get`, `post`, `put`, `patch`, `delete`, `head`, `options`, and `request`
- Cross-platform local process handling for Windows, Linux, and macOS
- CLI entrypoints for proxy startup, single requests, and raw TCP requests
