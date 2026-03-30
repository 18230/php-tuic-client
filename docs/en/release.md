# Release Checklist

## Before Release

- confirm the package name is `18230/php-tuic-client`
- confirm `composer.json`, `README.md`, and `README.zh-CN.md` are in sync
- confirm no real TUIC credentials are committed
- confirm `examples/node.user.yaml` only contains redacted values
- confirm `.github/workflows/native-build.yml` is green on `main`
- confirm `resources/native/manifest.json` was refreshed by the native workflow
- run `composer validate --strict`
- run `composer test`
- run `php bin/tuic-client doctor --config=examples/node.example.yaml`

## Native Build Flow

The Composer dist package only contains files that exist in the tagged repository snapshot. Release assets alone are not enough.

Release flow:

1. Push your code to `main`
2. Wait for `Native Binaries` to finish and auto-commit refreshed files under `resources/native/`
3. Pull that bot commit locally
4. Tag the commit that already contains `resources/native/*`

## Tagging

```bash
git tag v0.x.y
git push origin main --tags
```

## After Tagging

- verify GitHub Actions CI is green
- verify GitHub Actions `Native Binaries` is green for the tag
- verify Packagist sync completed
- verify `composer require 18230/php-tuic-client` in a clean project resolves the bundled native file without an extra download step
