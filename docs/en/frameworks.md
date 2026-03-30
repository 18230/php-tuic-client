# Framework Integration

`php-tuic-client` is currently a scaffold project, so it does not ship framework-specific service providers yet.

Recommended integration model for this stage:

1. Keep TUIC node settings in your application config.
2. Validate them with `php bin/tuic-client doctor --config=...`.
3. Use `php bin/tuic-client run --dry-run` in CI or deployment checks.

Once the transport runtime is implemented, this package can grow framework helpers similar to `php-shadowsocks-client`.
