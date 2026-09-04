# GitHub Copilot Instructions for Citeoryx

This file provides context and guidelines for GitHub Copilot when working in this repository.

## Project Overview

Citeoryx is a WordPress plugin for content health monitoring, optimization, and AI discoverability.

- **Type**: WordPress Plugin
- **PHP Version**: 8.0+
- **WordPress Version**: 6.6+
- **Architecture**: DDD (Domain / Application / Infrastructure)
- **Frontend**: React + WordPress Components
- **Namespace**: `Citeoryx\`

## Core Patterns

### REST API First
All admin features use REST API (`/wp-json/citeoryx/v1/*`), not scattered AJAX calls.

### Async Task Pattern
Long-running operations (scans, AI analysis) run in the background:
1. REST endpoint creates the task → returns immediately
2. Action Scheduler processes bounded batches when installed; otherwise WP-Cron single events
3. Frontend polls status via `GET /scans/{id}`
4. Scan progress persisted in the `cx_scan_runs` table; AI analysis tasks live in a
   1-hour Transient via `AiAnalysisTaskStore` (there is no AI task table)

### Repository Pattern
Database access isolated in Repository classes under `src/Domain/*/`:
- `ContentRepository` - Content assets
- `IssueRepository` - Issue records
- `MetricsRepository` - Search metrics
- `LinkRepository` - Link relationships
- `ScanRunRepository` - Scan task runs
- `OpportunityRepository` / `CalendarRepository` - Planning

All queries use `$wpdb->prepare()` prepared statements.

### Provider Adapter Pattern
External integrations isolated in Adapters (`src/Integrations/`):
- `AiProviders/` - OpenAI / Anthropic / DeepSeek / Compatible APIs
- `SearchConsole/` - Google Search Console **and** Bing Webmaster Tools (both live here)
- `SeoPlugins/` - Rank Math / Yoast / AIOSEO / SEOPress adapters

## Naming Conventions

| Item | Convention | Example |
|------|------------|---------|
| PHP Classes | Plain PSR-4, **no prefix** | `Citeoryx\Domain\Content\ContentRepository` |
| PHP Global Constants | `CITEORYX_` prefix | `CITEORYX_DB_VERSION`, `CITEORYX_TABLE_ISSUES` |
| `CX_` prefix | **Issue code strings only** | `CX_CONTENT_STALE`, `CX_LINK_ORPHANED` |
| Functions/Hooks/Options | `citeoryx_` prefix | `citeoryx_scan_completed` |
| Database Tables | `{$wpdb->prefix}cx_` | `wp_cx_content_items`, `wp_cx_metrics_daily` |
| PHP Namespace | `Citeoryx\` | `Citeoryx\Domain\Content` |
| REST Namespace | `/citeoryx/v1/` | `/wp-json/citeoryx/v1/content` |
| Text Domain | `citeoryx` | `__('Text', 'citeoryx')` |

Real table names come from `src/Core/constants.php` (`CITEORYX_TABLE_*`): `cx_content_items`, `cx_metrics_daily`, `cx_query_pages`, `cx_issues`, `cx_links`, `cx_scan_runs`, `cx_ai_prompt_runs`.

## Security Requirements

1. **API Keys**: Priority order
   - `wp-config.php` constants (e.g., `CITEORYX_OPENAI_API_KEY`)
   - Environment variables
   - Encrypted database storage (Sodium/OpenSSL)

2. **External HTTP Requests**
   - Use `wp_safe_remote_get()` / `wp_safe_remote_post()`
   - Validate target domains (prevent SSRF)

3. **Database Operations**
   - Always use `$wpdb->prepare()` for dynamic values
   - Type-check in Repository methods

4. **REST API**
   - Require WordPress Nonce (`X-WP-Nonce` header)
   - Check custom capabilities (`citeoryx_*`)

## Code Standards

### PHP
- Follow WordPress Coding Standards (WPCS)
- PHP 8.0+ compatibility required
- Type declarations for all Repository methods
- Use `esc_*()` and `sanitize_*()` functions

### JavaScript
- WordPress Scripts ESLint configuration
- React functional components with hooks
- WordPress Components library preferred

### Comments
- PHPDoc blocks for all classes and public methods
- JSDoc for exported functions
- Code comments in Simplified Chinese for complex logic

## Common Tasks

### Add New REST Endpoint
1. Create Controller in `src/Rest/Controllers/` extending `BaseController`
2. Implement `register( string $namespace )` — **not** `register_routes()`
3. Validate params manually inside the callback (sanitize + allowlist). This project does **not** use `get_endpoint_args_for_item_schema()`
4. Register in `src/Rest/Router.php`
5. Return via `$this->success( $data )` / `$this->error( $message, $status )`
6. Call `RestResponseCache::invalidate()` from any write that changes aggregate data

### Add New Repository Method
1. Add method to Repository class in `src/Domain/*/`
2. Use `$wpdb->prepare()` for queries
3. Add type declarations (params + return)
4. Add PHPUnit test in `tests/Unit/`

### Add New Background Task
1. Create task class in `src/Infrastructure/Queue/`
2. Schedule via Action Scheduler when available, falling back to WP-Cron — copy the
   `function_exists( 'as_enqueue_async_action' )` guard used by `Scheduler::schedule_*()`
3. Process in bounded batches. Sizes differ by IO cost: scan 50, health recalc 50,
   link check 5, search metrics import 20
4. Save progress cursor after each batch (`config_json.offset` for scans, immutable
   `after_id` for health / links / metrics)

## SEO Plugin Compatibility

Citeoryx **reads but does not output** SEO tags to avoid conflicts:
- Compatible with: Rank Math, Yoast SEO, AIOSEO, SEOPress
- Reads their Title / Description / Schema via adapters
- Does NOT output: Title, Description, Sitemap, Schema to frontend

Adapters location: `src/Integrations/SeoPlugins/`

## Testing

- Unit tests: `tests/Unit/` (PHPUnit)
- WordPress integration coverage: `tests/Unit/` (WordPress test suite + PHPUnit)
- JS tests: `assets/src/admin/*.test.js` (Jest)
- E2E tests: `e2e/` (Playwright)

Run tests:
```bash
composer test                    # All PHP tests
npm test                         # All JS tests
npm run check:project            # Version and repository hygiene checks
vendor/bin/phpunit tests/Unit/ContentRepositoryTest.php  # Single test
```

## Documentation

- `ARCHITECTURE.md` - Detailed architecture and layers
- `API.md` - Complete REST API reference
- `DATABASE.md` - Database schema
- `TESTING.md` - Testing setup guide
- `CLAUDE.md` - Claude Code development guide
