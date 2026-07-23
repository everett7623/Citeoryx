# Citeoryx REST API 参考

命名空间：`/wp-json/citeoryx/v1/`

所有端点需要有效的 WordPress REST Nonce（`X-WP-Nonce` 头或 `_wpnonce` 参数），并校验自定义 Capability。

## 权限

| Capability | 说明 |
|---|---|
| `citeoryx_view_dashboard` | 查看总览 |
| `citeoryx_view_content` | 查看内容资产 |
| `citeoryx_run_scans` | 运行扫描 |
| `citeoryx_manage_issues` | 管理问题 |
| `citeoryx_use_ai` | 使用 AI 功能 |
| `citeoryx_apply_changes` | 应用变更 |
| `citeoryx_manage_integrations` | 管理集成 |
| `citeoryx_manage_settings` | 管理设置 |
| `citeoryx_export_data` | 导出数据 |

`citeoryx_view_dashboard` 表示可查看全站聚合与全部内容。没有该权限但拥有 `citeoryx_view_content` 的用户会被限制为仅访问其本人创建的 WordPress 内容：Author 可处理本人内容的问题，Contributor 仅可查看本人内容与优化建议。列表查询和单条 REST 请求都会在服务端校验该范围。

## 端点

### Dashboard

| 方法 | 端点 | 说明 |
|---|---|---|
| GET | `/dashboard` | 总览数据 |

响应字段：

```json
{
  "success": true,
  "data": {
    "status_counts": { "healthy": 10, "orphaned": 3 },
    "total_content": 50,
    "open_issues": [...],
    "high_priority": [...],
    "recent_scans": [...],
    "seo_plugin": "rank-math",
    "plugin_version": "2.1.7"
  }
}
```

### Reports

| 方法 | 端点 | 说明 |
|---|---|---|
| GET | `/reports/summary` | 获取可展示、可导出的站点汇总报告 |

需要 `citeoryx_view_dashboard`。响应中的分布字段使用稳定的 `{label,count}` 数组，空结果返回 `[]`；尚无可计算分数时，平均分返回 `null`。

后台 PDF 导出复用此响应，不新增二进制 REST 端点：具备 `citeoryx_export_data` 权限的用户可在浏览器端生成 A4 PDF。导出范围固定为响应已有的 28 天趋势、各维度最多 20 条、优先问题和最近扫描各 5 条，不触发额外查询或服务器临时文件。

```json
{
  "success": true,
  "data": {
    "generated_at": "2026-07-21 12:00:00",
    "content": {
      "total": 50,
      "status_counts": [{ "label": "healthy", "count": 30 }],
      "average_health_score": 78.25,
      "average_ai_readiness_score": 66.5,
      "last_scanned_at": "2026-07-21 11:30:00"
    },
    "issues": {
      "open_total": 8,
      "severity_counts": [{ "label": "high", "count": 2 }],
      "category_counts": [{ "label": "content", "count": 4 }],
      "top_items": []
    },
    "scans": { "recent": [] },
    "plugin": { "version": "2.1.7", "seo_plugin": "rank-math" }
  }
}
```

### Planning

| 方法 | 端点 | 说明 |
|---|---|---|
| GET | `/planning/opportunities` | 基于本地 Google/Bing 查询快照发现主题机会 |
| GET | `/planning/calendar` | 获取 WordPress 定时发布计划与到期复核提醒 |
| POST | `/planning/reviews/{id}/complete` | 将内容标记为已复核 |

需要 `citeoryx_view_dashboard`。支持 `page`、`per_page`、`days`（7–90）、`source` 和 `type` 筛选；`type` 可为 `striking_distance`、`refresh_before_new` 或 `topic_gap_candidate`。服务端单次最多聚合 1000 个查询×页面候选，达到上限时 `summary.data_limited` 为 `true`。

```json
{
  "success": true,
  "data": {
    "items": [{
      "id": "stable-hash",
      "type": "striking_distance",
      "issue_code": "CX_PLAN_EXISTING_PAGE_MATCH",
      "query": "example query",
      "source": "google_search_console",
      "confidence": "high",
      "recommended_action": "improve_existing",
      "priority_score": 81,
      "metrics": { "impressions": 120, "clicks": 8, "ctr": 0.066667, "position_avg": 8.4, "period_days": 28 },
      "evidence": ["Best page ranks between positions 4 and 15 with meaningful impressions."],
      "pages": [{ "content_id": 1, "url": "https://example.com/page", "status": "healthy", "position_avg": 8.4 }]
    }],
    "pagination": { "page": 1, "per_page": 20, "total": 1, "total_pages": 1 },
    "summary": { "total": 1, "type_counts": [{ "label": "striking_distance", "count": 1 }], "data_limited": false },
    "generated_at": "2026-07-22 12:00:00"
  }
}
```

