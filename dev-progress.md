# Citeoryx 开发进度

> 最后更新：2026-07-25

> 当前发布版本：2.3.0
> 下一开发目标：2.4.0

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
- `POST /recommendations/apply` 创建安全 WordPress Revision，包含完整字段校验、并发快照与重复提交幂等
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
- 报告页展示内容状态、平均健康分/AI 准备度、问题分布、优先问题与最近扫描，并可导出防公式注入的 CSV 和自动分页 A4 PDF
- 后台子菜单只保留已实现页面，所有入口均可正确渲染 React 应用
- 设置页支持周报与严重问题通知开关、收件邮箱、测试发送和最近发送状态
- 首次设置加载会隔离可选通知状态异常，后台 API 错误统一过滤 HTML 正文
- 优化工作台支持编辑标题、摘要和正文，提供字段级差异预览，并在不修改父内容的前提下创建 Revision 供人工审核；已验证提案可按 GSC/Bing 来源比较发布前后 7/28 天效果

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
- OpenAI / DeepSeek：通过 Chat Completions API 生成内容优化建议与 AI 可发现性分析
- Anthropic：通过 Messages API 生成内容优化建议与 AI 可发现性分析
- 支持 OpenAI / Anthropic 兼容的第三方 HTTPS API 基础地址与模型标识
- OAuth token、Google client secret 和 AI API key 通过 `KeyStore` 加密存储
- 管理端“集成”页可配置、连接和断开 Google Search Console，并配置 AI 提供商
- Bing Webmaster Tools：API Key 认证、站点列表、查询指标与 URL 查询词读取
- DeepSeek：通过 Chat Completions API 生成内容优化建议与 AI 可发现性分析
- 所有搜索控制台与 AI 密钥通过 `KeyStore` 加密存储
- 已实现 Google Search Console / Bing 定时数据导入：Google 写入三天延迟的日快照，Bing 按 API 返回的统计日期幂等写入，使用 ID 游标分批续接
- Google / Bing 支持显式连接验证、健康状态记录与连续导入失败告警；合法空数据不会被误判为连接失败
- Google / Bing 对网络错误、429 和 5xx 最多尝试 3 次，支持有上限的 `Retry-After` 与指数退避；确定性 4xx 不重试
- 搜索导入按 Provider 真实统计日期保存页面 × 查询快照；Google 同时记录国家和设备维度，报告页与 CSV 提供 28 天趋势及维度汇总
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
| `src/Integrations/AiProviders/AbstractAiProvider.php` | AI 提示词、加密密钥与安全 HTTP 请求共享逻辑 |
| `src/Integrations/AiProviders/OpenAiCompatibleProvider.php` | OpenAI Chat Completions 协议及兼容 API 适配器 |
| `src/Integrations/AiProviders/AnthropicCompatibleProvider.php` | Anthropic Messages 协议及兼容 API 适配器 |
| `src/Integrations/AiProviders/AiProviderFactory.php` | AI 提供商、模型与兼容端点解析工厂 |
| `src/Rest/Controllers/AiController.php` | AI 配置与内容分析 REST 端点 |
| `assets/src/admin/components/AiIntegrationSettings.js` | AI 提供商、模型与兼容 API 地址配置界面 |
| `assets/src/admin/components/Integrations.js` | Google Search Console、Bing Webmaster Tools 集成界面 |
| `src/Integrations/SearchConsole/BingWebmasterTools.php` | Bing Webmaster Tools API 适配器 |
| `src/Rest/Controllers/BingController.php` | Bing Webmaster Tools REST 端点 |
| `src/Integrations/AiProviders/DeepSeekProvider.php` | DeepSeek Chat Completions 适配器 |
| `src/Integrations/AiProviders/AiProviderFactory.php` | 增加 DeepSeek 分支 |
| `src/Rest/Controllers/AiController.php` | 支持 DeepSeek 配置与状态 |
| `src/Rest/Controllers/ReportsController.php` | 新增稳定的站点报告汇总契约 |
| `assets/src/admin/components/Reports.js` | 新增报告查看、刷新与 CSV/PDF 导出页面 |
| `assets/src/admin/reportPdf*.js` | 浏览器端生成支持中文、续表与页码的 A4 PDF |
| `src/Admin/Menu.php` | 移除未实现入口并修复子菜单空白页 |
| `src/Application/Notifications/WeeklyDigest.php` | 新增周报聚合、邮件发送、站点时区调度和幂等状态 |
| `src/Application/Notifications/CriticalIssueNotifier.php` | 扫描完成后汇总严重问题，并按问题集合去重发送 |
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
| `src/Application/Search/SearchIntegrationHealth.php` | 记录搜索集成健康状态、连续失败次数与恢复时间 |
| `src/Integrations/SearchConsole/*` | 保留安全的请求错误并提供统一连接验证契约 |
| `src/Rest/Controllers/SearchConsoleController.php` / `BingController.php` | 新增连接验证端点并返回健康状态 |
| `assets/src/admin/components/Integrations.js` / `src/Admin/Notices.php` | 展示连接状态并在连续失败后通知集成管理员 |
| `src/Domain/Metrics/MetricsRepository.php` | 幂等保存页面 × 查询 × 国家 × 设备快照，并提供 28 天维度聚合与每日趋势 |
| `assets/src/admin/components/Reports.js` / `ReportTables.js` | 展示搜索趋势、热门查询、国家和设备表现 |
| `src/Infrastructure/Http/RetryPolicy.php` | 为搜索 API 提供有上限的暂时性失败重试、指数退避与 `Retry-After` 解析 |
| `src/Application/Planning/TopicOpportunityFinder.php` | 将本地搜索查询证据分类为临门一脚、优先刷新和低置信主题缺口候选 |
| `src/Rest/Controllers/PlanningController.php` / `assets/src/admin/components/Planning.js` | 提供分页主题机会 API 与后台内容规划视图 |
| `src/Application/Planning/PlanningCalendar.php` / `src/Domain/Planning/CalendarRepository.php` | 按站点时区聚合原生定时文章与到期复核内容 |
| `assets/src/admin/components/PlanningCalendar.js` / `PlanningCalendarLists.js` | 展示发布计划、过期提醒并支持标记复核完成 |
| `src/Application/Optimize/RevisionDraftService.php` | 通过公开 WordPress Post API 创建安全、幂等的 Revision，并校验完整快照冲突 |
| `src/Application/Optimize/RevisionPerformanceMonitor.php` / `src/Domain/Metrics/MetricsRepository.php` | 固化验证后的发布时间点，并按来源聚合固定日期范围的发布前后搜索效果 |
| `src/Rest/Controllers/OptimizerController.php` | 提供编辑快照并新增 `/recommendations/apply` 契约与对象级权限检查 |
| `assets/src/admin/components/OptimizerRevisionPanel.js` / `RevisionDiffPreview.js` | 编辑拟议字段、预览差异并创建 Revision |

