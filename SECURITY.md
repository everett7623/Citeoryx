# Security Policy

## 支持版本

| 版本 | 支持状态 |
|---|---|
| 2.0.x | 活跃支持 |
| 1.x（RankDraft / ContentPilot） | 仅关键安全更新 |

## 报告安全漏洞

请勿在公开 Issue 中披露安全漏洞。请发送邮件至 security@citeoryx.com，包含：

- 漏洞描述
- 复现步骤
- 受影响版本
- 潜在影响评估
- 建议修复方向（如有）

## 安全设计原则

- REST API 使用 WordPress Nonce 和自定义 Capability 校验；
- 外部请求通过 `wp_safe_remote_*` 执行，并实施 SSRF 防护；
- API Key 优先通过 `wp-config.php` 常量或环境变量配置；
- 存储的 API Key 使用 Sodium / OpenSSL 加密；
- 后台只显示掩码后的 API Key；
- 日志中禁止记录完整 API Key；
- 所有数据库操作使用 Prepared Statements。

## 已知风险缓解

| 风险 | 缓解 |
|---|---|
| GSC 数据延迟 | 明确数据截止日期，不显示“实时” |
| AI 建议幻觉 | 默认不自动发布，标注待核实事实 |
| Schema 重复 | 默认只读适配，不重复输出前台 JSON-LD |
| Cron 不稳定 | Action Scheduler + Site Health 检测 |
| 大站点资源 | 分批、锁、限速、增量扫描 |
| 第三方 API 变更 | Adapter + 能力检测 + 降级 |
