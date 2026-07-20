# Changelog

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