## 待开发 / 建议下一步

1. **内容规划与日历**
   - 主题机会发现（已完成首版：本地 GSC/Bing 证据、三类保守规则与人工复核提示）
   - 发布计划与过期内容提醒（已完成首版：原生定时文章、站点时区、画像复核周期）

2. **报告与通知**
   - 严重问题通知（已完成首版：扫描完成汇总、`critical`/`high`、集合去重与独立状态）
   - PDF 报告导出（已完成首版：浏览器端 A4 生成、中文渲染、自动分页与下载错误提示）

3. **测试与质量**
   - Playwright E2E 测试（已完成首版：wp-env、首次引导、规划/报告导航、PDF 下载与 CI 失败诊断）

4. **优化工作台闭环**
   - Revision Diff 与安全创建修订（已完成首版：完整字段快照、差异预览、能力与对象权限、并发冲突、幂等提交）
   - Evidence Panel（已完成首版：规则建议关联问题引擎白名单证据、原生折叠展示与移动端布局）
   - 内链建议（已完成首版：公开候选、已链接去重、标题/焦点关键词本地相关性评分、语言过滤与人工锚文本）
   - 发布后效果验证（已完成首版：显式验证扫描固化时间点、7/28 天等长自然日窗口、GSC/Bing 来源分开展示与收集状态）
   - 完成与发布后验证（已完成首版：提案哈希状态机、发布状态识别、扫描内容哈希验证、偏离提案告警与权限控制）

## 部署备忘

- 复制整个 `citeoryx` 目录到 `wp-content/plugins/`
- 后台启用插件
- 完成引导页站点画像
- 在“总览”页启动首次扫描，或运行 `wp citeoryx scan full`
- 已扫描内容可在“优化器”中生成优化建议

## 已知限制

- PHP 8.4 CLI 已可用；2026-07-22 已对项目全部 PHP 文件执行语法检查并通过。
- GitHub Actions 已在 PHP 8.0–8.4、WordPress 6.6/latest 与 MySQL 8 矩阵中通过 PHPUnit、PHPCompatibility 和 WPCS（2026-07-22）。
- 已将 TypeScript 固定为与 `@wordpress/scripts@27` 兼容的 5.4.x；Jest、JS lint、React 构建与 CSS lint 均已成功（2026-07-22）。
- 本机 PHPUnit 因 PHP 8.4 CLI 未启用 `mbstring` 而在框架启动阶段停止；相关用例已提交，但本轮无法在本机执行。
- 本机没有 Docker 命令，无法启动 wp-env；Playwright 配置、测试发现与静态检查已在本地验证，真实 WordPress 后台旅程由 CI 执行。
- Google 每个内容 URL/日期保存前 100 个查询 × 国家 × 设备组合；Bing 查询统计由 API 返回自身日期（通常按周更新），且不提供国家和设备字段。
- 搜索 API 暂时性失败最多同步尝试 3 次、单次等待最多 2 秒；凭据、权限等确定性 4xx 会立即失败并进入健康状态记录。
- Google Search Console 需要在 Google Cloud Console 创建 OAuth Web Client，并将插件显示的 callback URI 加入授权重定向 URI。
- Bing Webmaster Tools 需要 API Key（Bing 后台“API 访问”）。
- OpenAI、Anthropic、DeepSeek 及其兼容 API 使用需要单独配置 API Key；密钥不会返回给浏览器或 REST 响应。
