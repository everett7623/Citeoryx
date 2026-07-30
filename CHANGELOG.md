# Changelog

## 2.3.1 - 待发布

### Added
- 新增统一的日志类 `Logger`，规范化调试输出
- Dashboard、Inventory、Issues 添加友好的空状态提示
- 添加移动端响应式优化（支持 782px 和 600px 断点）

### Changed
- 调试日志统一使用 `Logger` 类，支持结构化上下文
- 优化前端构建产物（82.8KB JS + 8.52KB CSS）
- 改进空列表显示，提供清晰的操作引导

### Fixed
- 清理同步冲突文件 `SearchIntegrationHealth.sync-conflict-*.php`
- 规范化 4 处 `error_log()` 调用
- 修复表格和分页组件在条件渲染时的布局问题

### Development
- 新增项目升级优化报告 `UPGRADE-REPORT.md`
- 统一 AI 工具开发文档（CLAUDE.md, .cursorrules, copilot-instructions.md）
- 新增通用 AI 开发指南 `AI-GUIDELINES.md`
- 新增执行总结 `UPGRADE-SUMMARY.md`

## 2.3.0 - 2026-07-25

### Added
- 已验证的 Revision 新增发布后 7/28 天搜索效果对比，按 Google Search Console 与 Bing 来源独立展示点击、展示、CTR 和平均排名变化，并标识数据收集状态。

## 2.2.4 - 2026-07-25

### Added
- 规则优化建议新增可展开的证据面板，显示问题引擎记录的计数、URL、标题结构与 robots 指令等白名单证据。
- 优化器新增基于本地标题与焦点关键词的内链建议，排除已链接及非公开目标，并提供建议锚文本和相关度依据。
- Revision 工作流新增待审核、已应用未发布、发布待扫描、已验证和偏离提案状态，并可在优化器中触发发布后扫描验证。

## 2.2.3 - 2026-07-24

### Changed
- AI 设置移动到集成页顶部，Sub2API / OpenAI Responses（Codex）入口前置；当 Sub2API 根地址误填在普通兼容模式时可一键切换协议。

### Fixed
- Responses 请求显式使用非流式模式，并兼容 `response.completed` 封装、SSE 文本增量和常见第三方返回结构。
- AI 连接测试现在区分 HTML 控制面板、空响应、无效 JSON、HTTP 错误和无文本协议响应，同时只展示安全错误字段。

## 2.2.2 - 2026-07-24

### Fixed
- 集成页全局通知现在独占双栏布局的一整行，不再被相邻 Google 卡片拉伸成大块红色区域。
- 当 Sub2API 服务根地址误填在普通 OpenAI 兼容模式时，设置页会明确提示改用 OpenAI Responses API，不自动改写用户 URL。

## 2.2.1 - 2026-07-24

### Added
- AI 设置新增独立启用状态和 10-180 秒请求超时，保存值会实际应用到提供商 HTTP 请求。
- AI 设置展示官方固定端点、加密密钥状态、数据发送范围说明和内容优化器快捷入口。

### Changed
- AI 设置按服务商/模型、端点/API Key 的两列结构重排，并拆分为独立表单、标题状态和操作组件。
- 禁用 AI 时保留已保存的服务商、模型、兼容 URL 和加密密钥；连接测试仍可验证已保存配置。

### Fixed
- 优化器现在区分“AI 已关闭”和“提供商未配置”，不再把主动关闭误报为配置缺失。
- 旧版客户端未提交 `enabled` 字段时，选择非 `none` 服务商仍会默认启用，避免升级后行为倒退。

## 2.2.0 - 2026-07-24

### Added
- 内容优化器新增 AI 深度分析工作流，展示引用潜力、置信度、优势、薄弱点和结构化改进建议。
- 新增短期 AI 任务状态与最近任务恢复，用户刷新或返回页面后可继续查看 `queued`、`running`、`completed`、`failed` 状态。

