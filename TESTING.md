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

### 集成测试

覆盖：

- WordPress CRUD；
- REST API；
- Action Scheduler；
- GSC Mock；
- SEO Plugin Adapters；
- 数据库迁移；
- 多语言；
- WooCommerce。

### E2E

覆盖：

- 安装向导；
- 连接 GSC；
- 启动扫描；
- 查看问题；
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

# 代码规范检查
composer phpcs
npm run lint:js
npm run lint:css
```

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
		<testsuite name="Integration">
			<directory suffix="Test.php">tests/Integration</directory>
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
