# Packagist Publishing Guide

## Recommended First Release

- Repository: `https://github.com/18230/php-tuic-client`
- Composer package name: `18230/php-tuic-client`
- First Packagist version tag: `v0.1.0`

## Submit the Package

1. Open [Packagist Submit](https://packagist.org/packages/submit).
2. Paste `https://github.com/18230/php-tuic-client`.
3. Submit after the `v0.1.0` tag is available.

## Auto Updates

This project ships the same GitHub-based Packagist sync workflow pattern as `php-shadowsocks-client`.

- Workflow: `.github/workflows/packagist-sync.yml`
- Required secret: `PACKAGIST_API_TOKEN`
- Recommended variable: `PACKAGIST_USERNAME`
  For this account, set it to `aiqq363927173`.
