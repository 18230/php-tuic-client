# Release Checklist

## Local Validation

Run:

```bash
composer validate --strict
composer test
php bin/tuic-client doctor --config=examples/node.example.yaml
php bin/tuic-client run --config=examples/node.example.yaml --dry-run
php bin/tuic-client --help
```

## First Public Release

- Confirm the final package name in `composer.json`
- Confirm the final repository URL
- Review the changelog and README
- Tag `v0.1.0` as the first scaffold release
