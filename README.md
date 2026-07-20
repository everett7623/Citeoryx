# Citeoryx

> WordPress 内容健康度持续监控、优化与 AI 可发现性引擎

Citeoryx 帮助站点持续回答：

1. 站内目前有哪些内容资产？
2. 哪些页面正在增长、停滞、衰退或互相竞争？
3. 哪些问题最值得先修复？
4. 页面应该增加、删除、合并、更新还是重新定位？
5. 内容是否容易被搜索引擎、生成式搜索和 AI 问答系统发现、理解和引用？
6. 优化完成后，是否真正带来了展示、点击、排名和转化改善？

## 核心定位

- **Content Inventory**：内容资产库
- **Content Health Engine**：内容健康度引擎
- **Optimization Workflow**：内容优化工作流
- **Opportunity Intelligence**：增长机会识别
- **AI Discoverability Readiness**：AI 可发现性准备度
- **Content Lifecycle Management**：内容全生命周期管理

## 兼容性

与 Rank Math、Yoast SEO、AIOSEO、SEOPress 等基础 SEO 插件兼容。Citeoryx 默认只读取其公开数据并提供分析和建议，不重复输出前台 Title、Description、Sitemap、Schema 等标签。

## 最低环境

- PHP >= 8.0
- WordPress >= 6.6
- MySQL >= 5.7 或 MariaDB >= 10.4

## 安装

1. 将 `citeoryx` 目录复制到 `wp-content/plugins/`。
2. 在 WordPress 后台启用插件。
3. 按安装向导完成站点画像和初始扫描。

## 开发

```bash
# PHP 依赖（仅开发需要，生产运行可省略）
composer install

# 前端依赖与构建
npm install
npm run build

# 开发模式
npm run start
```

> 注意：如果环境未安装 Composer，插件会启用内置 PSR-4 自动加载器，可直接在 WordPress 中启用；只有运行 PHPUnit / PHPCS 等开发工具时才需要 Composer 安装依赖。

## 文档

- [产品设计与需求](Citeoryx-插件开发文档-v2.0-正式版.md)
- [架构设计](ARCHITECTURE.md)
- [REST API 参考](API.md)
- [数据库设计](DATABASE.md)
- [贡献指南](CONTRIBUTING.md)
- [测试指南](TESTING.md)

## License

GPL-2.0-or-later
