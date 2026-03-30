# 生产部署说明

## 推荐拓扑

把 `php-tuic-client` 作为独立本地代理进程运行，然后让应用复用本地代理地址。

```text
应用 / curl / 浏览器 -> 127.0.0.1:8080 或 127.0.0.1:1080 -> php-tuic-client -> TUIC 节点 -> 目标站点
```

## 平台现状

- Linux：当前推荐运行平台
- macOS：当前推荐运行平台
- Windows：包的脚手架、配置解析、测试和干跑可用，但上游 `amphp/quic` 当前没有随包提供 Windows 的 `libquiche` 二进制，所以运行时还不可用

## 启动脚本

包内提供了三套跨平台脚本：

- Windows PowerShell: [scripts/start-tuic-client.ps1](../../scripts/start-tuic-client.ps1)
- Windows CMD: [scripts/start-tuic-client.bat](../../scripts/start-tuic-client.bat)
- Linux/macOS Shell: [scripts/start-tuic-client.sh](../../scripts/start-tuic-client.sh)

支持的环境变量：

- `PHP_BIN`
- `TUIC_CONFIG`
- `TUIC_NODE`
- `TUIC_NODE_NAME`
- `TUIC_HTTP_LISTEN`
- `TUIC_SOCKS_LISTEN`
- `TUIC_NO_HTTP`
- `TUIC_NO_SOCKS`

模板：

- [examples/tuic-client.env.example](../../examples/tuic-client.env.example)

## Linux / macOS

```bash
export PHP_BIN=/usr/bin/php
export TUIC_CONFIG=/opt/php-tuic-client/config/tuic.yaml
export TUIC_HTTP_LISTEN=127.0.0.1:8080
export TUIC_SOCKS_LISTEN=127.0.0.1:1080

./scripts/start-tuic-client.sh
```

模板文件：

- systemd: [resources/systemd/php-tuic-client.service](../../resources/systemd/php-tuic-client.service)
- Supervisor: [resources/supervisor/php-tuic-client.conf](../../resources/supervisor/php-tuic-client.conf)
- launchd: [resources/launchd/io.github.18230.php-tuic-client.plist](../../resources/launchd/io.github.18230.php-tuic-client.plist)

## Windows

```powershell
$env:PHP_BIN = 'E:\phpEnv\php\php-8.2\php.exe'
$env:TUIC_CONFIG = 'E:\proxy\tuic.yaml'
$env:TUIC_HTTP_LISTEN = '127.0.0.1:8080'
$env:TUIC_SOCKS_LISTEN = '127.0.0.1:1080'

.\scripts\start-tuic-client.ps1
```

当前只有在你自己提供可用的 Windows `libquiche` 构建时，这条路径才可能真正跑起来；默认随包依赖并不包含它。

## FFI 要求

长驻代理进程所使用的那套 PHP 必须启用 `ext-ffi`。

常见 `php.ini` 写法：

```ini
extension=ffi
```

## TLS / CA 说明

有两层证书：

1. TUIC 节点证书
   由节点配置里的 `skip-cert-verify` 控制
2. 目标 HTTPS 站点证书
   由 PHP cURL 的 CA 配置控制

CA 模板：

- [resources/php/cacert.ini.example](../../resources/php/cacert.ini.example)

## 运维建议

- 非必要不要把监听地址暴露到回环地址之外
- 部署时先执行 `php bin/tuic-client doctor --config=/path/to/tuic.yaml`
- 长时间运行优先 Linux
- 用进程守护工具管理重启和日志
- 如果配置了 `skip-cert-verify: true`，要明确记录这个安全取舍
