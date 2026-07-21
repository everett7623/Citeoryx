# Citeoryx 开发进度

> 最后更新：2026-07-22

> 当前发布版本：2.1.0
> 下一开发目标：2.2.0

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
- `GET /reports/summary` 内容评分、问题分布与最近扫描汇总
- `GET /settings` / `POST /settings` 站点画像与设置
- `GET /optimizer/{id}` 内容优化建议
- `POST /notifications/test` 测试邮件周报
- 扫描接口使用持久化后台任务，按批次返回进度并防止并发全量扫描
- 队列调度失败会记录为失败任务，扫描器按主机归类 URL 并排除非 HTTP 链接
- 外链检查使用不可变 ID 游标完成单轮遍历，HEAD 非成功响应会由 GET 复核
- 统一权限检查与响应格式 (`src/Rest/Controllers/BaseController.php`)
- Settings 接口提供完整性状态、真实内容类型选项和服务端字段校验

### 后台 React UI
- 总览、内容资产、问题与机会、优化器、集成、报告、设置七个标签页
- 引导页 (`Onboarding.js`)：首次使用强制完成完整站点画像，保存后直接进入后台
- CSV 导出（内容资产、问题列表）
- 激活后自动跳转到插件页面
- Dashboard 对扫描任务进行单一轮询，显示进度与失败原因
- 报告页展示内容状态、平均健康分/AI 准备度、问题分布、优先问题与最近扫描，并可导出防公式注入的 CSV
- 后台子菜单只保留已实现页面，所有入口均可正确渲染 React 应用
- 设置页支持周报开关、收件邮箱、测试发送和最近发送状态
- 首次设置加载会隔离可选通知状态异常，后台 API 错误统一过滤 HTML 正文

### 内容扫描与分析
- 全量/增量扫描 (`ContentScanner.php`)
- 站点画像所选内容类型驱动默认扫描范围
- 内容状态分类（健康、机会、孤立、过时、失效、需复核）
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
- Bing Webmaster Tools：API Key 认证、站点列表、查询指标与 URL 查询词读取
- DeepSeek：通过 Chat Completions API 生成内容优化建议与 AI 可发现性分析
- 所有搜索控制台与 AI 密钥通过 `KeyStore` 加密存储
- 已实现 Google Search Console / Bing 每日数据导入：按内容 URL 和最近确认日期写入本地 `cx_metrics_daily` 快照，使用 ID 游标分批续接
- 站点报告及 CSV 已显示本地 28 天搜索点击与展现聚合

### CLI
- `wp citeoryx scan full`
- `wp citeoryx scan post <id>`

### 其他
- 隐私 API 导出/擦除
- 卸载数据清理
- 每周邮件周报使用站点时区下的周一 09:00 单次 Cron，并通过 ISO 周期键防重

## 最近修改

