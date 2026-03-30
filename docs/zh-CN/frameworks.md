# 框架接入

## 安装

```bash
composer require 18230/php-tuic-client
```

## Laravel

包内已经提供服务提供者：

- `PhpTuic\Integration\Laravel\TuicServiceProvider`

如果开启了 Laravel 的自动发现，通常不需要手动注册。

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

## 框架项目里启动本地代理

如果你只是想在框架项目里启动本地代理，直接执行：

```bash
vendor/bin/tuic-client --config=config/tuic.yaml
```

或者使用底层入口：

```bash
vendor/bin/tuic-proxy-server --config=config/tuic.yaml
```

包里也保留了命令桩文件：

- [stubs/laravel/TuicProxyStartCommand.php](../../stubs/laravel/TuicProxyStartCommand.php)
- [stubs/thinkphp/TuicProxyStartCommand.php](../../stubs/thinkphp/TuicProxyStartCommand.php)
