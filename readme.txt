=== Citeoryx ===
Contributors: everettlabs
Tags: content-health, ai-discoverability, seo, content-inventory, gsc
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 2.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress 内容健康度持续监控、优化与 AI 可发现性引擎。

== Description ==

Citeoryx 帮助站点持续回答：

* 站内目前有哪些内容资产？
* 哪些页面正在增长、停滞、衰退或互相竞争？
* 哪些问题最值得先修复？
* 页面应该增加、删除、合并、更新还是重新定位？
* 内容是否容易被搜索引擎、生成式搜索和 AI 问答系统发现、理解和引用？
* 优化完成后，是否真正带来了展示、点击、排名和转化改善？

核心能力：

* **Content Inventory** - 自动盘点全站内容资产
* **Content Health Engine** - 多维度健康检查与趋势分析
* **Opportunity Intelligence** - 衰退、机会、Striking Distance 识别
* **AI Discoverability Readiness** - AI 可发现性准备度
* **Optimization Workflow** - 问题到任务到验证的完整工作流

Citeoryx 与 Rank Math、Yoast、AIOSEO、SEOPress 等基础 SEO 插件兼容，默认只读取、不重复输出前台标签。

== Installation ==

1. 上传 `citeoryx` 文件夹到 `/wp-content/plugins/` 目录。
2. 在 WordPress 后台“插件”菜单中启用 Citeoryx。
3. 按安装向导完成站点画像和初始内容盘点。

== Frequently Asked Questions ==

= Citeoryx 是否会替代 Rank Math / Yoast？ =

不会。Citeoryx 专注于内容健康度、机会识别和 AI 可发现性，不重复输出 Title、Description、Sitemap、Schema 等基础 SEO 标签。

= 是否需要 API Key？ =

不需要。安装后即可进行本地内容盘点和基础结构检查。Google Search Console、AI Provider 等集成为可选。

== Changelog ==

= 2.3.1 =
* 新增统一日志工具，规范调试输出和结构化上下文。
* 改进 Dashboard、内容资产和问题列表的空状态提示。
* 优化 WordPress 后台移动端布局，并为 Onboarding 表单增加实时字段验证。

= 2.3.0 =
* 已验证的 Revision 新增发布后 7/28 天搜索效果对比，按 Google Search Console 与 Bing 来源独立展示点击、展示、CTR 和平均排名变化，并标识数据收集状态。

= 2.2.4 =
* 优化器新增问题证据面板、基于本地内容索引的内链建议，以及从 Revision 审核到发布后扫描验证的完整闭环。
* 内链建议只推荐公开、无密码、尚未链接且语言兼容的内容，并提供锚文本和相关度依据。
* Revision 工作流显示待审核、已应用未发布、发布待扫描、已验证和偏离提案状态；验证扫描会同步刷新问题、评分和建议。

= 2.2.3 =
* AI 设置移动到集成页顶部，Sub2API / OpenAI Responses（Codex）入口前置并支持从错误模式一键切换。
* Responses 请求显式使用非流式模式，兼容完成事件、SSE 文本增量和常见第三方返回结构。
* 连接测试可区分 HTML 控制面板、空响应、无效 JSON、HTTP 错误和协议无文本响应。

= 2.2.2 =
* 修复集成页错误通知被双栏 Grid 拉伸成大块红色区域的问题。
* Sub2API 根地址填在普通 OpenAI 兼容模式时提示改用 Responses API，同时保持 URL 原样使用规则。

= 2.2.1 =
* AI 设置新增启用开关、10-180 秒请求超时、官方端点展示、密钥保存状态和数据发送说明。
* 禁用 AI 时保留服务商配置和加密密钥，连接测试仍可使用已保存配置。
* 优化器区分 AI 已关闭与提供商未配置，兼容旧版未发送启用字段的保存请求。

= 2.2.0 =
* 内容优化器新增 AI 深度分析入口，展示引用潜力、置信度、优势、薄弱点和可执行建议。
* AI 分析改为 Action Scheduler / WP-Cron 后台任务，支持进度恢复、活动任务去重和一小时结果保留。
* 优化器重构为宽屏双栏工作区，规则建议、AI 分析和安全 Revision 审核形成连续流程。
* AI 提供商兼容代码块或简短前缀包裹的 JSON 响应，无法解析时明确标记任务失败。

= 2.1.7 =
* 新增 OpenAI Responses API（Sub2API / Codex）模式，支持服务根地址与 `/v1/responses` 协议请求。
* AI 连接测试失败时显示安全的 HTTP 状态或网络错误，便于定位错误入口和权限问题。

= 2.1.6 =
* 兼容 AI API 现在原样使用用户填写的完整请求 URL，不再自动追加协议路径。

= 2.1.5 =
* 修复浏览器自动填充 API Key 后“测试连接”按钮仍被错误禁用的问题。

= 2.1.4 =
* 修复 AI 设置保存后状态刷新报错、导致“测试连接”按钮保持禁用的问题。

= 2.1.3 =
* AI 提供商设置新增真实连接测试，保存后可明确确认 API Key、模型和端点是否可用。

= 2.1.2 =
* 新增 Anthropic（Claude）官方 API、OpenAI 兼容 API 和 Anthropic 兼容 API 配置。
* AI 集成页支持配置模型标识和 HTTPS 自定义 API 基础地址，并扩大后台内容区域。

= 2.1.1 =
* 修复首次安装时空站点画像被 REST 接口编码为数组、导致后台无法进入引导页的问题。
* 修复报告页深链接未使用 WordPress TabPanel 正确初始页属性的问题。
* 修复 PHP 8.4 WPCS 检查中的测试数组格式错误。

= 2.1.0 =
* 完善首次站点画像、持久化后台扫描、报告页与每周邮件周报。
* 修复设置接口被可选通知状态阻断及后台直接显示 HTML 错误的问题。

= 2.0.1 =
* 修复 onboarding 保存时 profile 参数类型错误导致的 PHP 8 致命错误。

= 2.0.0 =
* 初始版本：内容资产盘点、健康引擎、问题系统、SEO 插件兼容、REST API、管理后台。
