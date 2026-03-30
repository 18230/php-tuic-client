# Changelog

All notable changes to this project will be documented in this file.

## [v0.2.0] - 2026-03-30

### Changed

- Renamed the Composer package to `18230/php-tuic-client`
- Aligned the repository structure, docs, CI, and release assets with `php-shadowsocks-client`

### Added

- Unified `bin/tuic-client` CLI with `run` and `doctor`
- PHPUnit test suite and `composer test`
- Laravel and ThinkPHP service-provider integration
- Cross-platform project scripts and CI, with runtime support currently aimed at Linux and macOS
- Packagist sync workflow
- English and Simplified Chinese documentation structure
- Production deployment templates for systemd, Supervisor, and launchd

### Notes

- The QUIC transport still depends on `amphp/quic`, which is currently published upstream as `dev-master`
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