`topic_gap_candidate` 仅表示没有已导入页面进入前 15，置信度固定为 `low`；创建新内容前仍需人工核对搜索意图。端点不会创建或修改 WordPress 内容。

`GET /planning/calendar` 支持 `horizon_days`（7–365，默认 90）和 `limit`（1–100，默认 50）。`scheduled.items` 直接读取 WordPress `future` 内容；`overdue_reviews.items` 按站点画像的 `review_cycle_days` 计算。日期均为带偏移的 ISO 8601，并在 `timezone` 中声明 WordPress 站点时区。

```json
{
  "success": true,
  "data": {
    "as_of": "2026-07-22T12:00:00+08:00",
    "timezone": "Asia/Shanghai",
    "horizon_days": 90,
    "review_cycle_days": 90,
    "scheduled": {
      "items": [{ "id": 10, "title": "Scheduled post", "publish_at": "2026-08-01T09:00:00+08:00", "edit_url": "..." }],
      "data_limited": false
    },
    "overdue_reviews": {
      "items": [{ "content_id": 3, "title": "Old page", "due_at": "2026-07-01T10:00:00+08:00", "overdue_days": 21 }],
      "data_limited": false
    }
  }
}
```

复核基准依次回退到 `last_reviewed_at`、`modified_at`、`published_at`、`created_at`。`POST /planning/reviews/{id}/complete` 需要 `citeoryx_view_dashboard` 与 `citeoryx_manage_issues`；该操作只更新时间戳，不修改正文、发布状态或内容健康状态。

### Content

| 方法 | 端点 | 说明 |
|---|---|---|
| GET | `/content` | 内容资产列表 |
| GET | `/content/{id}` | 单页详情 |
| POST | `/content/{id}/scan` | 扫描单页 |

Query 参数（`/content`）：

- `status` - 内容状态
- `post_type` - 文章类型
- `search` - URL 搜索
- `page`
- `per_page`

### Issues

| 方法 | 端点 | 说明 |
|---|---|---|
| GET | `/issues` | 问题列表 |
| PATCH | `/issues/{id}` | 更新问题状态、负责人或忽略时间 |

Query 参数（`/issues`）：

- `status` - `open` / `in_progress` / `resolved` / `ignored`
- `category`
- `severity`
- `content_id`
- `page`
- `per_page`

PATCH Body：

```json
{
  "status": "resolved",
  "assigned_user_id": 1,
  "ignored_until": "2026-12-31 23:59:59"
}
```

### Scans

| 方法 | 端点 | 说明 |
|---|---|---|
| POST | `/scans` | 创建扫描任务 |
| GET | `/scans/{id}` | 查询进度 |

POST Body：

```json
{ "scan_type": "full" }
```

`scan_type` 支持 `full` 和 `incremental`。接口只创建后台任务并返回 HTTP 202；任务状态通过 `GET /scans/{id}` 查询。重复触发时，如果已有 `queued` 或 `running` 任务，接口返回该任务而不会并行创建第二个全量扫描。未传 `per_page` 的列表接口默认返回 20 条。

扫描任务状态包括 `queued`、`running`、`completed`、`failed` 和 `cancelled`，`processed_items`、`failed_items` 与 `total_items` 用于展示进度。

### Settings

| 方法 | 端点 | 说明 |
|---|---|---|
| GET | `/settings` | 获取设置和站点画像 |
| POST | `/settings` | 更新设置和站点画像 |

POST Body：

```json
{
  "settings": {
    "auto_scan": true,
    "remove_data_on_uninstall": false,
    "weekly_digest_enabled": true,
    "critical_alerts_enabled": true,
    "notification_email": "owner@example.com"
  },
  "profile": {
    "site_type": "blog",
    "primary_goal": "traffic",
    "core_content_types": ["post", "page"],
    "main_language": "zh_CN",
    "main_region": "全球",
    "update_rhythm": "monthly",
    "risk_level": "standard",
    "review_cycle_days": 90
  }
}
```

GET 与 POST 使用相同的成功响应结构：

