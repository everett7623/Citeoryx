# Citeoryx 数据库设计

为兼容 WordPress 支持的旧数据库，不强制使用原生 JSON 字段；结构化数据使用 `LONGTEXT` 保存 JSON，并通过应用层校验。

## 表清单

| 表名 | 说明 |
|---|---|
| `{prefix}cx_content_items` | 内容资产主表 |
| `{prefix}cx_metrics_daily` | 每日表现数据 |
| `{prefix}cx_query_pages` | 查询与页面关系 |
| `{prefix}cx_issues` | 问题与机会 |
| `{prefix}cx_links` | 内部 / 外部链接图谱 |
| `{prefix}cx_scan_runs` | 扫描任务日志 |
| `{prefix}cx_ai_prompt_runs` | AI Prompt 观察记录 |

## 内容资产表

```sql
CREATE TABLE {prefix}cx_content_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  object_id BIGINT UNSIGNED NULL,
  object_type VARCHAR(50) NOT NULL,
  post_type VARCHAR(50) NULL,
  canonical_url VARCHAR(2048) NOT NULL,
  url_hash CHAR(32) NOT NULL,
  language_code VARCHAR(20) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'unknown',
  health_score DECIMAL(5,2) NULL,
  health_confidence VARCHAR(20) NULL,
  ai_readiness_score DECIMAL(5,2) NULL,
  content_hash CHAR(64) NULL,
  published_at DATETIME NULL,
  modified_at DATETIME NULL,
  last_scanned_at DATETIME NULL,
  last_reviewed_at DATETIME NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  metadata_json LONGTEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_url_hash (url_hash),
  KEY idx_object (object_type, object_id),
  KEY idx_status (status),
  KEY idx_health (health_score),
  KEY idx_modified (modified_at)
);
```

## 每日表现表

```sql
CREATE TABLE {prefix}cx_metrics_daily (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  content_id BIGINT UNSIGNED NOT NULL,
  metric_date DATE NOT NULL,
  source VARCHAR(30) NOT NULL,
  impressions DECIMAL(14,2) NULL,
  clicks DECIMAL(14,2) NULL,
  ctr DECIMAL(8,6) NULL,
  position_avg DECIMAL(8,3) NULL,
  sessions DECIMAL(14,2) NULL,
  conversions DECIMAL(14,2) NULL,
  revenue DECIMAL(14,2) NULL,
  extra_json LONGTEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_content_date_source (content_id, metric_date, source),
  KEY idx_metric_date (metric_date),
  KEY idx_content (content_id)
);
```

## 问题表

```sql
CREATE TABLE {prefix}cx_issues (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  content_id BIGINT UNSIGNED NULL,
  issue_code VARCHAR(100) NOT NULL,
  category VARCHAR(50) NOT NULL,
  severity VARCHAR(20) NOT NULL,
  confidence VARCHAR(20) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  impact_score DECIMAL(5,2) NULL,
  effort_score DECIMAL(5,2) NULL,
  priority_score DECIMAL(8,3) NULL,
  title VARCHAR(500) NOT NULL,
  evidence_json LONGTEXT NULL,
  recommendation LONGTEXT NULL,
  first_seen_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  resolved_at DATETIME NULL,
  ignored_until DATETIME NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_content_status (content_id, status),
  KEY idx_issue_code (issue_code),
  KEY idx_priority (priority_score),
  KEY idx_category (category)
);
```

完整建表语句参见 `src/Infrastructure/Database/SchemaManager.php`。

## 迁移策略

- 数据库版本存储于 `citeoryx_db_version` option；
- `SchemaManager::maybe_upgrade()` 在 `plugins_loaded` 时检查并升级；
- 使用 `dbDelta()` 兼容旧版 WordPress / MySQL。
