# Changelog

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