```json
{
  "success": true,
  "data": {
    "settings": {
      "auto_scan": true,
      "remove_data_on_uninstall": false,
      "weekly_digest_enabled": true,
      "critical_alerts_enabled": true,
      "notification_email": "owner@example.com"
    },
    "profile": { "site_type": "blog", "core_content_types": ["post", "page"] },
    "profile_complete": true,
    "profile_options": {
      "content_types": [
        { "value": "post", "label": "文章" },
        { "value": "page", "label": "页面" }
      ],
      "defaults": { "main_language": "zh_CN", "review_cycle_days": 90 }
    },
    "notification_status": {
      "status": "sent",
      "message": "WordPress 已接受邮件发送请求。",
      "attempted_at": "2026-07-21T09:00:00+08:00",
      "recipient": "owner@example.com"
    },
    "critical_alert_status": {
      "status": "sent",
      "message": "WordPress 已接受严重问题通知。",
      "attempted_at": "2026-07-22T14:30:00+08:00",
      "recipient": "owner@example.com",
      "issue_count": 3
    }
  }
}
```

`profile_options.content_types` 来自当前站点真实注册、公开且有后台界面的内容类型，并排除媒体附件。POST 会校验必填字段、枚举、内容类型、通知邮箱和设置值类型；校验失败返回 HTTP 400，且不会更新任何设置。

`notification_status` 与 `critical_alert_status` 是设置页的附加状态。通知服务读取异常时对应字段降级为 `status: "never"`，不会阻断首次站点画像加载；服务器在开启 `WP_DEBUG` 时记录内部异常。严重问题通知在完整或增量扫描完成后执行，汇总 `critical`/`high` 且状态为 `open`/`in_progress` 的问题；相同问题集合不会重复发送。

### Notifications

| 方法 | 端点 | 说明 |
|---|---|---|
| POST | `/notifications/test` | 向配置邮箱或请求指定邮箱发送测试周报 |

需要 `citeoryx_manage_settings`。请求体中的 `email` 可省略；省略时使用已保存的 `notification_email`。非法邮箱返回 HTTP 400，WordPress 邮件系统拒绝请求时返回 HTTP 502。

```json
{ "email": "owner@example.com" }
```

成功响应：

```json
{
  "success": true,
  "data": {
    "status": "sent",
    "message": "WordPress 已接受邮件发送请求。",
    "attempted_at": "2026-07-21T09:00:00+08:00",
    "recipient": "owner@example.com"
  }
}
```

`sent` 只表示 `wp_mail()` 接受了发送请求，不保证下游 SMTP 已最终投递。自动周报默认关闭；开启后使用单次 WordPress Cron，在每次执行后按站点时区重新计算下周一 09:00，并通过 ISO 周期键避免重复发送。

### Optimizer

| 方法 | 端点 | 说明 |
|---|---|---|
| GET | `/optimizer/{id}` | 获取内容优化建议、评分与相关问题 |
| POST | `/recommendations/apply` | 创建供人工审核的 WordPress Revision，不修改父内容 |

响应数据：

```json
{
  "success": true,
  "data": {
    "content": { "id": 1, "canonical_url": "..." },
    "editor": {
      "available": true,
      "post_id": 42,
      "title": "Current title",
      "content": "<!-- wp:paragraph --><p>Current content</p><!-- /wp:paragraph -->",
      "excerpt": "Current excerpt",
      "base_content_hash": "64-character SHA-256 snapshot token",
      "revisions_enabled": true,
      "message": "",
      "edit_url": "https://example.com/wp-admin/post.php?post=42&action=edit"
    },
    "scores": { "health": { "score": 82 }, "aeo": { "score": 71 } },
    "issues": [ ... ],
    "recommendations": [
      { "category": "content", "priority": "high", "title": "...", "description": "...", "action": "..." }
    ]
  }
}
```

`editor.base_content_hash` 同时覆盖标题、摘要和正文，用于检测用户打开优化器后发生的并发编辑。无法映射到 WordPress 内容时，`editor` 保持对象结构并返回 `available: false` 和用户可读的 `message`。

`POST /recommendations/apply` 需要 `citeoryx_apply_changes`，同时要求当前用户具备目标文章的 WordPress `edit_post` 权限。

请求体：

```json
{
  "content_id": 1,
  "title": "Proposed title",
  "content": "<!-- wp:paragraph --><p>Proposed content</p><!-- /wp:paragraph -->",
  "excerpt": "Proposed excerpt",
  "base_content_hash": "64-character SHA-256 snapshot token",
  "summary": "Updated examples and evidence"
}
```

创建成功返回 HTTP 201：

```json
{
  "success": true,
  "data": {
    "revision": {
      "id": 81,
      "parent_id": 42,
      "created_at": "2026-07-22T11:30:00",
      "author_id": 3,
      "compare_url": "https://example.com/wp-admin/revision.php?revision=81",
      "edit_url": "https://example.com/wp-admin/post.php?post=42&action=edit",
      "created": true
    }
  }
}
```