### Changed
- AI 内容分析从同步双请求改为 Action Scheduler / WordPress Cron 后台任务，同一用户与同一内容的活动任务自动复用，避免重复调用上游 API。
- 优化器拆分为页面控制、规则结果、AI 任务和 AI 结果组件，并在宽屏使用双栏工作区；现有规则评分和 Revision 审核流程保持不变。
- OpenAI、Anthropic 及兼容提供商现在可解析代码块或简短说明包裹的 JSON 对象。

### Security
- AI 任务结果按发起用户和内容隔离，REST 响应不暴露内部用户 ID、API Key、上游响应正文或异常详情。

## 2.1.7 - 2026-07-23

### Added
- 新增 OpenAI Responses API（Sub2API / Codex）模式：填写服务根地址后按 Responses 协议请求 `/v1/responses`，使用 `input` 请求体与 `store: false`。

### Fixed
- AI 连接测试失败时返回安全的 HTTP 状态或网络错误，不再仅显示无法定位的泛化提示。

## 2.1.6 - 2026-07-23

### Fixed
- OpenAI / Anthropic 兼容 API 现在原样使用用户填写的完整 HTTPS 请求 URL，不再自动追加 `/chat/completions` 或 `/messages`。
- 自定义请求 URL 允许查询参数，同时继续拒绝 URL 用户信息和片段，并使用 WordPress 安全 HTTP 客户端。

## 2.1.5 - 2026-07-22

### Fixed
- “测试连接”按钮现在只依据服务端确认的活动提供商与已保存 Key 启用，不再被浏览器自动填充或表单显示值错误禁用。
- API Key 输入框声明为新密码字段，降低浏览器和密码管理器复用登录凭据的概率。

## 2.1.4 - 2026-07-22

### Fixed
- 修复 AI 设置保存后状态接口把提供商对象传给字符串参数引发的 `TypeError`，保存完成后“测试连接”按钮现在可以正常启用。

## 2.1.3 - 2026-07-22

### Fixed
- AI 提供商设置新增“测试连接”按钮，使用已保存的 API Key、模型和端点发送最小请求，并明确显示成功或失败结果。
- 有未保存的提供商、Key、模型或端点变更时禁用连接测试，避免误把旧配置的测试结果当作当前输入结果。

## 2.1.2 - 2026-07-22

### Added
- 新增 Anthropic Messages API 适配器，以及 OpenAI / Anthropic 协议兼容第三方 API 的自定义 HTTPS 基础地址和模型配置。
- AI 集成页新增 Anthropic 与两种兼容模式，集成卡片改为响应式双栏布局，AI 配置卡片全宽显示。

### Security
- 自定义 AI API 地址仅接受无查询参数的 HTTPS 基础地址，调用统一走 WordPress 安全 HTTP 客户端；密钥继续加密存储且不返回给浏览器。

## 2.1.1 - 2026-07-22

### Fixed
- 空站点画像在 Settings REST 响应中固定为对象，避免首次引导被前端契约校验拒绝。
- 修复 WordPress TabPanel 的初始页属性，使 `#/reports` 深链接正确打开报告页。
- 修复 PHP 8.4 WPCS 检查中的 `SearchSchedulerTest` 关联数组格式错误。

## 2.1.0 - 2026-07-21

