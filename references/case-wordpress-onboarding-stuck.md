# WordPress 后台首次设置停留在引导页

## 症状

进入 Citeoryx 后台后停留在首次站点画像页面。2.0.1 已修复一次 PHP 8 TypeError，但页面仍无法区分“画像尚未完成”和“设置接口加载失败”。

## 原始证据

- 2026-07-21 拉取远程后，本地 `master` 与 `origin/master` 同为 `da012ce`，不存在未同步修复。
- 2026-07-22 的 GitHub Actions Playwright trace 取得了真实 `GET /wp-json/citeoryx/v1/settings` 响应：空画像被编码为 `"profile":[]`，而非对象。
- `App.js` 的原实现会在 `GET /settings` 失败时执行 `setProfile( {} )`，随后把任何网络、Nonce、权限或服务端错误都渲染成首次引导页。
- `SettingsController` 的原实现只做字符串清洗，缺失或非法的画像仍会写入并返回成功；前端随后只检查 `profile.site_type`。
- Onboarding 的原实现在 POST 成功后再次 GET 设置；第二次请求失败时页面仍会停留在引导页。

## 根因

前端将接口失败状态和业务上的“画像不完整”合并成同一个空对象状态，同时后端没有提供或强制执行完整画像契约。页面现象因此无法反映真实失败原因。后续的真实 E2E 响应进一步确认：PHP 的空数组会编码为 JSON 数组 `[]`，但 `profile` 是对象语义，前端严格校验后拒绝该响应。另有 `TabPanel` 误用 `initialTab` 属性，导致带 `#/reports` 的直达链接落回默认页。

## 修复

- Settings REST 统一返回 `profile_complete`、`profile_options`、`profile` 和 `settings`。
- 服务端在写入前校验必填字段、枚举、真实公开内容类型和布尔值类型。
- 初始 GET 失败显示错误与重试，不再进入引导页。
- POST 成功后直接使用服务端返回数据完成引导，不再依赖第二次 GET。
- 首次引导和后续设置复用同一字段组件，防止字段漂移。
- 空画像在 REST 响应中显式转换为 `{}`，保持 `profile` 的对象契约。
- 将 TabPanel 初始页属性改为 `initialTabName`，使深链接直接打开对应页签。

## 验证

- 前端生产构建通过。
- CSS lint 通过。
- 全部 PHP 文件语法检查通过。
- PHPUnit 契约测试已补充，但因本机缺少 WordPress 测试库尚未执行。
- 真实 WordPress 后台 E2E 待具备运行站点后回归。

## 2026-07-22 CI 回归

- 真实 Playwright trace 证明设置接口成功返回但空画像类型错误，修复后该字段固定为 JSON 对象。
- `TabPanel` 的 `initialTabName` 属性已在本地已安装的 WordPress Components 源码中核对。
- 前端单测（6 个套件、17 项）、生产构建、受影响 PHP 的 WPCS 检查、PHP 语法检查和 `git diff --check` 均通过。
- 本机无 Docker，且 PHP 缺少 `mbstring`，无法本地执行 `wp-env` E2E 与 PHPUnit；需要由下一次 GitHub Actions 运行完成最终环境回归。
