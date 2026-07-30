# AI 开发助手通用指南

本文档为所有 AI 开发助手（Claude Code、Cursor、GitHub Copilot 等）提供统一的项目上下文和开发约束。

## 项目信息

**Citeoryx** - WordPress 内容健康度持续监控、优化与 AI 可发现性引擎

- **类型**: WordPress 插件
- **PHP**: 8.0+
- **WordPress**: 6.6+
- **架构**: DDD 三层架构（Domain / Application / Infrastructure）
- **前端**: React 18 + WordPress Components
- **命名空间**: `Citeoryx\`
- **REST API**: `/wp-json/citeoryx/v1/*`

## 核心架构原则

### 1. REST API 优先设计

所有后台管理功能通过 REST API 实现，避免零散 AJAX：

```
Controller (src/Rest/Controllers/)
  ↓
Service (src/Application/)
  ↓
Repository (src/Domain/)
  ↓
Database
```

- Controller 负责权限校验、参数验证、响应格式
- Service 负责业务逻辑
- Repository 负责数据访问

### 2. 异步任务模式

**重要**：所有长时间操作（扫描、AI 分析）必须异步执行：

```
1. REST endpoint 创建任务 → 返回 task_id
2. Action Scheduler 批次处理（50 条/批）
3. 保存进度游标，继续排队下一批
4. 前端轮询 GET /tasks/{id} 获取状态
```

实现位置：
- 队列类：`src/Infrastructure/Queue/`
- 任务表：`cx_scan_runs`, `cx_ai_analysis_tasks`

### 3. Repository 模式

**所有数据库操作必须通过 Repository**：

```php
// ✅ 正确
$content = $this->content_repository->get_by_id($id);

// ❌ 错误
global $wpdb;
$content = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}cx_content WHERE id = $id");
```

Repository 位置：`src/Domain/*/Repository.php`

### 4. Provider Adapter 模式

外部服务集成必须通过 Adapter 隔离：

```
Application Service
  ↓
AiProviderFactory::create($type)
  ↓
OpenAiProvider / AnthropicProvider / CompatibleAiProvider
```

Adapter 位置：`src/Integrations/*/`

## 命名规范

| 项目 | 规范 | 示例 |
|------|------|------|
| PHP 类名 | `CX_` + PascalCase | `CX_Content_Scanner` |
| PHP 常量 | `CX_` + UPPER_SNAKE_CASE | `CX_STATUS_HEALTHY` |
| 函数名 | `citeoryx_` + snake_case | `citeoryx_get_content()` |
| Hook 名称 | `citeoryx_` + snake_case | `citeoryx_after_scan` |
| Option 名称 | `citeoryx_` + snake_case | `citeoryx_site_profile` |
| 数据库表 | `{prefix}cx_` + snake_case | `wp_cx_content` |
| PHP 命名空间 | `Citeoryx\` + PascalCase | `Citeoryx\Domain\Content` |
| REST 路径 | `/citeoryx/v1/` + kebab-case | `/citeoryx/v1/content-scanner` |
| 文本域 | `citeoryx` | `__('Text', 'citeoryx')` |

## 安全约束

### API Key 存储优先级

```
1. wp-config.php 常量（推荐）
   define('CITEORYX_OPENAI_API_KEY', 'sk-xxx');

2. 环境变量
   CITEORYX_OPENAI_API_KEY=sk-xxx

3. 数据库加密存储（最后选择）
   使用 Sodium/OpenSSL 加密
```

### 数据库安全

```php
// ✅ 正确：使用 prepare()
$wpdb->query(
    $wpdb->prepare(
        "UPDATE {$wpdb->prefix}cx_content SET title = %s WHERE id = %d",
        $title,
        $id
    )
);

// ❌ 错误：直接拼接
$wpdb->query("UPDATE {$wpdb->prefix}cx_content SET title = '$title' WHERE id = $id");
```

### REST API 安全

```php
// 1. 检查 Nonce
if (!wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'], 'wp_rest')) {
    return new WP_Error('rest_forbidden', __('Invalid nonce', 'citeoryx'), ['status' => 403]);
}

// 2. 检查权限
if (!current_user_can('citeoryx_run_scans')) {
    return new WP_Error('rest_forbidden', __('Insufficient permissions', 'citeoryx'), ['status' => 403]);
}
```

### 外部 HTTP 请求

```php
// ✅ 正确：使用 WordPress HTTP API
$response = wp_safe_remote_get($url, [
    'timeout' => 10,
    'headers' => ['Authorization' => 'Bearer ' . $api_key],
]);

// ❌ 错误：使用 cURL
$ch = curl_init($url);
```

## SEO 插件兼容性

**核心原则：只读取、不输出**

Citeoryx 与 Rank Math / Yoast SEO / AIOSEO / SEOPress 兼容：

```
✅ 可以做：
- 读取它们的 Title / Description / Focus Keyword
- 读取它们的 Schema 数据
- 分析它们的配置

❌ 不能做：
- 输出 <title> 标签到前台
- 输出 <meta name="description"> 到前台
- 输出 Sitemap
- 输出 Schema.org JSON-LD
```

适配器位置：`src/Integrations/SeoPlugins/`

## 代码规范

### PHP

```php
<?php
/**
 * 内容扫描器服务
 *
 * @package Citeoryx\Application\Scan
 */

