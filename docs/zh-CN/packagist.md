# Packagist 发布说明

## 推荐的首个版本

- 仓库地址：`https://github.com/18230/php-tuic-client`
- Composer 包名：`18230/php-tuic-client`
- 首个建议提交到 Packagist 的版本标签：`v0.1.0`

## 提交方式

1. 打开 [Packagist Submit](https://packagist.org/packages/submit)。
2. 填入 `https://github.com/18230/php-tuic-client`。
3. 等 `v0.1.0` tag 就绪后提交。

## 自动更新

这个项目沿用了和 `php-shadowsocks-client` 一样的 GitHub 自动同步方案。

- 工作流：`.github/workflows/packagist-sync.yml`
- 必需 secret：`PACKAGIST_API_TOKEN`
- 建议 variable：`PACKAGIST_USERNAME`
  当前账号建议设置为 `aiqq363927173`。
