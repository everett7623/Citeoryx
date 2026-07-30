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
Long-running operations (scans, AI analysis) use Action Scheduler:
1. REST endpoint creates task → returns `task_id` immediately
2. Action Scheduler processes in batches (50 items/batch)
3. Frontend polls status via `GET /scans/{id}`
4. Task progress persisted in `cx_scan_runs` table

### Repository Pattern
Database access isolated in Repository classes (`src/Domain/*/Repository.php`):
- `ContentRepository` - Content assets
- `IssueRepository` - Issue records
- `MetricsRepository` - Search metrics
- `LinkRepository` - Link relationships

All queries use `$wpdb->prepare()` prepared statements.

### Provider Adapter Pattern
External integrations isolated in Adapters (`src/Integrations/`):
- `AiProviders/` - OpenAI / Anthropic / Compatible APIs
- `Google/` - Google Search Console
- `Bing/` - Bing Webmaster Tools
- `SeoPlugins/` - Rank Math / Yoast / AIOSEO adapters

## Naming Conventions

| Item | Convention | Example |
|------|------------|---------|
| PHP Classes/Constants | `CX_` prefix | `CX_Issue`, `CX_STATUS_HEALTHY` |
| Functions/Hooks/Options | `citeoryx_` prefix | `citeoryx_get_content()` |
| Database Tables | `{$wpdb->prefix}cx_` | `wp_cx_content` |
| PHP Namespace | `Citeoryx\` | `Citeoryx\Domain\Content` |
| REST Namespace | `/citeoryx/v1/` | `/wp-json/citeoryx/v1/content` |
| Text Domain | `citeoryx` | `__('Text', 'citeoryx')` |

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
1. Create Controller in `src/Rest/Controllers/`
2. Implement `register_routes()` method
3. Add schema via `get_endpoint_args_for_item_schema()`
4. Register in `src/Rest/Router.php`

### Add New Repository Method
1. Add method to Repository class in `src/Domain/*/`
2. Use `$wpdb->prepare()` for queries
3. Add type declarations (params + return)
4. Add PHPUnit test in `tests/Unit/`

### Add New Background Task
1. Create task class in `src/Infrastructure/Queue/`
2. Schedule via Action Scheduler or WP-Cron
3. Process in batches (max 50 items)
4. Save progress cursor after each batch

## SEO Plugin Compatibility

Citeoryx **reads but does not output** SEO tags to avoid conflicts:
- Compatible with: Rank Math, Yoast SEO, AIOSEO, SEOPress
- Reads their Title / Description / Schema via adapters
- Does NOT output: Title, Description, Sitemap, Schema to frontend

Adapters location: `src/Integrations/SeoPlugins/`

## Testing

- Unit tests: `tests/Unit/` (PHPUnit)
- Integration tests: `tests/Integration/` (WordPress + PHPUnit)
- JS tests: `assets/src/admin/*.test.js` (Jest)
- E2E tests: `tests/e2e/` (Playwright)

Run tests:
```bash
composer test                    # All PHP tests
npm test                         # All JS tests
vendor/bin/phpunit tests/Unit/ContentRepositoryTest.php  # Single test
```

## Documentation

- `ARCHITECTURE.md` - Detailed architecture and layers
- `API.md` - Complete REST API reference
- `DATABASE.md` - Database schema
- `TESTING.md` - Testing setup guide
- `CLAUDE.md` - Claude Code development guide
