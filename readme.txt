=== Citeoryx ===
Contributors: everettlabs
Tags: content-health, ai-discoverability, seo, content-inventory, gsc
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 2.0.1
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

= 2.0.1 =
* 修复 onboarding 保存时 profile 参数类型错误导致的 PHP 8 致命错误。

= 2.0.0 =
* 初始版本：内容资产盘点、健康引擎、问题系统、SEO 插件兼容、REST API、管理后台。