相同基础版本和相同提案重复提交时返回已有 Revision、HTTP 200 且 `created: false`。基础快照已变化时返回 HTTP 409；站点禁用 Revision 时也返回 HTTP 409。接口只插入 `post_type=revision` 的子记录，不调用父文章更新或发布操作。

### Integrations

All integration configuration endpoints require `citeoryx_manage_integrations`. AI analysis requires `citeoryx_use_ai`.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/integrations/gsc` | Get Google Search Console connection state and callback URI |
| POST | `/integrations/gsc/client` | Save Google OAuth client credentials and return the authorization URL |
| POST | `/integrations/gsc/disconnect` | Remove stored Google OAuth tokens |
| POST | `/integrations/gsc/validate` | Validate Google API access and update connection health |
| GET | `/integrations/gsc/metrics` | Get Google Search Console query metrics for a date range |
| GET | `/integrations/gsc/queries?url={url}` | Get search queries for one canonical URL |
| GET | `/integrations/gsc/sites` | List Search Console sites available to the connected account |
| GET | `/integrations/ai` | Get configured AI provider state without exposing secrets |
| POST | `/integrations/ai/settings` | Configure OpenAI, Anthropic, DeepSeek, OpenAI-compatible, OpenAI Responses-compatible, Anthropic-compatible, or `none`; API keys are stored encrypted |
| POST | `/integrations/ai/validate` | Send a minimal request with the saved provider and return an explicit connection result |
| POST | `/integrations/ai/analyze/{id}` | Generate AI improvement and discoverability analysis for a content item |
| GET | `/integrations/bing` | Get Bing Webmaster Tools connection state |
| POST | `/integrations/bing/settings` | Save Bing Webmaster Tools API key |
| POST | `/integrations/bing/disconnect` | Remove stored Bing API key |
| POST | `/integrations/bing/validate` | Validate Bing API access and update connection health |
| GET | `/integrations/bing/metrics` | Get the available Bing query statistics (Bing supplies the statistic dates) |
| GET | `/integrations/bing/queries?url={url}` | Get the available Bing query statistics for one canonical URL |
| GET | `/integrations/bing/sites` | List Bing Webmaster Tools sites |

`POST /integrations/gsc/client` body:

```json
{
  "client_id": "Google OAuth client ID",
  "client_secret": "Google OAuth client secret"
}
```

Search Console imports run daily. Google imports the most recently finalized day with a three-day reporting delay. Bing's traffic-statistics endpoints return their own dated, typically weekly snapshots; Citeoryx upserts those provider dates instead of assigning the daily import date. The plugin saves per-content snapshots in local metrics storage and exposes a combined 28-day `performance` aggregate from `GET /reports/summary`.

`performance.history` contains daily site totals. `performance.dimensions` contains the top 20 aggregated `queries`, `countries`, and `devices`, with `label`, `clicks`, `impressions`, `ctr`, and impression-weighted `position_avg`. Query items also include `source`. Google imports the top 100 query/country/device combinations for each content URL and day; Bing currently contributes query rows without country or device values.

The Google and Bing status responses include a `health` object with `status`, `message`, `checked_at`, `consecutive_failures`, and `last_success_at`. A successful validation or scheduled import request resets the failure streak; two consecutive failures produce an admin notice. A valid response with no rows or sites is treated as healthy.

Google and Bing requests retry transient network errors, HTTP 429, and HTTP 5xx responses up to three total attempts. Backoff starts at 250 ms, honors numeric or HTTP-date `Retry-After` values, and caps each synchronous delay at two seconds. Other HTTP 4xx responses are returned immediately without retrying.

`POST /integrations/ai/settings` body:

```json
{
  "provider": "anthropic_compatible",
  "api_key": "provider-secret",
  "model": "claude-haiku-4-5-20251001",
  "base_url": "https://api.example.com/custom-endpoint?route=sub2api"
}
```

`provider` 可为 `openai`、`anthropic`、`deepseek`、`openai_compatible`、`openai_responses`、`anthropic_compatible` 或 `none`。官方提供商的 `model` 可省略并使用默认值。`openai_compatible` 与 `anthropic_compatible` 必须同时提交 `model` 与完整 HTTPS 请求 URL `base_url`，系统会原样使用该 URL。`openai_responses` 用于 Sub2API/Codex 等 Responses 协议网关，`base_url` 填 HTTPS 服务根地址，系统请求其 `/v1/responses`。兼容 URL 允许查询参数，但不得包含用户名、密码或片段。密钥不出现在任何 GET 响应中。

`POST /integrations/ai/validate` 使用当前已保存的提供商、模型、端点和加密 API Key 发送一次最小请求，返回 `data.valid` 与可展示的 `data.message`。该响应不会包含密钥或第三方响应正文。
