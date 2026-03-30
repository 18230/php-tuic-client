# Release Checklist

## Before Release

- confirm the package name is `18230/php-tuic-client`
- confirm `composer.json`, `README.md`, and `README.zh-CN.md` are in sync
- confirm no real TUIC credentials are committed
- confirm `examples/node.user.yaml` only contains redacted values
- run `composer validate --strict`
- run `composer test`
- run `php bin/tuic-client doctor --config=examples/node.example.yaml`

## Tagging

```bash
git tag v0.x.y
git push origin main --tags
```

## After Tagging

- verify GitHub Actions CI is green
- verify Packagist sync completed
- verify installation from Packagist in a clean project
