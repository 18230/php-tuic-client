# php-tuic-client

[![Ubuntu CI](https://img.shields.io/github/actions/workflow/status/18230/php-tuic-client/ubuntu-ci.yml?branch=main&label=ubuntu%20ci)](https://github.com/18230/php-tuic-client/actions/workflows/ubuntu-ci.yml)
[![Release](https://img.shields.io/github/v/tag/18230/php-tuic-client?label=release)](https://github.com/18230/php-tuic-client/tags)
[![Packagist Version](https://img.shields.io/packagist/v/18230/php-tuic-client?label=packagist)](https://packagist.org/packages/18230/php-tuic-client)
[![Packagist Downloads](https://img.shields.io/packagist/dt/18230/php-tuic-client?label=downloads)](https://packagist.org/packages/18230/php-tuic-client)
[![License](https://img.shields.io/github/license/18230/php-tuic-client)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)](https://www.php.net/)

[English](README.md)

`php-tuic-client` 是一个纯 PHP 的 TUIC 客户端初始化骨架。它在项目结构、工具链、文档和发布方式上与 `php-shadowsocks-client` 对齐，但会明确保留 TUIC 传输层待后续阶段实现。

## 功能特性

- PHP 8.2+
- 支持 Windows、Linux、macOS
- Composer 包，带 CLI 启动入口
- 支持 YAML / JSON 的 TUIC 节点配置解析
- 提供 `doctor` 环境检查命令
- 提供 `run --dry-run` 运行前校验
- 提供跨平台本地验证脚本
- 提供 GitHub Actions Ubuntu CI 工作流

## 当前范围

已实现：

- TUIC 配置解析
- CLI 运行骨架
- Doctor 自检命令
- Dry-run 校验路径
- 双语文档和发布脚手架

暂未实现：

- QUIC / TUIC 传输运行时
- 本地 SOCKS5 前端
- UDP relay
- 框架集成帮助类

## 安装

从 Packagist 安装：

```bash
composer require 18230/php-tuic-client
```

如果你是在当前仓库本地开发：

```bash
composer install
```

## 快速开始

先验证示例配置：

```bash
php bin/tuic-client doctor --config=examples/node.example.yaml
```

解析配置但不真正启动传输层：

```bash
php bin/tuic-client run --config=examples/node.example.yaml --dry-run
```

显式参数启动示例：

```bash
php bin/tuic-client run \
  --server=tuic.example.com \
  --port=443 \
  --uuid=11111111-2222-3333-4444-555555555555 \
  --password=change-me \
  --local=127.0.0.1:1080 \
  --dry-run
```

查看全部参数：

```bash
php bin/tuic-client --help
```

## 配置格式

推荐的配置文件结构：

```yaml
node:
  server: tuic.example.com
  port: 443
  uuid: 11111111-2222-3333-4444-555555555555
  password: change-me
  sni: tuic.example.com
  alpn:
    - h3
  udp_relay_mode: native
  congestion_controller: bbr
  allow_insecure: false

runtime:
  local: 127.0.0.1:1080
  log_level: info
```

可直接参考 [examples/node.example.yaml](examples/node.example.yaml)。

## 说明

- 当前项目是 TUIC 的初始化骨架，不是完整可生产的传输实现。
- 目前 `run` 命令主要用于配置解析和后续运行时接线准备。
- 只有带 `--dry-run` 时，CLI 才会以成功状态退出。

## 文档导航

- [English framework guide](docs/en/frameworks.md)
- [中文框架接入说明](docs/zh-CN/frameworks.md)
- [English production guide](docs/en/production.md)
- [中文生产部署说明](docs/zh-CN/production.md)
- [English release checklist](docs/en/release.md)
- [English Packagist publishing guide](docs/en/packagist.md)
- [中文发布检查清单](docs/zh-CN/release.md)
- [中文 Packagist 发布说明](docs/zh-CN/packagist.md)
- [更新日志](CHANGELOG.md)

## 测试

```bash
composer test
php bin/tuic-client doctor --config=examples/node.example.yaml
php bin/tuic-client run --config=examples/node.example.yaml --dry-run
```
