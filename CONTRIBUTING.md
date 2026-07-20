# Citeoryx 贡献指南

## 开发环境

- PHP >= 8.0
- WordPress >= 6.6
- Node.js >= 18
- Composer
- npm

## 快速开始

```bash
# 克隆仓库到 wp-content/plugins/citeoryx
cd wp-content/plugins/citeoryx

# 安装 PHP 依赖
composer install

# 安装前端依赖
npm install

# 启动前端开发服务器
npm run start

# 构建生产包
npm run build
```

## 代码规范

### PHP

- 遵循 WordPress PHP Coding Standards；
- 使用 PSR-4 自动加载；
- 类 / 接口 / 常量使用 `CX_` 前缀；
- 函数、Hook、Option 使用 `citeoryx_` 前缀；
- 命名空间为 `Citeoryx\`。

### JavaScript

- 使用 `@wordpress/scripts` 构建；
- React 函数组件；
- 使用 `@wordpress/i18n` 进行国际化；
- 使用 `@wordpress/api-fetch` 调用 REST API。

## 提交规范

- 提交信息使用英文；
- 类型 + 简短描述，例如 `feat: add orphan page detection`。

## 测试

详见 [TESTING.md](TESTING.md)。

## 分支与发布

- `main`：稳定分支；
- `develop`：日常开发；
- 功能分支：`feature/xxx`；
- 修复分支：`fix/xxx`。

## 报告问题

请提供：

- WordPress 版本
- PHP 版本
- 已安装的 SEO 插件
- 复现步骤
- 相关日志（不含 API Key）
