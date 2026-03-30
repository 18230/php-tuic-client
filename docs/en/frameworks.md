# Framework Integration

## Installation

```bash
composer require 18230/php-tuic-client
```

## Laravel

The package exposes a service provider:

- `PhpTuic\Integration\Laravel\TuicServiceProvider`

If package discovery is enabled, Laravel will register it automatically.

The primary production runtime is still the standalone local SOCKS5 process. The framework helper layer is optional and wraps that same runtime with cURL-based request helpers.

Published config template:

- [config/laravel/tuic-client.php](../../config/laravel/tuic-client.php)

Typical usage:

```php
use PhpTuic\Http\TuicRequestClient;

$client = app(TuicRequestClient::class);

try {
    $response = $client->get('https://api.ipify.org?format=json');
    echo $response->body;
} finally {
    $client->stop();
}
```

If you only need the proxy address instead of a request helper:

```php
use PhpTuic\Http\TuicRequestClient;

$client = app(TuicRequestClient::class);
$socksProxy = $client->getSocksProxyUrl();
```

To publish the config:

```bash
php artisan vendor:publish --tag=tuic-client-config
```

## ThinkPHP

Register the service:

```php
return [
    \PhpTuic\Integration\ThinkPHP\TuicService::class,
];
```

Config template:

- [config/thinkphp/tuic_client.php](../../config/thinkphp/tuic_client.php)

Typical usage:

```php
use PhpTuic\Http\TuicRequestClient;

$client = app(TuicRequestClient::class);

try {
    $response = $client->get('https://api.ipify.org?format=json');
    return $response->body;
} finally {
    $client->stop();
}
```

You can also obtain the local SOCKS5 endpoint directly through `getSocksProxyUrl()`.

## CLI From Framework Projects

If your framework project only needs the local proxy runtime, call:

```bash
vendor/bin/tuic-client --config=config/tuic.yaml
```

The included command stubs are still available and now target the SOCKS5 runtime only:

- [stubs/laravel/TuicProxyStartCommand.php](../../stubs/laravel/TuicProxyStartCommand.php)
- [stubs/thinkphp/TuicProxyStartCommand.php](../../stubs/thinkphp/TuicProxyStartCommand.php)
