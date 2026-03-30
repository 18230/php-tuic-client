# 生产部署说明

## 推荐拓扑

把 `php-tuic-client` 当成一个独立的本地 SOCKS5 进程来运行。

```text
应用 / curl / 抓取程序 -> 127.0.0.1:1080 -> php-tuic-client -> TUIC 节点 -> 目标站点
```

## 平台状态

- Linux：推荐生产平台
- macOS：适合开发机或轻量长驻运行
- Windows：支持，官方 release 标签会内置 x64 版 `quiche.dll`

## 运行时要求

- PHP `8.2.4+`
- `ext-ffi`
- `ext-json`
- `ext-openssl`
- `ext-sockets`
- `libquiche` / `quiche.dll`

官方 tag 版本会把下面这些预编译产物直接带进仓库内容，因此也会进入 Composer 的 dist 包：

- `resources/native/windows-x64/quiche.dll`
- `resources/native/linux-x64/libquiche.so`
- `resources/native/macos-x64/libquiche.dylib`

如果你的目标机器不在这些 x64 triplet 里，就需要自行构建，并放到 `resources/native/<platform>-<arch>/`，或者通过 `QUICHE_LIB` 指到绝对路径。

## 启动方式

Shell：

```bash
export PHP_BIN=/usr/bin/php
export TUIC_CONFIG=/opt/php-tuic-client/config/tuic.yaml
export TUIC_SOCKS_LISTEN=127.0.0.1:1080
export QUICHE_LIB=/opt/php-tuic-client/resources/native/linux-x64/libquiche.so

./scripts/start-tuic-client.sh
```

PowerShell：

```powershell
$env:PHP_BIN = 'E:\phpEnv\php\php-8.4\php.exe'
$env:TUIC_CONFIG = 'E:\proxy\tuic.yaml'
$env:TUIC_SOCKS_LISTEN = '127.0.0.1:1080'
$env:TUIC_ALLOW_IP = '127.0.0.1'
$env:TUIC_MAX_CONNECTIONS = '2048'
$env:TUIC_CONNECT_TIMEOUT = '12'
$env:TUIC_IDLE_TIMEOUT = '600'
$env:TUIC_HANDSHAKE_TIMEOUT = '20'
$env:TUIC_STATUS_FILE = 'E:\proxy\runtime\status.json'
$env:TUIC_LOG_FILE = 'E:\proxy\runtime\proxy.log'
$env:TUIC_PID_FILE = 'E:\proxy\runtime\proxy.pid'
$env:QUICHE_LIB = 'E:\proxy\resources\native\windows-x64\quiche.dll'

.\scripts\start-tuic-client.ps1
```

## 运行时文件

生产环境推荐至少保留：

- `TUIC_LOG_FILE`：逐行日志
- `TUIC_STATUS_FILE`：JSON 状态快照
- `TUIC_PID_FILE`：当前进程号

`status-file` 里常看的字段有：

- `listen`
- `node.server`
- `node.port`
- `stats.accepted_connections_total`
- `stats.closed_connections_total`
- `stats.handshake_timeouts_total`
- `stats.tuic_auth_success_total`
- `stats.tuic_auth_failure_total`

## 原生库构建

只有在使用 `dev-main`、本地开发，或者目标架构不在官方内置范围时，才需要手动构建。正式 tag 版本已经自带 x64 预编译库。

Windows：

```powershell
.\scripts\build-quiche.ps1
```

Linux / macOS：

```bash
./scripts/build-quiche.sh
```

## 运维建议

- 监听地址优先只放在回环地址。
- 建议明确配置 `allow-ip`，不要把本地代理暴露给任意来源。
- 建议始终打开 `log-file` 和 `status-file`，方便排障和监控。
- 默认 `idle-timeout` 已提高到 300 秒；如果你的业务会有更长时间的空闲长连接，可以继续上调。
- 部署时先跑 `php bin/tuic-client doctor ...`。
- `skip-cert-verify: true` 要当成明确的安全取舍。
- 建议配合 `systemd`、`supervisord`、`launchd` 等守护进程使用。
- 正式 tag 已经把 x64 原生库跟着 Composer 包一起发了。如果你是自定义架构，请保持同样的 `resources/native/` 目录结构一起发布。

## 部署检查清单

1. 通过正式 tag 安装：`composer require 18230/php-tuic-client`
2. 执行 `php bin/tuic-client doctor --config=/path/to/tuic.yaml`
3. 启动本地 SOCKS5 运行时，并配置 `log-file`、`status-file`、`pid-file`
4. 用 `curl --proxy socks5h://127.0.0.1:1080 https://api.ipify.org?format=json` 做一次探活
5. 把进程交给 `systemd`、`supervisord` 或 `launchd`

## 兼容性说明

- 当前生产主路径是独立的 SOCKS5 长驻进程。
- `TuicRequestClient` 仍然存在，但只是给框架项目用的便捷层，底层还是同一个本地 SOCKS5 进程。
- 当前运行时明确不提供 HTTP 代理模式。
