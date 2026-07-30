# Sub2API Responses 连接失败排查记录

日期：2026-07-24

## 现象

- `https://sub2.uukk.de` 返回 HTTP 200 HTML 管理页面，不是模型请求端点。
- `POST https://sub2.uukk.de/v1/responses` 返回结构化 JSON 鉴权响应，说明 Responses 路由存在。
- 插件此前把 HTTP 200 HTML 或无法识别的 JSON 都折叠为通用“检查 Key、模型和地址”错误。

## 契约依据

- Sub2API 的 Responses 探测请求使用 `/v1/responses`、`input` 和 `stream: false`。
- OpenAI Responses 非流式结果使用 `output[].content[].output_text`；流式完成事件可使用 `response.completed.response`，文本增量使用 `response.output_text.delta`。
- Sub2API 服务端也显式兼容上游在 `stream=false` 时错误返回 SSE 的情况。

## 修复

- Responses 模式显式发送 `stream: false`。
- 兼容非流式结果、完成事件封装和 SSE 文本增量。
- 对 HTML、空响应、无效 JSON、无文本 Responses 响应分别给出安全诊断，不回显任意上游正文。
- 普通 OpenAI 兼容模式继续原样请求用户填写的完整 URL；Sub2API 根地址通过明确的 Responses 模式使用。
