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
    "plugin_version": "2.0.1"
  }
}
```

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

- `status` - `open` / `resolved` / `ignored`
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

### Settings

| 方法 | 端点 | 说明 |
|---|---|---|
| GET | `/settings` | 获取设置和站点画像 |
| POST | `/settings` | 更新设置和站点画像 |

POST Body：

```json
{
  "settings": { "auto_scan": true, "remove_data_on_uninstall": false },
  "profile": { "site_type": "blog", "primary_goal": "traffic" }
}
```

### Optimizer

| 方法 | 端点 | 说明 |
|---|---|---|
| GET | `/optimizer/{id}` | 获取内容优化建议、评分与相关问题 |

响应数据：

```json
{
  "success": true,
  "data": {
    "content": { "id": 1, "canonical_url": "..." },
    "scores": { "health": { "score": 82 }, "aeo": { "score": 71 } },
    "issues": [ ... ],
    "recommendations": [
      { "category": "content", "priority": "high", "title": "...", "description": "...", "action": "..." }
    ]
  }
}
```

### Integrations

All integration configuration endpoints require `citeoryx_manage_integrations`. AI analysis requires `citeoryx_use_ai`.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/integrations/gsc` | Get Google Search Console connection state and callback URI |
| POST | `/integrations/gsc/client` | Save Google OAuth client credentials and return the authorization URL |
| POST | `/integrations/gsc/disconnect` | Remove stored Google OAuth tokens |
| GET | `/integrations/gsc/metrics` | Get Google Search Console query metrics for a date range |
| GET | `/integrations/gsc/queries?url={url}` | Get search queries for one canonical URL |
| GET | `/integrations/gsc/sites` | List Search Console sites available to the connected account |
| GET | `/integrations/ai` | Get configured AI provider state without exposing secrets |
| POST | `/integrations/ai/settings` | Configure the active AI provider and store its API key encrypted |
| POST | `/integrations/ai/analyze/{id}` | Generate AI improvement and discoverability analysis for a content item |

`POST /integrations/gsc/client` body:

```json
{
  "client_id": "Google OAuth client ID",
  "client_secret": "Google OAuth client secret"
}
```

`POST /integrations/ai/settings` body:

```json
{
  "provider": "openai",
  "api_key": "sk-..."
}
```
