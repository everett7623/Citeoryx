# Sub2API / Codex 端点需要 Responses 协议

日期：2026-07-23 ~ 2026-07-24

## 症状

用户在插件中选择「OpenAI 兼容 API」，按 Codex 配置填入 `base_url = "https://sub2.uukk.de"`、模型和 Key，连接测试始终失败。Key 和模型都正确。

## 原始证据

- `https://sub2.uukk.de` 返回 HTTP 200 HTML 管理页面，不是模型请求端点。
- `POST https://sub2.uukk.de/v1/responses` 返回结构化 JSON 鉴权响应，说明 Responses 路由存在。
- 用户的 Codex 配置同时声明了协议契约，不只是路由：

```toml
base_url = "https://sub2.uukk.de"
wire_api = "responses"
disable_response_storage = true
```

- 插件此前把 HTTP 200 HTML 和无法识别的 JSON 都折叠成通用的「检查 Key、模型和地址」错误，掩盖了真实原因。

## 根因

两层问题：

1. **协议不匹配**。原 OpenAI 兼容模式使用 Chat Completions 协议，向配置 URL 发送 `messages` 请求体；`wire_api = "responses"` 要求请求 `/v1/responses`、使用 `input`、并以 `store: false` 禁止响应存储。认证头相同不足以弥补请求体和响应结构的差异。
2. **诊断信息坍缩**。兼容层把结构完全不同的失败（HTML 页面、空响应、无效 JSON、无文本的 Responses 响应）归并为同一条文案，用户无法区分「地址错」和「协议错」。

## 修复

- 保留原 OpenAI 兼容完整 URL 模式，不影响已有第三方端点。
- 新增 **OpenAI Responses API（Sub2API / Codex）** 模式：接受服务根地址，请求 `/v1/responses`，请求体使用 `input`、`max_output_tokens` 和 `store: false`，并显式发送 `stream: false`。
- 响应解析同时兼容三种形态：非流式 `output[].content[].output_text`、完成事件封装 `response.completed.response`、以及上游在 `stream=false` 时仍返回 SSE 的文本增量 `response.output_text.delta`（Sub2API 服务端本身也兼容这种情况）。
- 对 HTML、空响应、无效 JSON、无文本 Responses 响应分别给出可区分的安全诊断，只显示 HTTP 状态或安全网络错误，不回显上游正文、Key 或 URL。

## 验证

- 使用 Codex 配置的根地址完成连接测试与实际分析请求。
- `tests/Unit/OpenAiResponsesCompatibilityTest.php` 覆盖三种响应形态的解析。

## 教训

- 提供商配置里的 `wire_api` 是**协议契约**，不只是路由提示。兼容层必须同时匹配 URL、请求体结构和响应结构，不能只复用认证头。
- 把不同结构的失败折叠成一条通用文案会直接拖长排查时间。诊断分支的粒度要对齐失败的**结构种类**，而不是对齐「是否成功」。
