# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 项目概述

Citeoryx 是一个 WordPress 内容健康度监控、优化与 AI 可发现性引擎插件。

- **类型**: WordPress Plugin（模块化单体，DDD 三层：Domain / Application / Infrastructure）
- **最低要求**: PHP 8.0+，WordPress 6.6+，MySQL 5.7+ / MariaDB 10.4+
- **前端**: React 18 + `@wordpress/scripts` + `@wordpress/components`
- **命名空间**: `Citeoryx\`（PSR-4，`src/` 下 89 个类）
- **REST 命名空间**: `citeoryx/v1`（`CITEORYX_REST_NAMESPACE`）
- **文本域**: `citeoryx`（UI 文案本身是简体中文，写在 `__()` 里）

## 开发命令

### PHP 测试（首次运行必读）

PHPUnit 跑在 **WordPress 官方测试套件**上，不是纯单元测试。`tests/bootstrap.php` 会加载真实 WordPress、执行 `SchemaManager::install()` 建表并清空 7 张 `cx_*` 表。没有安装测试环境时 `composer test` 直接报错退出。

```bash
# 仅开发需要；生产走内置 Autoloader
composer install

# 首次必须执行：需要可用的 MySQL + bash
composer test-install

# 运行全部 PHP 测试
composer test

# WPCS 检查，CI 阻断项
composer phpcs

# 自动修复规范问题
composer phpcbf

# PHP 8.0+ 兼容性检查
composer phpcompat
```

```bash
# 单个测试文件
vendor/bin/phpunit tests/Unit/ContentRepositoryTest.php
```

```bash
# 单个测试方法
vendor/bin/phpunit --filter test_method_name
```

### 前端

```bash
npm install

# 监听 assets/src/admin
npm run start

# 生产构建到 assets/build，Assets.php 读取 index.asset.php
npm run build

# Jest 全部测试
npm test

# Jest 单个测试文件
npm test -- assets/src/admin/apiError.test.js

npm run lint:js
npm run lint:css
```

### 质量门禁与 E2E

```bash
# 版本一致性 + 仓库卫生，CI 第一道关卡
npm run check:project

# wp-env（需要 Docker），WordPress 6.6 / PHP 8.0，端口 8889
npm run env:start

# Playwright，需先 npm run build 且 env 已启动
npm run test:e2e

