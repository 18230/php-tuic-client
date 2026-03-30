# Packagist 发布说明

包名：

- `18230/php-tuic-client`

仓库：

- `https://github.com/18230/php-tuic-client`

## 首次提交

1. 打开 [Packagist Submit](https://packagist.org/packages/submit)
2. 提交仓库地址
3. 确认识别出的包名是 `18230/php-tuic-client`
4. 确认目标版本标签已经可见

## 自动同步

仓库里已经提供了两条自动同步路径：

1. GitHub Actions 工作流
   - [`.github/workflows/packagist-sync.yml`](../../.github/workflows/packagist-sync.yml)
2. 原生 GitHub webhook 脚本
   - [scripts/setup-packagist-github-hook.ps1](../../scripts/setup-packagist-github-hook.ps1)
   - [scripts/setup-packagist-github-hook.sh](../../scripts/setup-packagist-github-hook.sh)

推荐的 GitHub 配置：

- 仓库 secret `PACKAGIST_API_TOKEN`
- 仓库 variable `PACKAGIST_USERNAME`

## 打标签前

执行：

```bash
composer validate --strict
composer test
php bin/tuic-client doctor --config=examples/node.example.yaml
```