### Added
- 新增基于 WordPress 原生定时文章的发布计划、按站点审查周期计算的过期内容提醒及复核完成操作。
- 新增基于本地 Google/Bing 查询快照的主题机会发现、分页 REST 端点与后台内容规划页。
- 新增 Bing Webmaster Tools API Key 集成、连接验证、站点与页面查询统计读取。
- 新增 Google / Bing 搜索表现定时分批导入、本地历史快照、连接健康状态与连续失败告警。
- 新增项目级 WPCS 规则集并将编码规范检查升级为 CI 阻断条件；数据库表名统一使用 `%i` 标识符占位符。
- 补齐首次站点画像：核心内容类型、主要语言/地区、更新节奏、风险等级与默认审查周期。
- Settings REST 响应新增 `profile_complete` 和由 WordPress 实际公开内容类型生成的 `profile_options`。
- 新增 Settings REST 契约测试，覆盖完整保存和无效请求不写入。
- 全量与增量扫描改为持久化后台批次任务，支持 Action Scheduler / WordPress Cron、进度轮询和重复触发去重。
- 根据问题与健康分生成内容资产状态，恢复总览统计和筛选能力。
- 扫描器现在按主机解析绝对/协议相对链接，并排除 `mailto:`、`tel:` 等非 HTTP 链接。
- 新增站点汇总报告 REST 端点和后台报告页，展示内容评分、状态分布、待处理问题与最近扫描，并支持安全 CSV 导出。
- 新增浏览器端 A4 PDF 报告导出，支持中文、自动分页、续表标题、页眉页脚与页码。
- 新增可配置的每周邮件周报、测试发送端点和发送状态记录，按站点时区调度并防止同一 ISO 周重复发送。
- 新增扫描完成后的严重问题邮件汇总，覆盖 `critical`/`high` 待处理问题，并按问题集合去重。
- 新增基于 wp-env 的 Playwright E2E，覆盖首次引导、后台导航和 PDF 报告下载，并在 CI 保留失败诊断产物。
- 优化工作台新增标题、摘要、正文的字段级差异预览，以及带权限、并发校验和幂等保护的 WordPress Revision 创建流程；父内容不会被修改或发布。

### Fixed
- 首次设置接口不再因可选的周报状态读取失败而整体返回 500，后台也不再直接显示服务器 HTML 错误页。
- 修正 Composer PSR-4 namespace 前缀，确保生成 `vendor` 后仍能自动加载插件类。
- 外链检查改为不可变 ID 游标，避免达到批次大小后永久续排；HEAD 失败时会使用 GET 复核。
- 初始设置加载失败时不再被误判为“尚未完成画像”，页面现在显示原始错误和重试操作。
- 设置接口不再接受缺失、非法枚举、失效内容类型或字符串布尔值后静默保存。
- 首次设置保存成功后直接采用服务端响应切换页面，避免二次 GET 失败导致停留在引导页。
- 修复 Action Scheduler 单篇扫描未注册执行 Hook、外链域名被误判为不安全、问题记录重复插入和后台子菜单导航失效。
- 卸载时清理加密凭据、运行选项和插件权限；停用时清除全部 Cron / Action Scheduler 任务。
- 队列调度失败会将扫描任务标记为 `failed`，REST 列表和问题更新接口拒绝数组型非法输入。
- 修复指标聚合空结果告警，并让条件消失时的 `in_progress` 问题正确转为已解决。
- 固定与 `@wordpress/scripts@27` 兼容的 TypeScript 5.4.x，恢复 JS lint；优化器不再引用不存在的 `Badge` 组件。
- 后台菜单现在只展示已实现页面，所有子菜单都渲染 React 容器，并与实际标签页 slug 保持一致。
- Author 和 Contributor 仅能访问本人创建的内容；问题、优化建议与 AI 请求增加对象级权限校验，后台标签和导出操作同步按能力隐藏。

## 2.0.1 - 2026-07-20

### Fixed
- 修复 onboarding 页面保存站点画像时 `main_region` 字段触发的 PHP 8 TypeError（`Cannot access offset of type string on string`）。
- `SettingsController` 现在会对 `profile` 和 `settings` 参数先做 `is_array` 校验，再进入 sanitize。

## 2.0.0 - 2026-07-13

### Added
- 初始版本：Citeoryx 品牌升级。
- 内容资产盘点与本地扫描。
- SEO 插件兼容层（Rank Math / Yoast / AIOSEO / SEOPress）。
- 内部链接图谱、孤儿页检测、失效链接占位。
- 内容健康评分与 AI 可发现性准备度评分。
- 问题与机会引擎（Noindex、Canonical、内容过时、薄内容、缺失 H1、作者不明、证据缺失等）。
- REST API（Dashboard、Content、Issues、Scans、Settings）。
- React 管理后台（总览、内容资产、问题、设置）。
- Action Scheduler / Cron 集成。
- 隐私导出与擦除支持。
- 加密的 API Key 存储。
- 安全的外部 HTTP 请求封装。
