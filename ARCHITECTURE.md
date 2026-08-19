# Citeoryx 架构设计

## 设计原则

- 模块化单体；
- Domain / Application / Infrastructure 分层；
- PSR-4 自动加载；
- REST API 优先，减少零散 AJAX；
- Action Scheduler 处理后台任务；
- WordPress Cron 仅负责触发；
- Repository 隔离数据库；
- Provider Adapter 隔离外部服务；
- Feature Flag 控制实验功能；
- 所有扫描增量化、可恢复、可追踪；REST 只创建任务，后台按批次推进并持久化进度。

## 目录结构

```
citeoryx/
├── citeoryx.php              # 插件入口
├── uninstall.php             # 卸载处理
├── composer.json             # PHP 依赖与 autoload
├── package.json              # 前端依赖与构建脚本
├── readme.txt                # WordPress.org readme
├── src/
│   ├── Core/                 # 插件启动、容器、激活/停用、权限
│   ├── Domain/               # 领域模型与 Repository
│   │   ├── Content/
│   │   ├── Issue/
│   │   ├── Link/
│   │   ├── Metrics/
│   │   └── Scan/
│   ├── Application/          # 用例服务
│   │   ├── Scan/
│   │   ├── Analyze/
│   │   ├── Notifications/
│   │   ├── Optimize/
│   │   ├── Planning/
│   │   ├── Search/
│   │   └── Settings/
│   ├── Infrastructure/         # 技术实现
│   │   ├── Database/
│   │   ├── Queue/
│   │   ├── Cache/
│   │   ├── Http/
│   │   ├── Encryption/
│   │   └── Logging/
│   ├── Integrations/           # 外部集成适配器
│   │   ├── SeoPlugins/
│   │   ├── SearchConsole/
│   │   └── AiProviders/
│   ├── Rest/                   # REST 控制器与 Schema
│   │   ├── Controllers/
│   │   └── Router.php
│   ├── Admin/                  # 后台菜单、资产、通知
│   ├── Cli/                    # WP-CLI 命令
│   └── Support/                # 隐私、工具函数
├── assets/
│   ├── src/admin/              # React 管理后台源码
│   └── build/                  # 构建产物
├── languages/                  # 翻译文件
├── tests/                      # PHPUnit / WordPress 测试
├── e2e/                        # Playwright 后台旅程
└── vendor/                     # Composer 依赖
```

## 核心流程

### 初始化

```
安装插件
  → 检测现有 SEO 插件
  → 选择内容类型
  → 生成站点画像
  → 本地内容盘点
  → 可选连接 GSC / AI / SERP 服务
  → 建立首个健康基线
```

### 日常工作流

```
定时同步数据
  → 增量扫描变更内容
  → 生成问题和机会
  → 计算优先级
  → 分配任务
  → 在优化工作台处理
  → 人工审核发布
  → 提交更新通知
  → 观察 7 / 28 / 90 天效果
```

### 后台扫描任务

`POST /scans` 创建 `cx_scan_runs` 记录并立即返回。Action Scheduler 可用时使用异步 Action；否则使用 WordPress 单次 Cron。每个任务最多处理 50 条内容，完成一批后保存游标并继续排队，前端通过 `GET /scans/{id}` 轮询 `queued` / `running` / `completed` / `failed` 状态。

### 邮件周报

每周周报默认关闭，由 `WeeklyDigest` 使用本地内容与问题聚合生成纯文本邮件。任务不是固定秒数的重复 Cron，而是每次执行后依据 `wp_timezone()` 重新调度下周一 09:00 的单次事件，避免夏令时导致本地发送时间漂移；成功接受发送后写入 ISO 周期键，重复任务不会再次发送。

## 命名规范

| 项目 | 正式值 |
|---|---|
| 正式名称 | Citeoryx |
| WordPress 目录名 | `citeoryx` |
| PHP 类、接口、常量前缀 | `CX_` |
| PHP 命名空间 | `Citeoryx\` |
| 函数、Hook、Option 前缀 | `citeoryx_` |
| 数据库表前缀 | `{wp_prefix}cx_` |
| REST API 命名空间 | `/wp-json/citeoryx/v1/` |
| 文本域 | `citeoryx` |

## 依赖注入

`Citeoryx\Core\Container` 是一个轻量级服务容器，按类名延迟实例化服务。所有外部依赖（数据库、HTTP、缓存、加密）均通过 Repository / Adapter 隔离。

## 安全

- REST API 使用 WordPress Nonce 和自定义 Capability 校验；
- 外部请求使用 `wp_safe_remote_*` 并防止 SSRF；
- API Key 优先通过 `wp-config.php` 常量或环境变量配置；
- 存储时使用 Sodium/OpenSSL 加密；
- 所有数据库操作使用 Prepared Statements。
