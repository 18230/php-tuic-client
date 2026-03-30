# 发布检查清单

## 发布前

- 确认包名是 `18230/php-tuic-client`
- 确认 `composer.json`、`README.md`、`README.zh-CN.md` 已同步
- 确认没有提交真实 TUIC 凭据
- 确认 `examples/node.user.yaml` 只有脱敏示例
- 运行 `composer validate --strict`
- 运行 `composer test`
- 运行 `php bin/tuic-client doctor --config=examples/node.example.yaml`

## 打标签

```bash
git tag v0.x.y
git push origin main --tags
```

## 打标签后

- 检查 GitHub Actions CI 是否通过
- 检查 Packagist 自动同步是否成功
- 在一个干净项目里验证 `composer require 18230/php-tuic-client`
