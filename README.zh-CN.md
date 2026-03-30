# php-tuic-client

[![CI](https://img.shields.io/github/actions/workflow/status/18230/php-tuic-client/ci.yml?branch=main&label=ci)](https://github.com/18230/php-tuic-client/actions/workflows/ci.yml)
[![Release](https://img.shields.io/github/v/tag/18230/php-tuic-client?label=release)](https://github.com/18230/php-tuic-client/tags)
[![Packagist Version](https://img.shields.io/packagist/v/18230/php-tuic-client?label=packagist)](https://packagist.org/packages/18230/php-tuic-client)
[![Packagist Downloads](https://img.shields.io/packagist/dt/18230/php-tuic-client?label=downloads)](https://packagist.org/packages/18230/php-tuic-client)
[![License](https://img.shields.io/github/license/18230/php-tuic-client)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2.4%2B-777bb4)](https://www.php.net/)

[English README](README.md)

`php-tuic-client` 是一个面向 PHP 8.2.4+ 的纯 PHP TUIC 客户端包，提供：

- 原生 TUIC over QUIC/TLS 连接能力
- 本地 HTTP / SOCKS5 代理运行时
- 可直接在业务代码里调用的 `TuicRequestClient`
- 可接入 Laravel / ThinkPHP 的 Composer 包结构

## 功能

- 提供适配 Windows、Linux、macOS 的项目脚手架与 CI
- 支持 Clash 风格 YAML / JSON 节点
- 支持 CLI 直接传内联节点配置
- 支持本地 HTTP 代理与 SOCKS5 代理
- 支持应用层直接发 `http://` / `https://` 请求
- 提供跨平台启动脚本
- 提供跨平台 GitHub Actions CI

运行时说明：

- 当前 `amphp/quic` 随包提供的是 Linux / macOS 的 `libquiche` 原生二进制。
- 所以这个仓库在 Windows 上目前可以完成配置解析、测试和 CLI 干跑，但真正的 TUIC 运行时仍然受上游绑定分发限制。

## 当前范围

已实现：

- TUIC v5 鉴权
- 原生 `http://` 请求
- 通过本地代理加 cURL 发 `https://` 请求
- 本地 HTTP 代理
- 本地 SOCKS5 代理
- `doctor` 与 `run` CLI
- Laravel / ThinkPHP 接入入口

暂未实现：

- UDP relay
- 连接池
- 多节点调度
- 面向超高并发的长时间 soak 验证

## 安装

Packagist 安装：

```bash
composer require 18230/php-tuic-client
```

仓库内本地开发：

```bash
composer install
```

## 运行要求

必须启用这些扩展：

- `ext-curl`
- `ext-ffi`
- `ext-json`
- `ext-openssl`
- `ext-sockets`

说明：

- 当前 QUIC 传输依赖是 [`amphp/quic`](https://github.com/amphp/quic)，它的上游包目前仍以 `dev-master` 形式发布。
- 本项目已经把它固定到一个已知 commit，方便重复安装和 CI。
- `doctor` 命令会直接检查 `ext-ffi` 等依赖是否齐全。

## 快速开始

直接用内联 YAML 启动本地代理：

```bash
php bin/tuic-client --node="{ name: 'SG 01', type: tuic, server: your-tuic-server.example.com, port: 443, uuid: 11111111-1111-1111-1111-111111111111, password: your-password, alpn: [h3], disable-sni: false, reduce-rtt: false, udp-relay-mode: native, congestion-controller: bbr, skip-cert-verify: false, sni: your-tuic-server.example.com }"
```

使用配置文件启动：

```bash
php bin/tuic-client --config=examples/node.example.yaml
```

启动前先自检：

```bash
php bin/tuic-client doctor --config=examples/node.example.yaml
```

发起一次请求：

```bash
php bin/tuic-request --config=examples/node.example.yaml --url=https://api.ipify.org?format=json
```

查看命令帮助：

```bash
php bin/tuic-client --help
```

## 配置方式

主 CLI 支持：

- `--node` 传内联 YAML / JSON
- `--config` 传 YAML / JSON 文件
- `--node-name` 在多节点配置里指定节点名

推荐配置文件结构：

```yaml
proxies:
  - name: "SG 01"
    type: tuic
    server: your-tuic-server.example.com
    port: 443
    uuid: 11111111-1111-1111-1111-111111111111
    password: replace-with-your-password
    alpn: [h3]
    disable-sni: false
    reduce-rtt: false
    udp-relay-mode: native
    congestion-controller: bbr
    skip-cert-verify: false
    sni: your-tuic-server.example.com
```

常用运行参数：

- `--http-listen=127.0.0.1:8080`
- `--socks-listen=127.0.0.1:1080`
- `--no-http`
- `--no-socks`
- `--node-name=SG 01`
- `--dry-run`

## 业务代码使用

### PHP 请求客户端

```php
<?php

use PhpTuic\Http\TuicRequestClient;

require __DIR__ . '/vendor/autoload.php';

$client = new TuicRequestClient(__DIR__ . '/config/tuic.yaml');

try {
    $response = $client->get('https://api.ipify.org?format=json');
    echo $response->statusCode . PHP_EOL;
    echo $response->body . PHP_EOL;
} finally {
    $client->stop();
}
```

### CLI curl

先启动代理：

```bash
php bin/tuic-client --config=examples/node.example.yaml
```

然后：

```bash
curl -x http://127.0.0.1:8080 https://api.ipify.org?format=json
curl --socks5-hostname 127.0.0.1:1080 https://api.ipify.org?format=json
```

## TLS / CA 说明

这里有两层证书校验：

1. TUIC 节点证书
   由节点里的 `skip-cert-verify` 控制
2. 目标 HTTPS 站点证书
   由 PHP cURL 的 CA 配置控制

所以就算 TUIC 隧道本身是通的，如果你的 PHP 没配置 CA bundle，业务层访问 `https://` 站点仍然可能失败。

模板：

- [resources/php/cacert.ini.example](resources/php/cacert.ini.example)

## 框架接入

- [English framework guide](docs/en/frameworks.md)
- [中文框架接入说明](docs/zh-CN/frameworks.md)

## 生产部署

- [English production guide](docs/en/production.md)
- [中文生产部署说明](docs/zh-CN/production.md)

建议的最低生产姿态：

- 监听地址优先保持在回环地址
- 部署前执行 `php bin/tuic-client doctor ...`
- 长驻进程所使用的那套 PHP 必须启用 `ext-ffi`
- 真正长期运行时优先 Linux / macOS，再配合 Supervisor、systemd、launchd 等守护方式
- 如果业务通过代理访问 `https://`，记得配置 CA 文件

## 发布说明

- [English release checklist](docs/en/release.md)
- [English Packagist publishing guide](docs/en/packagist.md)
- [中文发布检查清单](docs/zh-CN/release.md)
- [中文 Packagist 发布说明](docs/zh-CN/packagist.md)
- [Changelog](CHANGELOG.md)

## 示例

- [节点配置模板](examples/node.example.yaml)
- [本地环境变量模板](examples/tuic-client.env.example)
- [PHP 请求示例](examples/request_client.php)
- [HTTP 代理示例](examples/http_proxy_example.php)
- [SOCKS5 代理示例](examples/socks5_proxy_example.php)

## 测试

```bash
composer test
php bin/tuic-client doctor --config=examples/node.example.yaml
```
