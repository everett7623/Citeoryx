# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 项目概述

Citeoryx 是一个 WordPress 内容健康度监控、优化与 AI 可发现性引擎插件。

- **类型**: WordPress Plugin
- **最低要求**: PHP 8.0+, WordPress 6.6+
- **架构**: DDD 三层架构 (Domain / Application / Infrastructure)
- **前端**: React + WordPress Scripts + WordPress Components
- **异步处理**: Action Scheduler（后备 WP-Cron）
- **命名空间**: `Citeoryx\`
- **REST 命名空间**: `/wp-json/citeoryx/v1/`
- **数据库表前缀**: `{$wpdb->prefix}cx_`
- **文本域**: `citeoryx`

## 开发命令

### 前端开发

```bash
npm install              # 安装前端依赖
npm run start            # 开发模式，实时编译 assets/src/admin
npm run build            # 生产构建到 assets/build
npm run lint:js          # JavaScript 代码检查
npm run lint:css         # CSS 代码检查
npm test                 # 运行 Jest 单元测试
npm run test:e2e         # 运行 Playwright E2E 测试
```

### PHP 开发

```bash
composer install         # 安装 PHP 依赖（仅开发需要）
composer test            # 运行 PHPUnit 测试
composer phpcs           # PHP_CodeSniffer 代码规范检查
composer phpcbf          # 自动修复代码规范问题
composer phpcompat       # PHP 8.0+ 兼容性检查
```

### WordPress 本地环境

```bash
npm run env:start        # 启动 @wordpress/env 本地环境
npm run env:stop         # 停止本地环境
```

## 核心架构模式

### 1. REST API 优先设计

所有后台管理功能通过 REST API 实现，减少零散 AJAX：

- **Controllers** 位于 `src/Rest/Controllers/`
- **路由注册** 在 `src/Rest/Router.php`
- **权限校验** 使用 WordPress Nonce + 自定义 Capability (`citeoryx_*`)
- **Schema 验证** 在 Controller 中声明 `get_endpoint_args_for_item_schema()`

### 2. 异步任务模式

所有扫描和长时间操作都是后台异步任务，而非同步 REST 调用：

1. **REST 端点只创建任务**：`POST /scans` 立即返回 `scan_id`
2. **Action Scheduler 执行**：每批处理 50 条内容，完成后保存游标并继续排队
3. **前端轮询状态**：通过 `GET /scans/{id}` 查询 `queued` / `running` / `completed` / `failed`
4. **任务表记录**：`cx_scan_runs` 持久化进度，任务可恢复

队列实现位于 `src/Infrastructure/Queue/`，所有扫描都增量化、可恢复、可追踪。

### 3. Repository 模式

所有数据库操作通过 Repository 隔离，位于 `src/Domain/*/`：

- `ContentRepository` - 内容资产（`cx_content` 表）
- `IssueRepository` - 问题记录（`cx_issues` 表）
- `MetricsRepository` - 搜索指标（`cx_metrics` 表）
- `LinkRepository` - 链接关系（`cx_links` 表）

Repository 内部使用 `$wpdb->prepare()` 防止 SQL 注入，对外暴露类型安全接口。

### 4. Provider Adapter 模式

外部集成通过 Adapter 隔离，位于 `src/Integrations/`：

- `AiProviders/` - OpenAI / Anthropic / 兼容 API
- `Google/` - Google Search Console
- `Bing/` - Bing Webmaster Tools
- `SeoPlugins/` - Rank Math / Yoast / AIOSEO 适配器

### 5. 容器与依赖注入

`src/Core/Container.php` 实现简单的服务容器，按类名延迟实例化。注册位置：`src/Core/Plugin.php`。

## 兼容性约束

### 与其他 SEO 插件共存

Citeoryx 默认**只读取、不重复输出**前台 SEO 标签：

- 检测已安装的 SEO 插件：Rank Math / Yoast SEO / AIOSEO / SEOPress
- 通过适配器读取它们的 Title / Description / Focus Keyword / Schema
- **不输出** Title / Description / Sitemap / Schema 到前台
- 专注于内容健康度分析和优化建议

适配器位于 `src/Integrations/SeoPlugins/`，每个插件一个独立适配器类。

### 自动加载兼容

插件支持两种自动加载方式：

1. **Composer Autoloader**（开发推荐）：`vendor/autoload.php`
2. **内置 PSR-4 Autoloader**（生产部署）：`src/Core/Autoloader.php`

生产环境不需要 `composer install`，插件会自动检测并使用内置加载器。

## 安全约束

1. **API Key 存储优先级**：
   - 优先读取 `wp-config.php` 常量（如 `CITEORYX_OPENAI_API_KEY`）
   - 次选环境变量
   - 最后从数据库读取加密值（使用 Sodium/OpenSSL）

2. **外部 HTTP 请求**：
   - 使用 `wp_safe_remote_*()` 系列函数
   - 防止 SSRF：验证目标域名白名单

3. **数据库操作**：
   - 所有查询使用 `$wpdb->prepare()` Prepared Statements
   - Repository 层严格类型检查

## 测试结构

- `tests/Unit/` - PHPUnit 单元测试
- `tests/Unit/` - 同时通过 WordPress 官方测试套件覆盖集成行为
- `assets/src/admin/*.test.js` - Jest 单元测试
- `e2e/` - Playwright E2E 测试

运行单个 PHPUnit 测试：

```bash
vendor/bin/phpunit tests/Unit/ContentRepositoryTest.php
```

## 代码规范

- **PHP**: WordPress Coding Standards (WPCS) + PHPCompatibilityWP
- **JavaScript**: WordPress Scripts 默认 ESLint 配置
- **CSS**: WordPress Scripts 默认 Stylelint 配置
- **命名**: 
  - PHP 类/常量前缀：`CX_`
  - 函数/Hook/Option 前缀：`citeoryx_`
  - 数据库表前缀：`{$wpdb->prefix}cx_`

## 重要文档

- `ARCHITECTURE.md` - 详细架构设计与分层说明
- `API.md` - REST API 完整参考
- `DATABASE.md` - 数据库表结构设计
- `TESTING.md` - 测试环境配置指南
- `Citeoryx-插件开发文档-v2.0-正式版.md` - 产品需求与设计