namespace Citeoryx\Application\Scan;

use Citeoryx\Domain\Content\ContentRepository;

class ContentScanner {
    /**
     * 内容仓储
     *
     * @var ContentRepository
     */
    private ContentRepository $content_repository;

    /**
     * 构造函数
     *
     * @param ContentRepository $content_repository 内容仓储
     */
    public function __construct(ContentRepository $content_repository) {
        $this->content_repository = $content_repository;
    }

    /**
     * 扫描指定内容
     *
     * @param int $content_id 内容 ID
     * @return array 扫描结果
     */
    public function scan(int $content_id): array {
        // 实现
    }
}
```

- 遵循 WordPress Coding Standards (WPCS)
- 所有 public/protected 方法必须有 PHPDoc
- 类型声明必须完整（参数 + 返回值）
- 复杂逻辑使用中文注释说明

### JavaScript / React

```javascript
/**
 * 内容列表组件
 *
 * @param {Object} props - 组件属性
 * @param {Array} props.items - 内容项列表
 * @param {Function} props.onSelect - 选择回调
 * @return {JSX.Element} 组件
 */
export function ContentList({ items, onSelect }) {
    const [selected, setSelected] = useState(null);

    return (
        <div className="citeoryx-content-list">
            {/* 实现 */}
        </div>
    );
}
```

- 使用 WordPress Components 库（`@wordpress/components`）
- 函数组件 + Hooks
- 使用 `@wordpress/i18n` 进行国际化
- 遵循 WordPress Scripts ESLint 配置

## 开发命令速查

### 前端开发

```bash
npm install           # 安装依赖
npm run start         # 开发模式（热重载）
npm run build         # 生产构建
npm test              # Jest 单元测试
npm run test:e2e      # Playwright E2E 测试
npm run lint:js       # JavaScript 代码检查
npm run lint:css      # CSS 代码检查
```

### PHP 开发

```bash
composer install      # 安装依赖（仅开发需要）
composer test         # PHPUnit 测试
composer phpcs        # PHP_CodeSniffer 检查
composer phpcbf       # 自动修复代码规范
composer phpcompat    # PHP 8.0+ 兼容性检查
```

### 本地环境

```bash
npm run env:start     # 启动 @wordpress/env
npm run env:stop      # 停止本地环境
```

## 常见开发任务

### 添加新的 REST 端点

1. 在 `src/Rest/Controllers/` 创建 Controller
2. 实现 `register_routes()` 方法
3. 声明 `get_endpoint_args_for_item_schema()` 定义参数
4. 在 `src/Rest/Router.php` 注册 Controller

### 添加新的 Repository 方法

1. 在 `src/Domain/*/Repository.php` 添加方法
2. 使用 `$wpdb->prepare()` 构建查询
3. 添加完整的类型声明和 PHPDoc
4. 在 `tests/Unit/` 编写 PHPUnit 测试

### 添加后台异步任务

1. 在 `src/Infrastructure/Queue/` 创建任务类
2. 通过 Action Scheduler 或 WP-Cron 调度
3. 批次处理（每批最多 50 条）
4. 每批完成后保存游标并继续排队

### 添加外部服务集成

1. 在 `src/Integrations/` 创建 Provider/Adapter
2. 实现统一接口（如 `AiProviderInterface`）
3. 在 Factory 类中注册（如 `AiProviderFactory`）
4. 在设置页面添加配置选项

## 测试要求

### 单元测试

- **PHP**: `tests/Unit/` - PHPUnit
- **JavaScript**: `assets/src/admin/*.test.js` - Jest
- 所有 Repository 方法必须有测试
- 所有 Service 核心逻辑必须有测试

### 集成测试

- **位置**: `tests/Integration/`
- 测试 WordPress 集成点（Hook、Filter、Shortcode）
- 测试完整的 REST API 流程

### E2E 测试

- **位置**: `tests/e2e/`
- **工具**: Playwright
- 测试关键用户流程（扫描、优化、报告）

## 相关文档

| 文档 | 用途 |
|------|------|
| `ARCHITECTURE.md` | 详细架构设计与分层说明 |
| `API.md` | REST API 完整参考文档 |
| `DATABASE.md` | 数据库表结构设计 |
| `TESTING.md` | 测试环境配置指南 |
| `CLAUDE.md` | Claude Code 专用指南 |
| `.cursorrules` | Cursor 编辑器配置 |
| `.github/copilot-instructions.md` | GitHub Copilot 配置 |

## AI 工具特定配置

- **Claude Code**: 参考 `CLAUDE.md`
- **Cursor**: 参考 `.cursorrules`
- **GitHub Copilot**: 参考 `.github/copilot-instructions.md`

各工具配置文件内容与本文档保持一致，仅格式略有差异。
