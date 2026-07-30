# WordPress 设置接口返回 critical error HTML

## 症状

安装 Citeoryx 后进入后台，React 管理端能够加载，但首次 `GET /citeoryx/v1/settings` 失败。错误通知直接显示 WordPress 的 critical error HTML，页面只剩“重试”按钮，无法进入站点画像第一步。

## 原始证据

- 2026-07-21 用户截图确认错误正文为 `<p>There has been a critical error on this website.</p>`。
- React 已渲染且进入 `App.js` 的设置加载失败分支，故障边界确定在初始 Settings REST 请求，而不是静态资源或菜单注册。
- 当前可控浏览器没有用户 WordPress 登录会话，服务器也未提供 PHP 错误日志，因此无法确认内部 `Throwable` 的具体类和堆栈。
- 代码检查确认 Settings 响应在同一调用链中同步读取可选的 `WeeklyDigest` 状态；该附加模块的异常会让整个 onboarding 返回 500。

## 根因

设置接口的职责边界过宽：首次站点画像依赖可选通知模块成功返回。同时，前端信任 `apiFetch` 的 `message` 是纯文本，把服务器或代理返回的 HTML 原样显示。具体服务器内部异常仍需线上 `debug.log` 才能进一步归因，但这两个边界缺陷已足以解释“附加模块异常导致第一步完全不可用”和“HTML 泄漏到界面”的现象。

## 修复

- Settings 响应把通知状态读取改成可降级附加数据；捕获异常后返回稳定的 `never` 状态，不阻断画像加载。
- 仅在 `WP_DEBUG` 开启时写入通知状态异常，保留线上定位信息。
- 管理端统一通过 `getApiErrorMessage()` 过滤 API 错误，HTML 响应改为当前操作的可读失败提示。
- 新增通知服务抛异常的 Settings 契约测试，以及 HTML 错误过滤测试。
- 发布版本升为 `2.1.0`，避免继续以 `2.0.1` 覆盖安装造成更新与缓存识别混乱。

## 验证

- 设置响应本地烟雾测试可返回画像选项和稳定通知状态。
- JavaScript 3 个测试套件、8 条测试全部通过。
- JavaScript lint、CSS lint、生产构建和全部 PHP 语法检查通过。
- WordPress PHPUnit 与真实后台 E2E 仍需带 WordPress 测试库或用户站点登录会话的环境执行。

## 教训

- 首次设置等关键路径不得同步依赖通知、报告等可选模块。
- UI 边界不能假设错误消息一定是 JSON 纯文本。
- 没有服务器堆栈时必须明确未知项，不能把结构性修复误写成已确认的内部异常类型。
