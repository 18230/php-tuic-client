# 发布检查清单

## 本地验证

运行：

```bash
composer validate --strict
composer test
php bin/tuic-client doctor --config=examples/node.example.yaml
php bin/tuic-client run --config=examples/node.example.yaml --dry-run
php bin/tuic-client --help
```

## 第一次公开发布

- 确认 `composer.json` 里的最终包名
- 确认最终仓库地址
- 检查 changelog 和 README
- 以 `v0.1.0` 作为首个骨架版本发布