npm run env:stop
```

CI（`.github/workflows/ci.yml`）跑三条流水线：前端检查、Chromium E2E、PHP 8.0–8.4 × WordPress 6.6/latest 矩阵。`phpcs` 与 `phpcompat` 只在 PHP 8.4 分片执行。

## 架构要点

### 启动链路

`citeoryx.php` → 选择 `vendor/autoload.php` 或 `src/Core/Autoloader.php`（生产环境无需 composer install）→ `src/Core/constants.php` → 在 `plugins_loaded` 上执行 `Plugin::run()`，依次注册 locale、缓存失效、Admin、REST、Queue、Privacy、迁移、CLI。

### Container 是手写 switch，不是自动装配

`src/Core/Container.php` 的 `build()` 是一个显式 `switch`。**新增任何服务都必须在这里补一个 `case`**，否则运行时抛 `InvalidArgumentException: Unknown service`。构造参数也在这里手工串联。

### REST 分层与约定

```
Router::register_routes()  →  Controller::register( $namespace )  →  BaseController
```

- 控制器方法叫 `register( string $namespace )`，**不是** `register_routes()`；新控制器要在 `src/Rest/Router.php` 里加一行。
- 本项目**不使用** `get_endpoint_args_for_item_schema()` / `get_item_schema()`。参数在 callback 内手动取 `get_json_params()` / `get_param()`，逐个 `sanitize_*` + 白名单校验后返回 `$this->error(...)`。改动时保持这一风格，不要引入半套 schema。
- 统一响应信封：成功 `{ success: true, data }`，失败 `{ success: false, message }`（`BaseController::success()` / `error()`）。
- 权限走 `permission_callback` + `Capabilities::*` 常量，不在 callback 内补检查。
- **作者级作用域**：`BaseController::content_author_scope()` 对没有 `VIEW_DASHBOARD` 的角色返回当前用户 ID，`can_access_content()` / `can_access_content_id()` 限制其只能访问自己的 post。新增涉及单条内容的端点必须接上这两个方法，否则 Author / Contributor 会越权。

### 异步任务

- **Action Scheduler 是可选依赖**。所有排队点都是 `function_exists( 'as_enqueue_async_action' )` 判断，不可用时回退 `wp_schedule_single_event( time() + 1, ... )`。新增任务照抄 `Scheduler` 里的 `schedule_*()` 私有方法结构。
- 周期事件（hourly / daily / weekly）在 `src/Core/Activator.php` 注册，回调在 `Plugin::register_queue()` 绑定。
- **批大小不统一**，按 IO 成本区分：全量/增量扫描 50、健康分重算 50、外链检查 5、搜索指标导入 20。改批大小前先算清楚单批的外部请求数。
- 游标语义有两种：扫描用 `config_json.offset`，健康分/外链/指标用**不可变 ID 游标** `after_id`（避免边扫边写导致漏项）。
- 扫描进度持久化在 `cx_scan_runs`，状态 `queued` / `running` / `completed` / `failed` / `cancelled`；`enqueue_scan()` 发现已有 running 任务会直接返回它，防止并发全量扫描。完成时触发 `citeoryx_scan_completed`。
- **AI 深度分析任务不落表**：`AiAnalysisTaskStore` 用 Transient 存 1 小时，并对 (content_id, user_id) 做活跃任务去重。

### 数据表与迁移

真实表名与 `src/Core/constants.php` 中的常量一一对应，**不要凭直觉写 `cx_content` / `cx_metrics`**：

| 常量 | 表名 |
|---|---|
| `CITEORYX_TABLE_CONTENT_ITEMS` | `{prefix}cx_content_items` |
| `CITEORYX_TABLE_METRICS_DAILY` | `{prefix}cx_metrics_daily` |
| `CITEORYX_TABLE_QUERY_PAGES` | `{prefix}cx_query_pages` |
| `CITEORYX_TABLE_ISSUES` | `{prefix}cx_issues` |
| `CITEORYX_TABLE_LINKS` | `{prefix}cx_links` |
| `CITEORYX_TABLE_SCAN_RUNS` | `{prefix}cx_scan_runs` |
| `CITEORYX_TABLE_AI_PROMPT_RUNS` | `{prefix}cx_ai_prompt_runs` |

`SchemaManager::install()` 用 `dbDelta()` 建全部表；`maybe_upgrade()` 在每次请求比对 `citeoryx_db_version` option。**改表结构必须同时 bump `src/Core/constants.php` 里的 `CITEORYX_DB_VERSION`**（形如 `2026072204`），否则线上不会迁移。

Repository（`src/Domain/*/`）：`ContentRepository`、`IssueRepository`、`LinkRepository`、`MetricsRepository`、`ScanRunRepository`、`OpportunityRepository`、`CalendarRepository`。所有 `$wpdb` 调用只应出现在 Repository 里（`Scheduler::update_config()` 是历史例外）。

### REST 聚合缓存

`RestResponseCache` 用「世代号 option + 300s Transient」实现：`invalidate()` 只标脏，真正换世代号在 `shutdown` 时一次性完成，所以同一请求里多次写入只失效一次。`/dashboard` 和 `/reports/summary` 走 `remember()`。**任何会改变聚合结果的写路径（内容、问题、扫描、指标、SEO 插件启停）都要调用 `RestResponseCache::invalidate()`**，否则前端会看到最多 5 分钟的陈旧数据。

### 外部集成

- `SeoPluginAdapterFactory` 按 `rank-math → yoast → aioseo → seopress` 顺序探测，全不命中返回 `NullAdapter`。
- `AiProviderFactory::PROVIDERS`：`openai` / `anthropic` / `openai_compatible` / `openai_responses` / `anthropic_compatible` / `deepseek` / `none`。禁用时返回 `NullAiProvider`。新增 provider 要同时补 `PROVIDERS`、`make_selected()` 的 case、`KEY_NAME`、`DEFAULT_MODEL`。
- Google 与 Bing **同在** `src/Integrations/SearchConsole/`（不是分开的 `Google/` `Bing/`）。
- 外部 HTTP 一律走 `Infrastructure/Http/HttpClient`：内置 SSRF 校验、`wp_safe_remote_*`、30s 超时、5MB 响应上限，返回 `{success, body, code, error}` 数组而非 `WP_Error`。

### 密钥读取

`KeyStore::get( $name )` 的查找顺序是 **`CITEORYX_` + strtoupper(name) 常量 → 同名环境变量 → `citeoryx_key_{$name}` option（Sodium，回退 OpenSSL）**。所以 `openai_api_key` 对应 `define( 'CITEORYX_OPENAI_API_KEY', ... )`。实际键名：`openai_api_key`、`anthropic_api_key`、`openai_compatible_api_key`、`anthropic_compatible_api_key`、`deepseek_api_key`、`bing_webmaster_api_key`、`gsc_tokens`、`gsc_client_secret`。

### 后台前端

单挂载点 `#citeoryx-admin-app` + `TabPanel` 的 hash 路由；`Menu.php` 的子菜单 slug（`citeoryx-dashboard#/inventory` 等）必须与 `App.js` 的 tab name 对齐。`Assets.php` 通过 `wp_localize_script` 注入 `window.citeoryxAdmin`，其中 `user.can*` 布尔值决定 tab 可见性——加权限时要同时改 `Capabilities`、`Assets.php`、`Menu.php` 和 `App.js`。

API 错误一律经 `getApiErrorMessage()` 过滤：服务器/代理返回 HTML（critical error 页面）时降级为可读文案，不把 HTML 甩到界面上。

## 命名规范

| 项目 | 实际规范 | 示例 |
|---|---|---|
| PHP 类 | 纯 PSR-4，**无前缀** | `Citeoryx\Domain\Content\ContentRepository` |
| PHP 全局常量 | `CITEORYX_` | `CITEORYX_DB_VERSION`、`CITEORYX_TABLE_ISSUES` |
| `CX_` 前缀 | **只用于 issue code 字符串**，不是类/常量前缀 | `CX_INDEX_NOINDEX`、`CX_CONTENT_STALE`、`CX_LINK_ORPHANED`、`CX_AEO_EVIDENCE_MISSING`、`CX_PLAN_TOPIC_GAP` |
| Hook / Option | `citeoryx_` | `citeoryx_scan_completed`、`citeoryx_settings` |
| Capability | `citeoryx_` | `citeoryx_run_scans`（代码里用 `Capabilities::RUN_SCANS` 常量引用） |
| 数据库表 | `{$wpdb->prefix}cx_` | `wp_cx_content_items` |

Issue code 是跨层共享词表：`IssueEngine` 产生 → `ContentStatusClassifier` 据此判定内容状态 → `Optimizer` 据此给建议。**新增 issue code 必须三处同步**，否则问题能被发现但不影响状态、也给不出优化建议。

权限共 9 个（`src/Core/Capabilities.php`）。改角色映射时必须 bump `Capabilities::MAPPING_VERSION`，`maybe_upgrade()` 才会重新分配。

## 兼容性约束：只读取，不输出

Citeoryx 与 Rank Math / Yoast / AIOSEO / SEOPress 共存的前提是**不往前台输出任何 SEO 标签**——不输出 Title、Description、Sitemap、Schema。适配器只读取它们的数据用于分析。这是产品定位而非实现细节，不要"顺手"加前台输出。

## 发布版本一致性

`npm run check:project` 强制以下 8 处版本号完全一致，任一不符 CI 直接失败：`citeoryx.php` 插件头 `Version:`、`CITEORYX_VERSION` 常量、`readme.txt` 的 `Stable tag:`、`readme.txt` changelog 首个版本、`CHANGELOG.md` 首个 `## x.y.z`、`dev-progress.md` 的 `> 当前发布版本：x.y.z`、`package.json`、`package-lock.json`（根字段与 `packages[""]` 两处）。同时禁止把 `coverage/`、`output/` 下的生成物提交进 git。

## 文档

- `ARCHITECTURE.md` — 分层与核心流程
- `API.md` — REST API 完整参考
- `DATABASE.md` — 表结构
- `TESTING.md` — 测试环境与覆盖清单
- `dev-progress.md` — 已完成 / 进行中功能的权威清单
- `references/case-*.md` — 线上问题复盘笔记（症状 / 原始证据 / 根因 / 修复 / 验证 / 教训）。排查同类问题前先翻这里；定位到新的结构性缺陷后按同样格式补一篇。
- `AI-GUIDELINES.md`、`.cursorrules`、`.github/copilot-instructions.md` — 其他 AI 助手的同源约定。注意这三份里的命名规范表和表名尚未更新（仍写着 `CX_` 类前缀、`cx_content`、`cx_ai_analysis_tasks`），以本文件为准；改动架构结论时一并同步。
