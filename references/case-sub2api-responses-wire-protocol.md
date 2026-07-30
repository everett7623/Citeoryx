# Sub2API Codex 配置使用 Responses 协议

## 现象

用户在插件中选择 OpenAI 兼容 API，填写 Codex 配置里的 `base_url = "https://sub2.uukk.de"`、模型和 Key，连接测试仍失败。

## 真实配置

Codex 配置同时声明：

```toml
base_url = "https://sub2.uukk.de"
wire_api = "responses"
disable_response_storage = true
```

## 根因

插件原有 OpenAI 兼容模式使用 Chat Completions 协议，向配置 URL 发送 `messages` 请求体。Codex 配置要求 Responses 协议：请求 `/v1/responses`，使用 `input`，并通过 `store: false` 禁止响应存储。Key 与模型正确也无法弥补协议不匹配。

## 修复

- 保留原 OpenAI 兼容完整 URL 模式，不改变已有第三方端点。
- 新增 OpenAI Responses API（Sub2API / Codex）模式，接受服务根地址并请求 `/v1/responses`。
- Responses 请求使用 `input`、`max_output_tokens` 与 `store: false`，解析顶层 `output_text` 或 `output` 消息文本块。
- 连接失败仅显示 HTTP 状态或安全网络错误，不回传响应正文、Key 或 URL。

## 教训

提供商配置中的 `wire_api` 是协议契约，不只是路由提示。兼容层必须同时匹配 URL、请求体和响应结构，不能只复用认证头。
