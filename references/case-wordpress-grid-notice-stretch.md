# WordPress Grid Notice Stretch

## Symptom

WordPress `Notice` 在集成页显示成大块红色区域，文字只有一行，但通知高度与右侧较高的设置卡片一致。

## Evidence

- 页面容器是两列 CSS Grid。
- Notice 是 Grid 的第一个直接子项，Google 设置卡片是第二个子项。
- Grid 默认拉伸同一行子项，因此左侧 Notice 高度被右侧卡片撑高。

## Fix

- 使用 `.citeoryx-integrations > .components-notice` 直接子项选择器，不扩大历史超长组件。
- 设置 `grid-column: 1 / -1`，让通知独占整行。
- 设置 `align-self: start` 并移除 Notice 外边距，保持紧凑高度。

## Verification

- JS 单测与 lint。
- CSS lint 与生产构建。
- 检查发布包包含最新构建资源。
