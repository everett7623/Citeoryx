# AI 开发助手通用指南

本文档为所有 AI 开发助手提供项目的最小共享上下文。

> **唯一事实源是 `CLAUDE.md`。** 架构细节、命令、边界约定以那份为准，本文件只保留跨工具共用的要点。
> 历史上本文件、`.cursorrules`、`.github/copilot-instructions.md` 各维护一份完整副本，结果是四份文档同时漂移出与代码不符的结论。不要再把完整内容复制到这里。

## 项目信息

**Citeoryx** — WordPress 内容健康度持续监控、优化与 AI 可发现性引擎

| 项 | 值 |
|---|---|
| 类型 | WordPress 插件（模块化单体，DDD 三层） |
| PHP / WordPress | 8.0+ / 6.6+ |
| 前端 | React 18 + `@wordpress/scripts` + `@wordpress/components` |
| 命名空间 | `Citeoryx\`（PSR-4） |
| REST | `/wp-json/citeoryx/v1/*` |
| 文本域 | `citeoryx`（UI 文案为简体中文，写在 `__()` 里） |

## 五条核心约束

1. **REST API 优先** — 后台功能走 `Controller → Application Service → Repository → DB`，不写零散 AJAX。
2. **长任务必须异步** — REST 端点只创建任务并立即返回；Action Scheduler 可用时走它，否则回退 WP-Cron 单次事件。
3. **数据库只在 Repository 里访问** — `src/Domain/*/`，一律 `$wpdb->prepare()`。
4. **外部服务通过 Adapter 隔离** — `src/Integrations/`，统一接口 + Factory 注册 + 降级实现（`Null*`）。
5. **SEO 插件只读取、不输出** — 读 Rank Math / Yoast / AIOSEO / SEOPress 的数据用于分析，**绝不**向前台输出 Title、Description、Sitemap、Schema。

## 最易被写错的五个事实

这些是历史上 AI 助手反复搞错的点，改代码前先对照：

| 事实 | 说明 |
|---|---|
| `CX_` **不是**类或常量前缀 | 类是纯 PSR-4 无前缀（`ContentRepository`），全局常量是 `CITEORYX_`。`CX_` 只用于 issue code 字符串（`CX_CONTENT_STALE`） |
| 表名不是 `cx_content` | 真实表名见 `src/Core/constants.php` 的 `CITEORYX_TABLE_*`：`cx_content_items`、`cx_metrics_daily`、`cx_query_pages`、`cx_issues`、`cx_links`、`cx_scan_runs`、`cx_ai_prompt_runs` |
| 不使用 REST schema API | Controller 入口是 `register( string $namespace )`，参数在 callback 内手动 sanitize + 白名单校验。全仓没有 `get_endpoint_args_for_item_schema()` |
| Google 和 Bing 同目录 | 都在 `src/Integrations/SearchConsole/`，没有独立的 `Google/` `Bing/` |
| AI 分析任务不落表 | `AiAnalysisTaskStore` 用 1 小时 Transient，不存在 `cx_ai_analysis_tasks` 表 |

## 安全约束

- **API Key 读取顺序**：`CITEORYX_*` 常量 → 同名环境变量 → 数据库加密值（Sodium，回退 OpenSSL）。`KeyStore::get('openai_api_key')` 对应 `define( 'CITEORYX_OPENAI_API_KEY', ... )`。
- **外部 HTTP 走 `Infrastructure\Http\HttpClient`**：内置 SSRF 校验、`wp_safe_remote_*`、超时与响应大小上限。不要直接 cURL，也不要绕过它调 `wp_remote_*`。
- **REST 权限**：`permission_callback` + `Capabilities::*` 常量。涉及单条内容的端点必须接 `can_access_content()` / `can_access_content_id()`，否则 Author / Contributor 会越权。
- **密钥不外泄**：后台只显示掩码值，REST 响应和日志都不得包含完整 Key。

## 开发命令

完整命令见 `CLAUDE.md`。最容易踩的一条：

```bash
composer test-install
```

PHPUnit 跑在 WordPress 官方测试套件上，没先装测试环境时 `composer test` 会直接报错退出。

## 相关文档

| 文档 | 用途 |
|---|---|
| `CLAUDE.md` | **唯一事实源**：架构、命令、边界约定 |
| `ARCHITECTURE.md` | 分层设计与核心流程 |
| `API.md` | REST API 完整参考 |
| `DATABASE.md` | 数据库表结构 |
| `TESTING.md` | 测试环境与覆盖清单 |
| `dev-progress.md` | 功能进度的权威清单 |
| `references/case-*.md` | 线上问题复盘笔记 |
| `.cursorrules` / `.github/copilot-instructions.md` | Cursor / Copilot 的工具专属精简版 |
