# 发布检查清单

## 发布前

- 确认包名是 `18230/php-tuic-client`
- 确认 `composer.json`、`README.md`、`README.zh-CN.md` 已同步
- 确认没有提交真实 TUIC 凭据
- 确认 `examples/node.user.yaml` 只有脱敏示例
- 确认 `main` 分支上的 `.github/workflows/native-build.yml` 已通过
- 确认 `resources/native/manifest.json` 已被 native workflow 刷新
- 运行 `composer validate --strict`
- 运行 `composer test`
- 运行 `php bin/tuic-client doctor --config=examples/node.example.yaml`

## 原生库发布流程

Composer 的 dist 包只会包含 tag 对应仓库快照里的文件，所以只上传 GitHub Release 附件还不够。

正确流程是：

1. 先把代码推到 `main`
2. 等 `Native Binaries` 工作流完成，并自动把 `resources/native/` 刷新提交回仓库
3. 本地拉取这次 bot 提交
4. 对这次已经包含 `resources/native/*` 的提交打 tag

## 打标签

```bash
git tag v0.x.y
git push origin main --tags
```

## 打标签后

- 检查 GitHub Actions CI 是否通过
- 检查 tag 对应的 `Native Binaries` 是否通过
- 检查 Packagist 自动同步是否成功
- 在一个干净项目里验证 `composer require 18230/php-tuic-client` 后无需额外下载即可命中包内自带的原生库