| 文件 | 变更 |
|---|---|
| `src/Application/Settings/SiteProfileSchema.php` | 统一站点画像字段、选项、默认值、清洗与完整性校验 |
| `src/Rest/Controllers/SettingsController.php` | 严格校验设置请求并返回稳定的设置契约 |
| `assets/src/admin/components/SiteProfileFields.js` | 首次引导与设置页共享站点画像字段 |
| `assets/src/admin/components/Onboarding.js` | 补齐站点画像并直接使用保存响应完成引导 |
| `assets/src/admin/App.js` | 区分加载失败与未完成画像，提供重试状态 |
| `tests/Unit/SettingsControllerTest.php` | 增加设置契约与无效请求不写入测试 |
| `src/Infrastructure/Queue/Scheduler.php` | 扫描、健康重算和外链检查改为可续接批次任务 |
| `src/Application/Analyze/ContentStatusClassifier.php` | 根据分析结果生成内容资产状态 |
| `src/Domain/Issue/IssueRepository.php` | 刷新问题时复用同一内容/问题代码记录 |
| `src/Application/Scan/ContentScanner.php` | 站点画像扫描范围、批次扫描和 URL 归一化 |
| `src/Infrastructure/Queue/Scheduler.php` | 持久化扫描任务、续接批次和失败状态 |
| `src/Domain/Metrics/MetricsRepository.php` | 空聚合结果的安全处理 |
| `tests/Unit/IssueRepositoryTest.php` | 覆盖问题刷新去重 |
| `tests/Unit/ScansControllerTest.php` | 覆盖异步扫描创建与类型校验 |
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
| `assets/src/admin/components/Integrations.js` | Google Search Console、Bing Webmaster Tools、OpenAI 与 DeepSeek 配置界面 |
| `src/Integrations/SearchConsole/BingWebmasterTools.php` | Bing Webmaster Tools API 适配器 |
| `src/Rest/Controllers/BingController.php` | Bing Webmaster Tools REST 端点 |
| `src/Integrations/AiProviders/DeepSeekProvider.php` | DeepSeek Chat Completions 适配器 |
| `src/Integrations/AiProviders/AiProviderFactory.php` | 增加 DeepSeek 分支 |
| `src/Rest/Controllers/AiController.php` | 支持 DeepSeek 配置与状态 |
| `src/Rest/Controllers/ReportsController.php` | 新增稳定的站点报告汇总契约 |
| `assets/src/admin/components/Reports.js` | 新增报告查看、刷新与 CSV 导出页面 |
| `src/Admin/Menu.php` | 移除未实现入口并修复子菜单空白页 |
| `src/Application/Notifications/WeeklyDigest.php` | 新增周报聚合、邮件发送、站点时区调度和幂等状态 |
| `src/Rest/Controllers/NotificationsController.php` | 新增测试邮件端点 |
| `assets/src/admin/components/Settings.js` | 增加周报配置、测试发送和状态展示 |
| `assets/src/admin/apiError.js` | 统一将服务器 HTML 错误降级为当前操作的纯文本提示 |
| `src/Application/Scan/LinkChecker.php` | 外链检查使用 ID 游标并在 HEAD 失败时通过 GET 复核 |
| `src/Application/Search/SearchPerformanceImporter.php` | Google/Bing 按内容导入每日搜索表现快照 |
| `src/Infrastructure/Queue/Scheduler.php` | 新增搜索表现每日任务与 ID 游标批次续接 |
| `src/Core/Plugin.php` | 注册搜索表现 Cron 与批次钩子 |
| `src/Core/Activator.php` / `src/Core/Deactivator.php` | 创建与清除搜索表现计划任务 |
| `src/Domain/Metrics/MetricsRepository.php` | 新增站点级 28 天搜索指标聚合 |
| `src/Rest/Controllers/ReportsController.php` | 报告响应包含本地搜索表现汇总 |
| `assets/src/admin/components/Reports.js` | 后台报告显示搜索点击与展现 |
| `assets/src/admin/reportCsv.js` | CSV 导出包含搜索表现快照 |

## 待开发 / 建议下一步

1. **完善外部 API**
   - 搜索指标的国家、设备与查询维度历史快照
   - 搜索 API 的连接验证与失败告警

2. **内容规划与日历**
   - 主题机会发现
   - 发布计划与过期内容提醒

3. **报告与通知**
   - 严重问题通知
   - PDF 报告导出（当前已支持后台汇总与 CSV）

4. **测试与质量**
   - Playwright E2E 测试
   - 外部 API contract mock 与失败重试测试

## 部署备忘

- 复制整个 `citeoryx` 目录到 `wp-content/plugins/`
- 后台启用插件
- 完成引导页站点画像
- 在“总览”页启动首次扫描，或运行 `wp citeoryx scan full`
- 已扫描内容可在“优化器”中生成优化建议

## 已知限制

- PHP 8.4 CLI 已可用；2026-07-21 已对项目全部 PHP 文件执行语法检查并通过。
- GitHub Actions 已在 PHP 8.0–8.4、WordPress 6.6/latest 与 MySQL 8 矩阵中通过 PHPUnit、PHPCompatibility 和 WPCS（2026-07-22）。
- 已将 TypeScript 固定为与 `@wordpress/scripts@27` 兼容的 5.4.x；JS lint、React 构建与 CSS lint 均已成功（2026-07-21）。
- 本机没有运行中的 WordPress 实例或可复用后台浏览器页面，首次设置尚未执行真实 E2E 回归。
- 搜索表现目前按内容 URL 写入每日聚合快照，国家、设备和查询维度历史快照尚未实现。
- 搜索 API 连接验证、导入失败告警与自动重试尚未实现。
- Google Search Console 需要在 Google Cloud Console 创建 OAuth Web Client，并将插件显示的 callback URI 加入授权重定向 URI。
- Bing Webmaster Tools 需要 API Key（Bing 后台“API 访问”）。
- OpenAI / DeepSeek 使用需要单独配置 API Key；密钥不会返回给浏览器或 REST 响应。
