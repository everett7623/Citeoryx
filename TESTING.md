# Citeoryx 测试指南

## 测试类型

### 单元测试

覆盖：

- 各健康规则；
- 评分边界；
- 衰退算法；
- Cannibalization 归一化；
- URL 和 Canonical 处理；
- Provider Response Mapping；
- Encryption；
- 权限。
- Author / Contributor 的作者级内容、问题与优化建议访问边界。
- 周报调度、周期幂等和邮件请求契约。
- Settings 首次加载不受可选通知状态异常影响，管理端不会直接显示 HTML 错误正文。
- Revision 创建不修改父内容，并覆盖并发冲突、重复提交幂等、HTML 清洗和对象级权限。
- 问题状态切换只接受最新列表请求，较早响应不会覆盖当前筛选结果。

### WordPress 集成覆盖

当前 PHP 测试统一位于 `tests/Unit/`，但通过 WordPress 官方测试环境启动插件、安装数据库表并执行真实 WordPress API 和数据库操作。仓库暂未拆分独立的 `tests/Integration/` 测试套件。

覆盖：

- WordPress CRUD；
- REST API；
- Action Scheduler；
- GSC Mock；
- SEO Plugin Adapters；
- 数据库迁移；
- 多语言；
- WooCommerce 兼容逻辑。

### E2E

当前 Playwright 自动化覆盖：

- 安装向导完成站点画像；
- 内容规划与报告页签加载；
- PDF 报告生成与下载文件校验；
- 扫描任务创建与进度轮询；
- 问题列表查看、解决与状态筛选。

后续旅程规划覆盖：

- 连接 GSC；
- 生成 AI 建议；
- 创建 Revision；
- 发布；
- 查看优化效果。

## 运行测试

```bash
# 单元测试
vendor/bin/phpunit

# JavaScript 测试
npm run test

# 项目版本与仓库卫生检查
npm run check:project

# 代码规范检查
composer phpcs
npm run lint:js
npm run lint:css

# 首次运行需安装 Chromium，并启动 Docker
npx playwright install chromium
npm run build
npm run env:start
npm run test:e2e
npm run env:stop
```

E2E 使用 `.wp-env.json` 启动 WordPress 6.6 / PHP 8.0，默认地址为
`http://localhost:8889`。测试会激活 Citeoryx、重置站点画像、隔离扫描旅程中
上次运行遗留的活动任务，并使用 wp-env 默认管理员完成真实后台旅程。失败时可在
`playwright-report/` 和 `test-results/` 查看 HTML 报告、截图、视频和 trace。

如需改用 wp-env 测试站，页面地址和 WP-CLI 目标必须同时切换：

```bash
CITEORYX_E2E_BASE_URL=http://localhost:8890 CITEORYX_E2E_WP_ENV=tests-cli npm run test:e2e
```

首次运行 PHP 测试前，需要可用的 MySQL 和 Bash 环境：

```bash
composer test-install
composer test
```

GitHub Actions 会对 Node.js 前端检查、Chromium E2E，以及 PHP 8.0–8.4 / WordPress 6.6 和最新版本组合自动执行质量门禁。WPCS 使用项目级 `phpcs.xml.dist` 执行 `WordPress-Extra` 规则，并作为阻断条件运行；规则集仅排除与 Composer PSR-4 架构及既有领域代码风格冲突的文件命名、保留字参数名和短三元表达式规则。

## PHP 测试配置

`phpunit.xml.dist`：

```xml
<?xml version="1.0"?>
<phpunit
	bootstrap="tests/bootstrap.php"
	colors="true"
>
	<testsuites>
		<testsuite name="Unit">
			<directory suffix="Test.php">tests/Unit</directory>
		</testsuite>
	</testsuites>
</phpunit>
```

## 兼容性矩阵

| 项目 | 最低目标 | 推荐测试 |
|---|---|---|
| PHP | 8.0 | 8.0 / 8.1 / 8.2 / 8.3 / 8.4 |
| WordPress | 6.6 | 最新稳定版及前两个大版本 |
| MySQL | 5.7 | 8.0 |
| MariaDB | 10.4 | 10.11 |
| Gutenberg | Core | 最新插件版 |
| WooCommerce | 8.x | 最新稳定版 |
| Multisite | 暂不承诺 | 安装和停用不报错 |

## 性能验收

- 前台无新增查询或仅可忽略的常量级查询；
- 后台列表分页；
- 1,000 页面扫描不中断；
- 任务失败可重试；
- 扫描暂停后可恢复；
- 日志可定位到具体 URL 和规则；
- 无单次请求长时间占用 PHP Worker。
