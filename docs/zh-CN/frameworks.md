# 框架接入

`php-tuic-client` 目前还是初始化骨架，所以暂时还没有像 `php-shadowsocks-client` 那样提供框架专用的 service provider。

这个阶段建议这样接入：

1. 在业务配置里维护 TUIC 节点参数。
2. 用 `php bin/tuic-client doctor --config=...` 做配置校验。
3. 在 CI 或部署流程里执行 `php bin/tuic-client run --dry-run`。

等 TUIC 传输运行时真正落地后，再补齐和 `php-shadowsocks-client` 对齐的框架帮助类。
