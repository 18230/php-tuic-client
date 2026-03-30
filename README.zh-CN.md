# php-tuic-client

[![CI](https://img.shields.io/github/actions/workflow/status/18230/php-tuic-client/ci.yml?branch=main&label=ci)](https://github.com/18230/php-tuic-client/actions/workflows/ci.yml)
[![Native](https://img.shields.io/github/actions/workflow/status/18230/php-tuic-client/native-build.yml?branch=main&label=native)](https://github.com/18230/php-tuic-client/actions/workflows/native-build.yml)
[![Release](https://img.shields.io/github/v/tag/18230/php-tuic-client?label=release)](https://github.com/18230/php-tuic-client/tags)
[![Packagist Version](https://img.shields.io/packagist/v/18230/php-tuic-client?label=packagist)](https://packagist.org/packages/18230/php-tuic-client)
[![License](https://img.shields.io/github/license/18230/php-tuic-client)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2.4%2B-777bb4)](https://www.php.net/)

[English README](README.md)

`php-tuic-client` 是一个纯 PHP 的 TUIC v5 客户端，目标很明确：启动一个本地 `socks5://127.0.0.1:1080` 风格的代理服务，然后把流量通过 TUIC 节点转发出去。

底层 QUIC 传输由 `cloudflare/quiche` 提供，PHP 通过 FFI 加载 `libquiche`，本地监听、节点解析、CLI 和运行控制仍然由 PHP 完成。

## 当前范围

- TUIC v5 鉴权
- 本地 SOCKS5 服务
- Clash 风格 YAML / JSON 节点解析
- Windows、Linux、macOS 三端项目支持
- 跨平台启动脚本
- `doctor` 与 `run` CLI 工作流
- 面向框架项目的兼容请求辅助层，底层仍然复用同一条本地 SOCKS5 运行链路

当前不包含：

- UDP relay
- HTTP 代理运行时
- 多节点调度

## 运行要求

- PHP `8.2.4+`
- `ext-ffi`
- `ext-json`
- `ext-openssl`
- `ext-sockets`
- 可加载的 `libquiche` 动态库

## 安装

```bash
composer require 18230/php-tuic-client
```

官方 release 标签会把预编译好的 `libquiche` 一起打进仓库内容，目前内置：

- `windows-x64`
- `linux-x64`
- `macos-x64`

这意味着针对正式标签执行 `composer require` 后，通常可以直接命中包内自带的动态库。如果你安装的是 `dev-main`，或者目标机器不是这些架构，就需要自己构建并通过 `QUICHE_LIB` 或 `--quiche-lib` 指定。

## 构建 libquiche

正式 release 已经内置 x64 预编译库。只有在本地开发、使用 `dev-main`、或者目标架构不在内置范围时，才需要手动构建。

你需要一个带 `ffi` feature 的共享库构建。

Windows：

```powershell
.\scripts\build-quiche.ps1
```

Linux / macOS：

```bash
./scripts/build-quiche.sh
```

如果 `libquiche` 不在默认路径，也可以通过 `QUICHE_LIB` 或 `--quiche-lib` 指定。

## 快速开始

先做自检：

```bash
php bin/tuic-client doctor --config=examples/node.example.yaml
```

`doctor` 会输出当前识别到的本机 triplet 以及动态库查找顺序。官方 release 安装后，正常情况下会直接命中 `resources/native/<platform>-<arch>/` 下的文件。

直接用内联 YAML 启动：

```bash
php bin/tuic-client \
  --node="{ name: 'SG 01', type: tuic, server: your-tuic-server.example.com, port: 443, uuid: 11111111-1111-1111-1111-111111111111, password: your-password, alpn: [h3], disable-sni: false, reduce-rtt: false, udp-relay-mode: native, congestion-controller: bbr, skip-cert-verify: false, sni: your-tuic-server.example.com }" \
  --listen=127.0.0.1:1080
```

或者从配置文件启动：

```bash
php bin/tuic-client --config=examples/node.example.yaml --listen=127.0.0.1:1080
```

启动后你的业务或工具直接走：

```text
socks5://127.0.0.1:1080
```

命令行工具建议优先使用 `socks5h://`，这样域名解析也会走代理侧：

```bash
curl --proxy socks5h://127.0.0.1:1080 https://api.ipify.org?format=json
```

示例：

```php
<?php

$ch = curl_init('https://api.ipify.org?format=json');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_PROXY => '127.0.0.1:1080',
    CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME,
]);

echo curl_exec($ch);
curl_close($ch);
```

## 节点字段说明

必填字段：

- `name`：本地显示名称
- `type`：必须是 `tuic`
- `server`：远端 TUIC 服务地址
- `port`：远端端口
- `uuid`：TUIC 用户 UUID
- `password`：TUIC 密码 / token
- `alpn`：一般写 `[h3]`
- `sni`：TLS 的服务名

支持但当前仍属附加字段：

- `skip-cert-verify`：默认 `false`
- `disable-sni`：默认 `false`
- `reduce-rtt`：默认 `false`
- `udp-relay-mode`：会解析，但当前还没有实现 UDP relay
- `congestion-controller`：通常使用 `bbr`

最小示例：

```yaml
proxies:
  - name: demo-tuic
    type: tuic
    server: example.com
    port: 443
    uuid: 00000000-0000-4000-8000-000000000000
    password: replace-with-your-password
    alpn: [h3]
    sni: example.com
```

## CLI 参数

主要参数：

- `--node`
- `--config`
- `--node-name`
- `--listen`
- `--allow-ip`
- `--max-connections`
- `--connect-timeout`
- `--idle-timeout`
- `--handshake-timeout`
- `--status-file`
- `--status-interval`
- `--log-file`
- `--pid-file`
- `--quiche-lib`
- `--dry-run`

生产风格示例：

```bash
php bin/tuic-client \
  --config=/opt/php-tuic-client/config/tuic.yaml \
  --listen=127.0.0.1:1080 \
  --allow-ip=127.0.0.1 \
  --max-connections=2048 \
  --connect-timeout=12 \
  --idle-timeout=600 \
  --handshake-timeout=20 \
  --status-file=/opt/php-tuic-client/runtime/status.json \
  --log-file=/opt/php-tuic-client/runtime/proxy.log \
  --pid-file=/opt/php-tuic-client/runtime/proxy.pid
```

## 节点配置示例

```yaml
proxies:
  - name: demo-tuic
    type: tuic
    server: example.com
    port: 443
    uuid: 00000000-0000-4000-8000-000000000000
    password: replace-with-your-password
    alpn: [h3]
    disable-sni: false
    reduce-rtt: false
    udp-relay-mode: native
    congestion-controller: bbr
    skip-cert-verify: false
    sni: example.com
```

## 运行时输出

生产环境里最建议打开这三类文件：

- `log-file`：人类可读的运行日志
- `status-file`：JSON 状态快照
- `pid-file`：当前进程号

`status-file` 里会有这些关键统计项：

- `accepted_connections_total`
- `closed_connections_total`
- `handshake_timeouts_total`
- `tuic_auth_success_total`
- `tuic_auth_failure_total`

## 生产说明

- 监听地址优先保持在回环地址。
- `doctor` 要用和长驻进程完全一致的 PHP 二进制去跑。
- 官方 release 标签已经把 x64 原生库带进 Composer 包。
- 生产参数尽量保持少而明确：`allow-ip`、`max-connections`、`connect-timeout`、`idle-timeout`、`handshake-timeout`、`status-file`、`log-file`、`pid-file`。
- 如果目标机器是其他架构，部署时请把自建 `libquiche` 跟着同一份产物一起发布，并放到匹配的 triplet 目录，或者设置 `QUICHE_LIB`。
- 长时间生产运行优先 Linux。

## 常见排查

- `doctor` 在启动前失败：
  请使用和实际长驻进程完全一致的 PHP 二进制执行 `php bin/tuic-client doctor ...`
- `ext-ffi` 没启用：
  在 `php.ini` 打开 FFI，然后重新执行 `doctor`
- 找不到 `libquiche`：
  优先使用正式 tag 的安装包，或者通过 `QUICHE_LIB` / `--quiche-lib` 指定绝对路径
- 进程能起来，但本地请求失败：
  先看 `log-file` 和 `status-file`。如果 `tuic_auth_failure_total` 不为 0，优先检查节点凭证、SNI 和 TLS 配置
- 三端运行不一致：
  先确认当前平台和架构是否命中了匹配的原生库

## 框架辅助层

包里仍然保留了 `TuicRequestClient` 以及 Laravel / ThinkPHP 的服务注册能力，但它们只是建立在“本地 SOCKS5 + cURL”之上的辅助层；如果你只关心协议级本地代理，可以完全忽略这些辅助类。

更多说明：

- [English production guide](docs/en/production.md)
- [中文生产部署说明](docs/zh-CN/production.md)

## 测试

```bash
composer test
php bin/tuic-client --config=examples/node.example.yaml --dry-run
php bin/tuic-client doctor --config=examples/node.example.yaml
```

发布流程还会额外跑一轮真实节点联调，覆盖 `ubuntu-22.04`、`windows-latest`、`macos-15-intel`，并且直接使用包内自带的 `libquiche` 与本地 `socks5://127.0.0.1:11084` 监听完成端到端验证。
