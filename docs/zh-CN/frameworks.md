# 框架接入

## 安装

```bash
composer require 18230/php-tuic-client
```

## Laravel

包内已经提供服务提供者：

- `PhpTuic\Integration\Laravel\TuicServiceProvider`

如果开启了 Laravel 的自动发现，通常不需要手动注册。

当前主生产路径仍然是独立的本地 SOCKS5 长驻进程。框架里的这些类只是建立在同一条运行时之上的 cURL 辅助层，不是另一套协议实现。

配置模板：

- [config/laravel/tuic-client.php](../../config/laravel/tuic-client.php)

常见用法：

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

如果你只想拿到本地代理地址，也可以直接：

```php
use PhpTuic\Http\TuicRequestClient;

$client = app(TuicRequestClient::class);
$socksProxy = $client->getSocksProxyUrl();
```

发布配置：

```bash
php artisan vendor:publish --tag=tuic-client-config
```

## ThinkPHP

注册服务：

```php
return [
    \PhpTuic\Integration\ThinkPHP\TuicService::class,
];
```

配置模板：

- [config/thinkphp/tuic_client.php](../../config/thinkphp/tuic_client.php)

常见用法：

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

同样也可以直接通过 `getSocksProxyUrl()` 拿到本地 SOCKS5 地址。

## 框架项目里启动本地代理

如果你只是想在框架项目里启动本地代理，直接执行：

```bash
vendor/bin/tuic-client --config=config/tuic.yaml
```

包里也保留了命令桩文件，并且已经对齐到当前的 SOCKS5 运行时：

- [stubs/laravel/TuicProxyStartCommand.php](../../stubs/laravel/TuicProxyStartCommand.php)
- [stubs/thinkphp/TuicProxyStartCommand.php](../../stubs/thinkphp/TuicProxyStartCommand.php)
