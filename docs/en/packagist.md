# Packagist Publishing

Package name:

- `18230/php-tuic-client`

Repository:

- `https://github.com/18230/php-tuic-client`

## Initial Submission

1. Open [Packagist Submit](https://packagist.org/packages/submit)
2. Submit the repository URL
3. Confirm that Packagist detects package `18230/php-tuic-client`
4. Confirm that the intended release tag is visible

## Automatic Updates

Two automation paths are included:

1. GitHub Actions workflow
   - [`.github/workflows/packagist-sync.yml`](../../.github/workflows/packagist-sync.yml)
2. Native GitHub webhook helper
   - [scripts/setup-packagist-github-hook.ps1](../../scripts/setup-packagist-github-hook.ps1)
   - [scripts/setup-packagist-github-hook.sh](../../scripts/setup-packagist-github-hook.sh)

Recommended GitHub configuration:

- repository secret `PACKAGIST_API_TOKEN`
- repository variable `PACKAGIST_USERNAME`

## Before Tagging

Run:

```bash
composer validate --strict
composer test
php bin/tuic-client doctor --config=examples/node.example.yaml
```

Also make sure the latest `Native Binaries` workflow has already committed refreshed files under `resources/native/`. Packagist downloads the tagged repository snapshot, so those native files must exist in Git before you tag the release.
