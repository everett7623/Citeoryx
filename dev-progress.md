# Citeoryx 开发进度

> 最后更新：2026-07-16

## 已完成功能

### 核心架构
- PSR-4 自动加载 + 无 Composer 时的 fallback 自动加载器 (`citeoryx.php`)
- 轻量级依赖注入容器 (`src/Core/Container.php`)
- 数据库迁移管理 (`src/Infrastructure/Database/SchemaManager.php`)
- 角色与权限 (`src/Core/Capabilities.php`)

### REST API
- 命名空间：`citeoryx/v1`
- 端点：
  - `GET /dashboard` 总览数据
  - `GET /content` / `/content/{id}` / `POST /content/{id}/scan` 内容资产
  - `GET /issues` / `PATCH /issues/{id}` 问题管理
  - `POST /scans` / `GET /scans/{id}` 扫描任务
  - `GET /settings` / `POST /settings` 站点画像与设置
  - `GET /optimizer/{id}` 内容优化建议
- 统一权限检查与响应格式 (`src/Rest/Controllers/BaseController.php`)

### 后台 React UI
- 总览、内容资产、问题与机会、优化器、设置五大标签页
- 引导页 (`Onboarding.js`)：首次使用强制完成站点画像
- CSV 导出（内容资产、问题列表）
- 激活后自动跳转到插件页面

### 内容扫描与分析
- 全量/增量扫描 (`ContentScanner.php`)
- 语言检测修复（Polylang 兼容）
- 问题引擎 (`IssueEngine.php`)：
  - 可索引性：noindex、外部 canonical
  - 内容：过时、低价值、标题结构
  - 链接：孤立内容、失效外链
  - AI 可发现性：作者不明确、缺少引用
- 评分：健康分、AI 准备度

### 链接检查
- HTTP HEAD + GET fallback (`HttpClient::head`)
- SSRF 保护（仅允许 http/https，禁止本地/保留地址）
- `LinkChecker` 批处理外链状态
- `Scheduler` 定时任务集成
- 数据库 `cx_links` 新增 `last_error` 字段

### 外部集成
- SEO 插件适配器：Yoast SEO / Rank Math / AIOSEO
- Google Search Console：OAuth 2.0 授权、token 自动刷新、站点列表、站点指标与 URL 查询词读取
- OpenAI：通过 Chat Completions API 生成内容优化建议与 AI 可发现性分析
- OAuth token、Google client secret、OpenAI API key 通过 `KeyStore` 加密存储
- 管理端“集成”页可配置、连接和断开 Google Search Console，并配置 OpenAI
- Bing Webmaster Tools 尚未实现

### CLI
- `wp citeoryx scan full`
- `wp citeoryx scan post <id>`

### 其他
- 隐私 API 导出/擦除
- 卸载数据清理

## 最近修改

| 文件 | 变更 |
|---|---|
| `src/Application/Optimize/Optimizer.php` | 实现优化建议生成；修正 issue code 模板 |
| `src/Rest/Controllers/OptimizerController.php` | 新增 `/optimizer/{id}` 端点 |
| `src/Rest/Router.php` | 注册优化器控制器 |
| `src/Core/Container.php` | 注册 Optimizer 服务 |
| `assets/src/admin/components/Optimizer.js` | 优化器 React 页面 |
| `assets/src/admin/App.js` | 增加优化器标签页 |
| `assets/src/admin/style.css` | 优化器样式 |
| `src/Core/Activator.php` | 激活后跳转到插件页 |
| `src/Admin/Notices.php` | 新增 activation_redirect |
| `src/Core/Plugin.php` | 注册 admin_init 重定向 |
| `assets/src/admin/components/Issues.js` | CSV 导出 |
| `assets/src/admin/components/Inventory.js` | CSV 导出 |
| `API.md` | 补充优化器端点文档与集成端点文档 |
| `src/Integrations/SearchConsole/GoogleOAuth.php` | Google OAuth 授权、回调、token 刷新与加密存储 |
| `src/Integrations/SearchConsole/GoogleSearchConsole.php` | Google Search Console API 适配器 |
| `src/Rest/Controllers/SearchConsoleController.php` | Google Search Console REST 端点 |
| `src/Integrations/AiProviders/OpenAiProvider.php` | OpenAI 内容建议与可发现性分析适配器 |
| `src/Integrations/AiProviders/AiProviderFactory.php` | AI 提供商解析工厂 |
| `src/Rest/Controllers/AiController.php` | AI 配置与内容分析 REST 端点 |
| `assets/src/admin/components/Integrations.js` | Google Search Console 与 OpenAI 配置界面 |

## 待开发 / 建议下一步

1. **完善外部 API**
   - Bing Webmaster Tools API
   - DeepSeek 等额外 AI Provider
   - Google Search Console 数据的定时导入与本地历史快照

2. **内容规划与日历**
   - 主题机会发现
   - 发布计划与过期内容提醒

3. **报告与通知**
   - 邮件周报
   - 严重问题通知
   - PDF 报告导出

4. **测试与质量**
   - 单元测试（PHPUnit）
   - PHPCS / WPCS 规范检查
   - Playwright E2E 测试

## 部署备忘

- 复制整个 `citeoryx` 目录到 `wp-content/plugins/`
- 后台启用插件
- 完成引导页站点画像
- 在“总览”页启动首次扫描，或运行 `wp citeoryx scan full`
- 已扫描内容可在“优化器”中生成优化建议

## 已知限制

- PHP CLI 不在当前本机 PATH 中，尚未执行 PHP 语法检查。
- `npm run lint:js` 被现有依赖兼容性错误阻塞：`@typescript-eslint` 加载时 `ts-api-utils` 抛出 `Cannot read properties of undefined (reading 'Intrinsic')`。
- React 构建已成功（2026-07-16：`npm run build`）。
- Google Search Console 需要在 Google Cloud Console 创建 OAuth Web Client，并将插件显示的 callback URI 加入授权重定向 URI。
- OpenAI 使用需要单独配置 API Key；密钥不会返回给浏览器或 REST 响应。
