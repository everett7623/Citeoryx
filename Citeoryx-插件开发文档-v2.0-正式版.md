# Citeoryx 插件开发文档
### WordPress 内容健康度持续监控、优化与 AI 可发现性引擎

> 版本：v2.0 Product & Technical Design  
> 更新：2026 年 7 月 13 日  
> 历史项目：RankDraft / ContentPilot SEO  
> 正式插件名：**Citeoryx**  
> 插件目录名：`citeoryx`  
> 文本域：`citeoryx`  
> 命名状态：已确认，后续统一以 Citeoryx 作为唯一对外产品名称

---

## 一、项目升级说明

Citeoryx 是原 RankDraft / ContentPilot SEO 项目的正式升级版本。产品不再只解决“下一篇文章写什么”，而是帮助站点持续回答以下问题：

1. 站内目前有哪些内容资产？
2. 哪些页面正在增长、停滞、衰退或互相竞争？
3. 哪些问题最值得先修复？
4. 页面应该增加、删除、合并、更新还是重新定位？
5. 内容是否容易被搜索引擎、生成式搜索和 AI 问答系统发现、理解和引用？
6. 优化完成后，是否真正带来了展示、点击、排名和转化改善？

新版产品闭环：

```text
内容盘点
  ↓
持续监控
  ↓
问题与机会识别
  ↓
影响 / 置信度 / 成本排序
  ↓
人工确认与辅助优化
  ↓
发布与重新抓取
  ↓
效果验证
  ↓
规则与策略持续学习
```

### 1.1 一句话定位

> Citeoryx 是一款与 Rank Math、Yoast、AIOSEO 等基础 SEO 插件兼容的 WordPress 内容运营插件，用于持续监控站点内容健康度、发现增长机会、组织优化任务，并提升传统搜索与 AI 搜索环境下的内容可发现性。

### 1.2 产品类别

Citeoryx 不应被定义为：

- 又一个 Meta Title / Description 编辑器；
- 又一个关键词密度评分器；
- 又一个自动批量写文章插件；
- 又一个 Schema、站点地图和重定向全家桶；
- 又一个用单一“SEO 分数”制造焦虑的页面插件。

Citeoryx 应被定义为：

- **Content Inventory**：内容资产库；
- **Content Health Engine**：内容健康度引擎；
- **Optimization Workflow**：内容优化工作流；
- **Opportunity Intelligence**：增长机会识别；
- **AI Discoverability Readiness**：AI 可发现性准备度；
- **Content Lifecycle Management**：内容全生命周期管理。

### 1.3 品牌命名与含义

旧项目名称 RankDraft 更像“排名文章草稿生成器”，无法覆盖内容监控、维护、修复、验证与 AI 可发现性等新能力；同时继续使用 `Rank`、`SEO`、`Content` 等高频词，也容易与现有工具形成同质化命名。

正式使用品牌名 **Citeoryx**：

- `Cite` 对应 citation、引用来源和可被答案引擎采用的内容证据；
- `oryx` 作为无固定产品含义的品牌化后缀，增强名称独特性和可注册性；
- 名称不局限于 Google 排名，可覆盖 SEO、AEO、内容健康度、知识图谱与持续优化；
- 适用于博客、企业站、商城、媒体、知识库、本地站点及多语言站点；
- 可形成统一产品语言：Citeoryx Health、Citeoryx Graph、Citeoryx Workspace、Citeoryx Insights。

正式命名与代码规范：

| 项目 | 正式值 |
|---|---|
| 正式名称 | Citeoryx |
| 产品描述 | Content Health & AI Discoverability Engine |
| 中文描述 | 站点内容健康度与 AI 可发现性引擎 |
| WordPress 目录名 | `citeoryx` |
| PHP 类、接口与常量前缀 | `CX_` |
| PHP 命名空间 | `Citeoryx\` |
| 函数、Hook 与 Option 前缀 | `citeoryx_` |
| 数据库表前缀 | `{wp_prefix}cx_` |
| REST API 命名空间 | `/wp-json/citeoryx/v1/` |
| 文本域 | `citeoryx` |

> 截至本次初步公开检索，未发现同名 WordPress 插件或 GitHub 软件项目。正式发布前仍应进行商标数据库、域名、社交账号与应用市场的法律级检索，不能仅凭搜索引擎结果断定全球可注册。

> 本文档后续统一使用 **Citeoryx**。

---

## 二、市场定位与差异化

### 2.1 主流工具已解决的问题

| 工具类型 | 典型产品 | 已解决的核心问题 |
|---|---|---|
| WordPress 基础 SEO | Rank Math、Yoast、AIOSEO、SEOPress | Meta、Canonical、Robots、Schema、Sitemap、基础页面检查 |
| 内容编辑优化 | Surfer、Semrush SWA、Frase | 关键词覆盖、竞品内容参考、编辑器实时建议 |
| 内容资产监控 | Clearscope、Surfer Content Audit | 内容库存、内容衰退、表现变化、更新机会 |
| 技术审计 | Screaming Frog、Sitebulb、Semrush Site Audit | 抓取、状态码、结构、重复内容、技术错误 |
| AI 可见性监控 | Semrush AI Search 等 | Prompt 跟踪、品牌提及、引用来源、AI 可见性趋势 |

### 2.2 Citeoryx 不重复造轮子的原则

Citeoryx 不与成熟 SEO 插件争夺以下输出权：

- Title、Description；
- Canonical；
- Robots Meta；
- XML Sitemap；
- Breadcrumb Schema；
- 通用 Article / Product / Organization Schema；
- 301 Redirect；
- Open Graph 和 Twitter Card。

默认策略：

```text
检测到现有 SEO 插件
  → 读取其公开数据或兼容字段
  → 提供分析和建议
  → 不重复输出前台标签

未检测到 SEO 插件
  → 仅提供必要的只读审计
  → 提示安装基础 SEO 插件
  → 不把 Citeoryx 变成全家桶
```

### 2.3 真正差异化能力

Citeoryx 的竞争力不来自功能数量，而来自以下组合：

1. **WordPress 原生内容库存**：无需先导出 URL 或把全部内容上传到 SaaS。
2. **持续监控而不是单次审计**：保存历史快照，识别变化而不是只看当前分数。
3. **问题自动转为任务**：每个问题有证据、影响范围、建议动作、负责人和状态。
4. **传统 SEO 与 AI 可发现性统一**：AEO 不脱离抓取、索引、质量、实体和内容价值。
5. **可解释评分**：显示分项、置信度、数据来源和触发规则，不输出无法解释的黑盒分数。
6. **本地优先、API 可选**：不连接 GSC、不购买 SERP API、不配置 AI Key，也能完成基础内容审计。
7. **人工审批优先**：AI 只提供分析、草案和差异，不默认自动覆盖正式内容。
8. **适用于全部站点类型**：通过站点画像和内容类型规则适配博客、B2B、商城、文档、媒体、本地服务站。

---

## 三、产品设计原则

### 3.1 Health First

首页不以“写一篇新文章”为中心，而以“当前站点最值得处理的 5 件事”为中心。

### 3.2 Evidence Before Advice

任何优化建议至少包含：

- 触发原因；
- 数据来源；
- 影响页面；
- 影响等级；
- 建议操作；
- 置信度；
- 可验证指标。

错误示例：

> 文章质量较差，建议优化。

正确示例：

> 该页面最近 28 天展示量较前一周期下降 34%，平均排名从 8.7 降至 14.2；主要查询仍有稳定搜索需求，页面最近 18 个月未实质更新。建议优先检查过时信息、搜索意图变化和竞争页面新增内容。

### 3.3 No Score Theater

- 不把所有指标压成一个无法解释的分数；
- 总分仅用于排序；
- 必须展示分项；
- 必须展示置信度；
- 数据不足时显示“数据不足”，而不是强行给低分；
- 不因文章未达到固定字数而扣分；
- 不以关键词密度作为核心评分依据。

### 3.4 Local-First and BYOK

基础扫描不依赖外部服务。

可选连接：

- Google Search Console；
- Bing Webmaster Tools；
- GA4；
- SERP 数据服务；
- OpenAI、Anthropic、Gemini、OpenRouter 或本地模型；
- Perplexity Search / Sonar 等搜索与引用接口。

### 3.5 Human-in-the-Loop

涉及以下动作时默认要求人工确认：

- 改写正文；
- 删除内容；
- 合并页面；
- 修改 Canonical；
- 添加或移除 Schema；
- 批量内部链接；
- 自动发布；
- 向外部 AI 服务发送完整内容。

### 3.6 Compatibility First

插件必须优先兼容：

- Gutenberg；
- Classic Editor；
- WooCommerce；
- Rank Math；
- Yoast SEO；
- AIOSEO；
- SEOPress；
- WPML / Polylang；
- 常见缓存和安全插件。

---

## 四、目标用户与站点类型

### 4.1 目标用户

- 个人博客站长；
- 企业官网运营人员；
- 内容营销团队；
- SEO 顾问和代理商；
- WooCommerce 商城运营人员；
- 文档站和知识库维护者；
- 多语言站点管理员；
- 有大量历史文章但缺少维护机制的站点。

### 4.2 站点画像

首次安装时创建站点画像：

| 配置 | 示例 |
|---|---|
| 站点类型 | 博客 / 企业站 / 商城 / 媒体 / 文档 / 本地服务 |
| 主要目标 | 流量 / 询盘 / 销售 / 订阅 / 品牌曝光 / 支持分流 |
| 核心内容类型 | Post / Page / Product / Docs / CPT |
| 主要语言 | 中文 / 英文 / 西语等 |
| 主要地区 | 全球 / 国家 / 城市 |
| 更新节奏 | 高频 / 周更 / 月更 / 低频常青 |
| 内容风险等级 | 普通 / YMYL / 医疗 / 金融 / 法律 |
| 默认审查周期 | 30 / 90 / 180 / 365 天 |

站点画像用于调整规则阈值，而不是强制套用同一套模板。

---

## 五、完整产品闭环

### 5.1 初始化

```text
安装插件
  → 检测现有 SEO 插件
  → 选择内容类型
  → 生成站点画像
  → 本地内容盘点
  → 可选连接 GSC / AI / SERP 服务
  → 建立首个健康基线
```

### 5.2 日常工作流

```text
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

### 5.3 内容生命周期状态

| 状态 | 说明 |
|---|---|
| Healthy | 当前无明显问题，继续监控 |
| Growing | 展示、点击或排名持续增长 |
| Opportunity | 有较高展示但 CTR、排名或覆盖仍可提升 |
| Decaying | 表现持续下降且超过阈值 |
| Stale | 内容长期未审查，存在时效风险 |
| Competing | 与其他页面发生查询或主题竞争 |
| Orphaned | 缺少有效内部入口 |
| Broken | 存在抓取、链接、结构或渲染问题 |
| Needs Review | 数据不足或规则冲突，需要人工判断 |
| Archived | 已归档，不进入常规优化队列 |

---

## 六、后台菜单与信息架构

### 6.1 菜单结构

| 菜单项 | 核心用途 | 默认视图 |
|---|---|---|
| 总览 | 查看健康趋势和最优先任务 | Dashboard |
| 内容资产 | 全站内容库存与筛选 | Inventory |
| 问题与机会 | 统一处理衰退、孤儿页、冲突和增长机会 | Opportunities |
| 优化工作台 | 对单页进行分析、改稿、对比和审批 | Optimizer |
| AI 可发现性 | AEO 准备度、实体、答案结构和 AI 观察 | AI Discoverability |
| 内容规划 | 保留原关键词研究、SERP 和选题能力 | Planning |
| 报告 | 周报、月报、优化效果和导出 | Reports |
| 设置 | 站点画像、集成、规则、自动化和权限 | Settings |

### 6.2 总览页面

首页只显示可行动信息：

- 全站内容健康趋势；
- 本周新增问题；
- 本周已解决问题；
- 高优先级内容衰退；
- Striking Distance 页面；
- 高展示低 CTR 页面；
- 查询竞争页面；
- 未审查的高价值内容；
- AI 可发现性高风险页面；
- 最近优化后的表现变化。

首页主按钮：

- `开始处理最高优先级任务`
- `运行增量扫描`
- `查看本周变化`

不把“生成文章”设置为首页主按钮。

---

## 七、核心模块设计

## 7.1 内容资产库 Content Inventory

内容资产库自动收集：

- WordPress Post / Page / Product / CPT；
- 发布状态；
- 规范 URL；
- 作者；
- 发布时间；
- 实质更新时间；
- 字数和区块数量；
- 标题层级；
- 内链入度 / 出度；
- 外链数量；
- 图片、视频、表格、列表；
- SEO 插件元数据；
- Schema 类型；
- 索引控制；
- GSC 查询和页面数据；
- 健康状态；
- 最近审查时间；
- 负责人和标签。

支持保存视图：

- 最近 90 天衰退文章；
- 排名 4–15 的机会页面；
- 高展示低 CTR 页面；
- 无入链页面；
- 超过一年未审查的页面；
- 产品页；
- 医疗 / 金融等高风险内容；
- 中文 / 英文 / 西语内容；
- AI 准备度低于 60 的高流量页面。

## 7.2 内容健康度引擎 Content Health Engine

健康引擎由规则、数据和历史趋势共同驱动。

### 检查维度

1. 可发现性；
2. 搜索表现；
3. 内容新鲜度；
4. 搜索意图与主题覆盖；
5. 答案结构；
6. 信任与证据；
7. 内外链结构；
8. 技术可访问性；
9. 多语言一致性；
10. 商业目标匹配。

### 规则类型

| 类型 | 示例 |
|---|---|
| 硬错误 | Noindex、404、Canonical 指向异常、正文为空 |
| 趋势问题 | 点击和展示连续下降 |
| 结构问题 | H1 缺失、标题层级跳跃、核心内容被折叠或 JS 阻断 |
| 内容问题 | 过时事实、答案不直接、缺少来源、重复段落 |
| 关系问题 | 孤儿页、内链锚文本弱、主题集群断裂 |
| 机会 | 高展示低 CTR、排名 4–15、已有查询但页面未覆盖 |
| 冲突 | 多 URL 竞争同一查询、重复意图页面 |
| AI 准备度 | 实体不清、作者信息不足、事实无来源、答案难提取 |

## 7.3 内容衰退检测 Content Decay

衰退不能只比较两个时间段，否则容易把季节性和短期波动误判为问题。

建议使用：

- 最近 28 天；
- 前 28 天；
- 最近 90 天趋势；
- 去年同期；
- 站点整体趋势；
- 查询需求趋势；
- 页面修改时间；
- 最低展示量门槛。

### 基础判定示例

```php
$is_decay =
    $current_impressions >= $minimum_impressions
    && $click_change_28d <= -0.25
    && $impression_change_28d <= -0.15
    && $position_change_28d >= 2.0
    && $trend_90d === 'down';
```

### 衰退类型

- 排名下降型；
- CTR 下降型；
- 搜索需求下降型；
- 内容过时型；
- 竞争页面分流型；
- 技术异常型；
- 季节性波动型；
- 站点整体波动型。

插件必须显示“疑似原因”，不能把全部下降都归因于内容质量。

## 7.4 Striking Distance 机会

默认识别：

- 平均排名 4–15；
- 有稳定展示；
- 查询与页面主题高度相关；
- 页面未发生明显意图错位；
- 页面仍可索引；
- 不是品牌导航词误判。

建议动作：

- 增强与查询相关的段落；
- 优化标题和摘要，但不重复输出 SEO 元数据；
- 增加内部链接；
- 补充比较、步骤、定义、示例、数据或 FAQ；
- 更新过时信息；
- 改善答案可提取性。

## 7.5 高展示低 CTR

需要排除：

- 品牌词与非品牌词混合；
- 排名变化造成的自然 CTR 变化；
- 图片、视频、本地、购物等 SERP 形态影响；
- 查询意图与页面不一致；
- Google 重写标题的情况。

输出建议：

- 查询分组；
- 当前标题与页面主标题；
- 可能被重写的标题来源；
- 竞品标题结构摘要；
- 改进建议；
- 修改前快照；
- 修改后 28 天效果。

## 7.6 内容竞争与 Cannibalization

不能仅因两个页面出现同一关键词就判定冲突。

同时满足多个信号时再创建问题：

- 同一规范化查询对应多个 URL；
- URL 展示份额频繁切换；
- 两个页面意图相似；
- 平均排名不稳定；
- 两页互相缺少明确主从关系；
- 页面主题向量高度接近。

建议动作类型：

- 保留主页面并合并内容；
- 明确不同意图；
- 调整内部链接；
- 调整 Canonical，仅提供建议；
- 301 合并，仅交给现有重定向插件执行；
- Noindex，仅在人工确认后交给基础 SEO 插件执行；
- 保持不变并忽略误报。

## 7.7 内部链接图谱

建立站内有向图：

```text
页面 A --锚文本--> 页面 B
```

记录：

- 来源页面；
- 目标页面；
- 锚文本；
- Follow / Nofollow；
- 链接所在区块；
- 正文链接或模板链接；
- HTTP 状态；
- 首次发现和最后检查时间。

核心能力：

- 孤儿页检测；
- 低入链高价值页；
- 无关锚文本；
- 失效内链；
- 过度集中链接；
- 主题集群断裂；
- 推荐来源页面和自然锚文本；
- 批量插入前预览和人工确认。

## 7.8 外部链接与证据

外链不再是简单的“优先域名 / 排除域名”。

来源评估维度：

- 是否为一手来源；
- 是否与主张直接相关；
- 是否仍可访问；
- 是否具有发布日期和更新时间；
- 是否有明确作者或机构；
- 是否存在明显商业偏见；
- 是否为循环引用；
- 是否与页面语言和地区匹配。

不建议继续使用固定的 DA / DR 加权作为核心来源评分。域名强度可以作为辅助信号，但不能替代来源与具体主张的相关性。

## 7.9 内容优化工作台

优化工作台不是独立写作器，而是单页问题处理中心。

### 页面布局

左侧：

- 当前 WordPress 内容；
- Gutenberg 区块结构；
- 修订记录；
- 原始版本与建议版本差异。

右侧：

- 页面健康分项；
- 主要查询；
- 问题列表；
- 证据；
- 推荐动作；
- AI 辅助按钮；
- 内链建议；
- AEO 检查；
- 发布前验证。

### AI 辅助动作

- 解释问题；
- 生成修改方案；
- 补充缺失段落；
- 生成直接答案；
- 重组标题层级；
- 提取或生成摘要；
- 生成比较表；
- 找出需核实的事实；
- 建议引用来源类型；
- 生成内部链接上下文；
- 生成更新说明。

默认只生成建议，不直接覆盖。

## 7.10 内容规划模块

旧版 RankDraft / ContentPilot 的功能保留，但从核心产品降为“内容规划”子模块。

保留能力：

- GSC 查询发现；
- 关键词库；
- 主题聚类；
- SERP 研究；
- 内容缺口；
- 趋势分析；
- AI 大纲；
- 内容日历；
- 一键创建 WordPress 草稿。

升级点：

- 新选题前先检查站内是否已有相同意图页面；
- 优先推荐“更新旧内容”而不是无限新增页面；
- SERP 参考不再只抓标题，应提取意图、内容类型、信息结构和来源类型；
- AI 大纲必须加入站内已有内容、品牌知识和独特信息；
- 生成前显示重复风险和内容蚕食风险；
- Keyword Planner 改为可选数据源，不作为安装必需项。

---

## 八、AEO 与 AI 可发现性设计

## 8.1 定位原则

AEO 不应被包装成“让 ChatGPT 一定引用你”的黑盒功能。

Citeoryx 中的 AEO 应定义为：

> 检查页面是否具备被搜索系统和生成式问答系统抓取、理解、检索、提取、验证和引用的基础条件，并对可改善的内容结构、实体、证据和访问问题给出建议。

建议后台统一使用名称：

- 中文：**AI 可发现性准备度**；
- 英文：**AI Discoverability Readiness**；
- AEO 仅作为市场认知标签，不作为算法承诺。

## 8.2 三层证据模型

### A. 已验证基础层

- 页面可抓取；
- 页面可索引；
- 允许摘要展示；
- Canonical 正常；
- 主要内容可直接访问；
- 页面体验正常；
- 内容独特、可靠、对用户有价值；
- 作者、组织和页面主题清晰；
- 合适的结构化数据有效；
- GSC 中有真实表现数据。

### B. 合理支持层

- 问题与答案对应清晰；
- 开头或小节中提供直接回答；
- 复杂答案使用步骤、列表、表格或定义；
- 事实主张有可核实来源；
- 页面包含第一手经验、原始数据或独特观点；
- 内容更新时间真实；
- 重要实体名称一致；
- 页面避免大量无意义模板文字。

### C. 实验层

- `llms.txt` / `llms-full.txt`；
- 自定义 AI crawler 策略；
- Prompt 监控；
- 不同 AI 提供商的引用测试；
- Agent-friendly 页面检查；
- 新兴机器可读协议。

实验层必须：

- 默认关闭；
- 明确标注实验性质；
- 不计入 Google AI 可见性核心分数；
- 不承诺排名或引用提升；
- 允许用户选择性启用。

## 8.3 AI 可发现性检查项

### 访问与索引

- HTTP 状态；
- Robots.txt；
- Meta Robots；
- `nosnippet` / `max-snippet`；
- Canonical；
- 登录墙和内容遮挡；
- 主要内容是否依赖不可访问脚本；
- 页面是否存在软 404；
- 页面是否被错误归档。

### 答案清晰度

- 页面是否解决明确问题；
- H2/H3 是否与用户问题对应；
- 每个核心问题是否有直接答案；
- 答案是否被过长铺垫淹没；
- 是否存在定义、步骤、比较、条件和例外；
- 是否区分事实、观点和经验。

### 实体清晰度

- Organization 信息；
- Author / Reviewer 信息；
- Person / Profile 页面；
- 产品、服务、地点和品牌名称一致性；
- 同一实体的别名和引用关系；
- `sameAs` 等字段是否由现有 SEO 插件正确输出；
- 页面主体实体是否明确。

### 证据与信任

- 关键主张是否有来源；
- 引用是否与主张匹配；
- 来源是否可访问；
- 是否显示发布时间和真实更新时间；
- 是否有作者资历或审阅信息；
- 是否存在第一手经验或原始材料；
- 高风险内容是否有审阅流程。

### 结构与提取

- 标题层级；
- 语义 HTML；
- 列表、表格和步骤是否使用正确结构；
- 图片是否有说明；
- 重要数据是否只存在于图片中；
- 页面摘要是否与正文一致；
- 隐藏内容是否可访问；
- 导航、广告和正文是否容易区分。

### 结构化数据

Citeoryx 只执行：

- 检测；
- 验证；
- 类型建议；
- 冲突提示；
- 交给现有 SEO 插件配置。

除非用户明确启用“独立 Schema 模式”，否则不主动输出重复 JSON-LD。

## 8.4 Answer Blocks

提供可选 Gutenberg 区块：

- `Direct Answer`：简洁回答；
- `Key Takeaways`：核心要点；
- `Step List`：步骤；
- `Comparison Table`：比较表；
- `Evidence List`：来源与证据；
- `Definition`：术语定义；
- `Reviewed By`：审阅信息；
- `Last Verified`：核验日期；
- `FAQ Content`：可见 FAQ 内容。

注意：

- 区块服务于读者，不是为 AI 强制切块；
- FAQ 区块不默认生成 FAQ Schema；
- 结构化数据必须符合页面真实可见内容；
- 不建议为每个长尾问题生成单独薄内容页面。

## 8.5 AI Prompt Observatory

该模块作为 Pro / Experimental 功能。

功能：

- 建立 Prompt Set；
- 定义品牌词、产品词、问题词和比较词；
- 连接支持搜索和引用的 API；
- 定期运行相同 Prompt；
- 保存回答摘要、引用域名、引用 URL、品牌提及和情感；
- 对比不同时间和不同提供商；
- 发现引用竞争对手但未引用本站的场景。

数据字段：

- Provider；
- Model；
- Prompt；
- Region / Language；
- Run Time；
- Mentioned；
- Cited；
- Citation URLs；
- Position / Order；
- Sentiment；
- Response Hash；
- Cost；
- Error；
- Confidence。

重要限制：

- AI 输出存在随机性；
- 不同账号、地区、模型和时间结果可能不同；
- API 结果不等于消费者产品中的真实展示；
- 不能将一次未引用判定为页面失败；
- 必须使用多次采样和趋势，而不是单次结果。

## 8.6 llms.txt 策略

Citeoryx 可提供生成器，但必须放在实验设置中。

默认文案：

> llms.txt 是新兴的非统一约定。启用后可为支持该文件的第三方系统提供内容导航，但它不替代 robots.txt、XML Sitemap、结构化数据或正常的 SEO 工作，也不应被视为 Google AI 搜索排名因素。

可配置：

- 是否启用；
- 包含的内容类型；
- 排除内容；
- 最大条数；
- 使用摘要或 Excerpt；
- 是否生成 full 版本；
- 缓存和更新时间。

---

## 九、评分与优先级模型

## 9.1 内容健康总分

总分用于排序，不用于绝对判断。

```text
Content Health Score =
  Discoverability × 20%
+ Search Performance × 20%
+ Freshness & Relevance × 15%
+ Intent & Coverage × 15%
+ Answerability × 15%
+ Trust & Evidence × 10%
+ Link Integrity × 5%
```

### 分项定义

| 分项 | 权重 | 主要信号 |
|---|---:|---|
| Discoverability | 20 | 索引、Canonical、摘要权限、可抓取、渲染 |
| Search Performance | 20 | 展示、点击、CTR、排名、趋势 |
| Freshness & Relevance | 15 | 实质更新时间、过时事实、需求趋势 |
| Intent & Coverage | 15 | 查询匹配、主题覆盖、重复与冲突 |
| Answerability | 15 | 直接回答、结构、定义、步骤、比较 |
| Trust & Evidence | 10 | 作者、审阅、来源、实体、第一手信息 |
| Link Integrity | 5 | 入链、出链、失效链接、主题连接 |

### 硬门槛

存在以下问题时，总分显示警告而非简单扣分：

- Noindex；
- 404 / 5xx；
- Canonical 指向其他页面；
- 正文不可访问；
- 主要内容为空；
- 页面未发布；
- 站点阻止抓取。

## 9.2 AI 可发现性准备度

```text
AI Readiness Score =
  Access & Eligibility × 25%
+ Answer Clarity × 20%
+ Entity Clarity × 15%
+ Evidence & Trust × 15%
+ Structure & Extractability × 15%
+ Freshness × 10%
```

不纳入核心分数：

- 是否存在 llms.txt；
- 文章是否达到固定长度；
- 是否每段都很短；
- 是否堆叠问句；
- 是否大量重复长尾关键词；
- 是否使用某个“AI 专用 Schema”。

## 9.3 置信度

每项建议必须有置信度：

| 等级 | 条件 |
|---|---|
| High | 有 GSC、站内结构和历史趋势等多个信号一致 |
| Medium | 有两个以上信号，但缺少完整历史数据 |
| Low | 主要来自静态规则或 AI 推断，需要人工确认 |
| Insufficient Data | 数据不足，不应给出明确结论 |

## 9.4 优先级模型

```text
Priority =
  Potential Impact
× Confidence
× Urgency
× Business Value
÷ Estimated Effort
```

每个维度归一化为 1–5。

示例：

| 问题 | 影响 | 置信度 | 紧急度 | 商业价值 | 成本 | 优先级 |
|---|---:|---:|---:|---:|---:|---:|
| 高流量产品页 Noindex | 5 | 5 | 5 | 5 | 1 | 极高 |
| 低流量旧文章缺一条外链 | 1 | 3 | 1 | 1 | 2 | 低 |
| 排名 8 的高价值询盘页内容过时 | 4 | 4 | 4 | 5 | 3 | 高 |

---

## 十、数据源与集成

## 10.1 零 API 模式

安装后立即可用：

- WordPress 内容盘点；
- 本地结构检查；
- 内部链接图谱；
- 失效链接检查；
- 内容更新时间；
- SEO 插件兼容读取；
- AEO 静态准备度；
- 内容审查提醒；
- 手动任务和报告。

## 10.2 Google Search Console

用途：

- 页面表现；
- 查询表现；
- 页面 × 查询关系；
- 国家 / 设备维度；
- 索引状态相关数据；
- AI 搜索相关报告（若 API 提供相应数据）；
- 优化前后对比。

同步策略：

- 首次拉取最近 16 个月；
- 日常只拉取新增日期；
- 原始数据按日存储；
- 聚合查询异步执行；
- 处理 GSC 数据延迟；
- 网络错误、429 和 5xx 使用有上限的指数退避与 `Retry-After` 重试；
- 凭据、权限等确定性 4xx 不自动重试；
- OAuth 失败时不阻断本地功能。

## 10.3 Bing / IndexNow

用途：

- Bing 数据补充；
- 内容新建、更新和删除后通知支持 IndexNow 的搜索引擎。

兼容规则：

- 检测现有插件是否已发送 IndexNow；
- 避免重复提交；
- 仅在正式发布或 URL 状态变化后发送；
- 保存提交结果和错误。

## 10.4 GA4

可选用途：

- Organic Landing Page；
- Engagement；
- Conversion；
- Revenue；
- 页面商业价值。

GA4 不作为核心依赖，避免插件因统计配置复杂而无法使用。

## 10.5 SERP 数据

Provider Adapter：

- Google Programmable Search；
- SerpAPI；
- DataForSEO；
- Perplexity Search；
- 手动 URL；
- 未来其他服务。

统一返回结构：

```php
interface SearchProviderInterface {
    public function search(SearchQuery $query): SearchResultCollection;
    public function supports(string $feature): bool;
    public function estimateCost(SearchQuery $query): Money;
}
```

## 10.6 AI 模型

统一 Provider 接口：

```php
interface AiProviderInterface {
    public function generate(AiRequest $request): AiResponse;
    public function supportsStructuredOutput(): bool;
    public function supportsWebSearch(): bool;
    public function supportsCitations(): bool;
    public function estimateCost(AiRequest $request): Money;
}
```

支持：

- OpenAI；
- Anthropic；
- Google Gemini；
- OpenRouter；
- Ollama / 本地 OpenAI-compatible endpoint；
- Perplexity；
- 自定义兼容接口。

## 10.7 SEO 插件兼容层

```php
interface SeoPluginAdapterInterface {
    public function isActive(): bool;
    public function getTitle(int $postId): ?string;
    public function getDescription(int $postId): ?string;
    public function getCanonical(int $postId): ?string;
    public function getRobots(int $postId): RobotsDirective;
    public function getFocusKeywords(int $postId): array;
    public function getSchemaTypes(int $postId): array;
    public function openSettingsUrl(int $postId): ?string;
}
```

适配器：

- RankMathAdapter；
- YoastAdapter；
- AioseoAdapter；
- SeopressAdapter；
- NullAdapter。

---

## 十一、技术架构

## 11.1 架构原则

- 模块化单体；
- Domain / Application / Infrastructure 分层；
- PSR-4 Autoload；
- REST API 优先，减少零散 AJAX；
- Action Scheduler 处理后台任务；
- WordPress Cron 仅负责触发；
- Repository 隔离数据库；
- Provider Adapter 隔离外部服务；
- Feature Flag 控制实验功能；
- 所有扫描增量化、可恢复、可追踪。

## 11.2 推荐文件结构

```text
citeoryx/
├── citeoryx.php
├── uninstall.php
├── composer.json
├── package.json
├── readme.txt
├── src/
│   ├── Core/
│   │   ├── Plugin.php
│   │   ├── Container.php
│   │   ├── Activator.php
│   │   ├── Deactivator.php
│   │   └── Capabilities.php
│   ├── Domain/
│   │   ├── Content/
│   │   ├── Health/
│   │   ├── Issue/
│   │   ├── Link/
│   │   ├── Query/
│   │   ├── Topic/
│   │   ├── Aeo/
│   │   └── Report/
│   ├── Application/
│   │   ├── Scan/
│   │   ├── Analyze/
│   │   ├── Prioritize/
│   │   ├── Optimize/
│   │   ├── Monitor/
│   │   └── Export/
│   ├── Infrastructure/
│   │   ├── Database/
│   │   ├── Queue/
│   │   ├── Cache/
│   │   ├── Http/
│   │   ├── Encryption/
│   │   └── Logging/
│   ├── Integrations/
│   │   ├── Google/
│   │   ├── Bing/
│   │   ├── SeoPlugins/
│   │   ├── SearchProviders/
│   │   └── AiProviders/
│   ├── Rest/
│   │   ├── Controllers/
│   │   └── Schema/
│   ├── Admin/
│   │   ├── Menu.php
│   │   ├── Assets.php
│   │   └── Notices.php
│   ├── Blocks/
│   ├── Cli/
│   └── Support/
├── assets/
│   ├── src/
│   │   ├── admin/
│   │   └── blocks/
│   └── build/
├── templates/
├── languages/
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── E2E/
└── vendor/
```

## 11.3 后台前端

推荐技术：

- React；
- `@wordpress/components`；
- `@wordpress/data`；
- `@wordpress/api-fetch`；
- WordPress Scripts；
- REST API；
- CSS Variables 适配 WordPress 后台；
- 不引入大型 UI 框架。

## 11.4 扫描策略

### 本地内容扫描

优先读取：

- `post_content`；
- Gutenberg block tree；
- Post Meta；
- SEO 插件公开字段；
- WordPress permalink；
- Attachment metadata。

仅在需要验证最终渲染时请求前台 URL。

### 前台渲染扫描

- 使用 `wp_safe_remote_get()`；
- 限制协议为 HTTP / HTTPS；
- 阻止私网 IP 和本地地址；
- 限制重定向次数；
- 设置超时；
- 限制响应大小；
- 不执行任意 JavaScript；
- 大站点分批扫描；
- 支持暂停和恢复。

### 增量规则

重新扫描条件：

- `post_modified_gmt` 变化；
- SEO 元数据变化；
- 插件规则版本变化；
- 链接目标状态过期；
- 用户手动请求；
- 定期完整复核。

---

## 十二、数据库设计

为了兼容 WordPress 支持的旧数据库，不强制使用原生 JSON 字段；结构化数据使用 `LONGTEXT` 保存 JSON，并通过应用层校验。

## 12.1 内容资产表

```sql
CREATE TABLE {prefix}cx_content_items (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  object_id             BIGINT UNSIGNED NULL,
  object_type           VARCHAR(50) NOT NULL,
  post_type             VARCHAR(50) NULL,
  canonical_url         VARCHAR(2048) NOT NULL,
  url_hash              CHAR(32) NOT NULL,
  language_code         VARCHAR(20) NULL,
  status                VARCHAR(30) NOT NULL DEFAULT 'unknown',
  health_score          DECIMAL(5,2) NULL,
  health_confidence     VARCHAR(20) NULL,
  ai_readiness_score    DECIMAL(5,2) NULL,
  content_hash          CHAR(64) NULL,
  published_at          DATETIME NULL,
  modified_at           DATETIME NULL,
  last_scanned_at       DATETIME NULL,
  last_reviewed_at      DATETIME NULL,
  assigned_user_id      BIGINT UNSIGNED NULL,
  metadata_json         LONGTEXT NULL,
  created_at            DATETIME NOT NULL,
  updated_at            DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_url_hash (url_hash),
  KEY idx_object (object_type, object_id),
  KEY idx_status (status),
  KEY idx_health (health_score),
  KEY idx_modified (modified_at)
) {charset_collate};
```

## 12.2 每日表现表

```sql
CREATE TABLE {prefix}cx_metrics_daily (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  content_id      BIGINT UNSIGNED NOT NULL,
  metric_date     DATE NOT NULL,
  source          VARCHAR(30) NOT NULL,
  impressions     DECIMAL(14,2) NULL,
  clicks          DECIMAL(14,2) NULL,
  ctr             DECIMAL(8,6) NULL,
  position_avg    DECIMAL(8,3) NULL,
  sessions        DECIMAL(14,2) NULL,
  conversions     DECIMAL(14,2) NULL,
  revenue         DECIMAL(14,2) NULL,
  extra_json      LONGTEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_content_date_source (content_id, metric_date, source),
  KEY idx_metric_date (metric_date),
  KEY idx_content (content_id)
) {charset_collate};
```

## 12.3 查询页面关系表

```sql
CREATE TABLE {prefix}cx_query_pages (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  content_id      BIGINT UNSIGNED NOT NULL,
  source          VARCHAR(30) NOT NULL DEFAULT 'unknown',
  query_text      VARCHAR(500) NOT NULL,
  query_hash      CHAR(32) NOT NULL,
  country_code    VARCHAR(8) NULL,
  device          VARCHAR(20) NULL,
  period_start    DATE NOT NULL,
  period_end      DATE NOT NULL,
  impressions     DECIMAL(14,2) NULL,
  clicks          DECIMAL(14,2) NULL,
  ctr             DECIMAL(8,6) NULL,
  position_avg    DECIMAL(8,3) NULL,
  intent          VARCHAR(30) NULL,
  topic_id        BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_query_dimension_period (content_id, source, query_hash, country_code, device, period_start, period_end),
  KEY idx_query_hash (query_hash),
  KEY idx_content_period (content_id, period_start, period_end),
  KEY idx_dimension_period (country_code, device, period_end),
  KEY idx_topic (topic_id)
) {charset_collate};
```

## 12.4 问题表

```sql
CREATE TABLE {prefix}cx_issues (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  content_id        BIGINT UNSIGNED NULL,
  issue_code        VARCHAR(100) NOT NULL,
  category          VARCHAR(50) NOT NULL,
  severity          VARCHAR(20) NOT NULL,
  confidence        VARCHAR(20) NOT NULL,
  status            VARCHAR(20) NOT NULL DEFAULT 'open',
  impact_score      DECIMAL(5,2) NULL,
  effort_score      DECIMAL(5,2) NULL,
  priority_score    DECIMAL(8,3) NULL,
  title             VARCHAR(500) NOT NULL,
  evidence_json     LONGTEXT NULL,
  recommendation    LONGTEXT NULL,
  first_seen_at     DATETIME NOT NULL,
  last_seen_at      DATETIME NOT NULL,
  resolved_at       DATETIME NULL,
  ignored_until     DATETIME NULL,
  assigned_user_id  BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_content_status (content_id, status),
  KEY idx_issue_code (issue_code),
  KEY idx_priority (priority_score),
  KEY idx_category (category)
) {charset_collate};
```

## 12.5 链接表

```sql
CREATE TABLE {prefix}cx_links (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_content_id BIGINT UNSIGNED NOT NULL,
  target_content_id BIGINT UNSIGNED NULL,
  target_url         VARCHAR(2048) NOT NULL,
  target_url_hash    CHAR(32) NOT NULL,
  anchor_text        VARCHAR(1000) NULL,
  link_context       VARCHAR(50) NULL,
  rel_flags          VARCHAR(255) NULL,
  http_status        SMALLINT NULL,
  is_internal        TINYINT(1) NOT NULL DEFAULT 0,
  first_seen_at      DATETIME NOT NULL,
  last_seen_at       DATETIME NOT NULL,
  last_checked_at    DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_source (source_content_id),
  KEY idx_target_content (target_content_id),
  KEY idx_target_hash (target_url_hash),
  KEY idx_http_status (http_status)
) {charset_collate};
```

## 12.6 扫描任务表

```sql
CREATE TABLE {prefix}cx_scan_runs (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  scan_type       VARCHAR(50) NOT NULL,
  status          VARCHAR(20) NOT NULL,
  total_items     INT UNSIGNED NOT NULL DEFAULT 0,
  processed_items INT UNSIGNED NOT NULL DEFAULT 0,
  failed_items    INT UNSIGNED NOT NULL DEFAULT 0,
  trigger_type    VARCHAR(30) NOT NULL,
  started_at      DATETIME NULL,
  finished_at     DATETIME NULL,
  config_json     LONGTEXT NULL,
  summary_json    LONGTEXT NULL,
  error_log       LONGTEXT NULL,
  PRIMARY KEY (id),
  KEY idx_status (status),
  KEY idx_started (started_at)
) {charset_collate};
```

## 12.7 AI Prompt 观察表

```sql
CREATE TABLE {prefix}cx_ai_prompt_runs (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  prompt_set_id     BIGINT UNSIGNED NOT NULL,
  provider          VARCHAR(50) NOT NULL,
  model             VARCHAR(100) NULL,
  prompt_hash       CHAR(64) NOT NULL,
  region_code       VARCHAR(20) NULL,
  language_code     VARCHAR(20) NULL,
  mentioned         TINYINT(1) NULL,
  cited             TINYINT(1) NULL,
  citation_json     LONGTEXT NULL,
  response_summary  LONGTEXT NULL,
  response_hash     CHAR(64) NULL,
  cost_amount       DECIMAL(12,6) NULL,
  confidence        VARCHAR(20) NULL,
  run_at            DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_prompt_set (prompt_set_id),
  KEY idx_run_at (run_at),
  KEY idx_provider (provider)
) {charset_collate};
```

---

## 十三、REST API 设计

命名空间：

```text
/wp-json/citeoryx/v1/
```

### 13.1 主要端点

| 方法 | 端点 | 说明 |
|---|---|---|
| GET | `/dashboard` | 总览数据 |
| GET | `/content` | 内容资产列表 |
| GET | `/content/{id}` | 单页详情 |
| POST | `/content/{id}/scan` | 扫描单页 |
| POST | `/content/{id}/analyze` | 重新分析 |
| GET | `/content/{id}/issues` | 单页问题 |
| GET | `/issues` | 问题列表 |
| PATCH | `/issues/{id}` | 更新状态、负责人或忽略时间 |
| POST | `/scans` | 创建扫描任务 |
| GET | `/scans/{id}` | 查询进度 |
| POST | `/recommendations/generate` | AI 生成建议 |
| POST | `/recommendations/apply` | 创建草稿修订，不直接发布 |
| GET | `/links` | 链接图谱数据 |
| GET | `/topics` | 主题集群 |
| POST | `/planning/brief` | 生成内容 Brief |
| GET | `/aeo/content/{id}` | AI 准备度详情 |
| POST | `/ai-observatory/run` | 运行 Prompt Set |
| GET | `/reports` | 报告列表 |
| POST | `/exports` | 创建导出任务 |

### 13.2 权限

自定义 Capabilities：

- `citeoryx_view_dashboard`；
- `citeoryx_view_content`；
- `citeoryx_run_scans`；
- `citeoryx_manage_issues`；
- `citeoryx_use_ai`；
- `citeoryx_apply_changes`；
- `citeoryx_manage_integrations`；
- `citeoryx_manage_settings`；
- `citeoryx_export_data`。

默认映射：

| WordPress 角色 | 默认权限 |
|---|---|
| Administrator | 全部 |
| Editor | 查看、扫描、处理问题、使用 AI、创建修订 |
| Author | 仅查看和处理自己内容 |
| Contributor | 查看自己内容和建议，不可应用变更 |
| SEO Manager | 插件新增角色，可管理内容但不能管理插件密钥 |

---

## 十四、后台任务与自动化

使用 Action Scheduler，不依赖单次长请求。

### 14.1 计划任务

| 任务 | 默认频率 | 说明 |
|---|---|---|
| 内容变更检测 | 每小时 | 找出新增和修改内容 |
| 本地增量扫描 | 每日 | 扫描变化页面 |
| GSC 同步 | 每日 | 拉取新增数据 |
| 链接状态检查 | 每周 | 只检查过期或高价值链接 |
| 健康规则重算 | 每周 | 重新计算趋势问题 |
| 高价值内容复核 | 每月 | 按站点画像选择 |
| Prompt Observatory | 每周或手动 | 默认关闭 |
| 数据清理 | 每月 | 清理超期日志和缓存 |
| 周报 | 每周 | 邮件或后台通知 |

### 14.2 自动化规则

示例：

```text
WHEN 高价值页面点击下降 > 25% 且持续两周
THEN 创建“内容衰退”高优先级任务
AND 通知 Editor
```

```text
WHEN 页面发布超过 365 天且从未审查
AND 页面仍有每月展示
THEN 创建“内容复核”任务
```

```text
WHEN 优化任务完成 28 天
THEN 生成前后表现对比
```

```text
WHEN 发现基础 SEO 插件已处理 IndexNow
THEN 禁用 Citeoryx 重复提交
```

---

## 十五、安全、隐私与合规

## 15.1 API Key 与 Token

原方案中使用 `wp_hash()` 存储可再次调用的 API Key 不正确，因为 Hash 不可逆。

推荐优先级：

1. `wp-config.php` 常量；
2. 环境变量；
3. 使用 Sodium / OpenSSL 加密后写入 Options；
4. 使用 WordPress Salts 派生加密密钥；
5. 后台只显示掩码；
6. 禁止在日志中记录完整密钥。

示例：

```php
define('CITEORYX_OPENAI_API_KEY', '...');
define('CITEORYX_GOOGLE_CLIENT_SECRET', '...');
```

## 15.2 外部请求安全

- 使用 `wp_safe_remote_get()` / `wp_safe_remote_post()`；
- 防止 SSRF；
- 禁止访问私网 IP、Metadata Endpoint 和本机地址；
- 限制响应体大小；
- 限制超时和重定向；
- 验证 Content-Type；
- 不执行抓取页面脚本；
- 不渲染未经净化的外部 HTML。

## 15.3 WordPress 安全

- REST Nonce；
- Capability Check；
- 输入 Sanitization；
- 输出 Escaping；
- SQL Prepared Statements；
- CSRF 防护；
- 禁止未授权批量操作；
- 文件导出使用一次性签名 URL；
- 所有自动修改保留 Revision。

## 15.4 AI 数据隐私

发送前显示：

- 将发送哪些内容；
- 发送给哪个 Provider；
- 是否包含草稿、作者、客户或用户信息；
- 是否启用外部搜索；
- 预计 Token 和费用。

可选功能：

- 自动移除邮件、电话、订单号等 PII；
- 只发送选中段落；
- 不保存 Provider 原始回答；
- 自定义数据保留时间；
- 本地模型模式。

## 15.5 WordPress Privacy API

插件需要：

- 隐私政策建议文本；
- 个人数据导出器；
- 个人数据擦除器；
- 明确外部服务数据流；
- 卸载时允许选择保留或删除数据。

---

## 十六、性能设计

### 16.1 前台零负担原则

- 默认不在前台加载 JS / CSS；
- 只有使用 Citeoryx Blocks 时才加载对应资产；
- 不在每次页面访问时运行分析；
- 所有分析在后台任务中完成；
- 不修改正常查询主流程。

### 16.2 大站点策略

| 站点规模 | 默认策略 |
|---|---|
| < 500 URL | 可一次完成初始扫描 |
| 500–5,000 URL | 分批后台扫描 |
| 5,000–50,000 URL | 增量扫描 + WP-CLI 推荐 |
| > 50,000 URL | Agency / Remote Worker 模式 |

### 16.3 数据保留

默认：

- 每日指标：16 个月；
- 扫描日志：90 天；
- 已解决问题：12 个月；
- AI 原始响应：30 天或不保存；
- 聚合数据：长期保留；
- 支持手动立即清理。

---

## 十七、错误与问题代码

建议使用稳定 Issue Code，便于测试、文档和外部集成。

### 17.1 Discoverability

- `CX_INDEX_NOINDEX`
- `CX_INDEX_BLOCKED_ROBOTS`
- `CX_INDEX_CANONICAL_EXTERNAL`
- `CX_INDEX_SOFT_404`
- `CX_INDEX_SNIPPET_BLOCKED`
- `CX_RENDER_MAIN_CONTENT_MISSING`

### 17.2 Performance

- `CX_PERF_CONTENT_DECAY`
- `CX_PERF_CTR_OPPORTUNITY`
- `CX_PERF_STRIKING_DISTANCE`
- `CX_PERF_QUERY_LOSS`
- `CX_PERF_SEASONAL_CHANGE`

### 17.3 Content

- `CX_CONTENT_STALE`
- `CX_CONTENT_THIN_VALUE`
- `CX_CONTENT_DUPLICATE_INTENT`
- `CX_CONTENT_FACT_REVIEW`
- `CX_CONTENT_MISSING_UNIQUE_VALUE`
- `CX_CONTENT_TITLE_STRUCTURE`

### 17.4 Links

- `CX_LINK_ORPHANED`
- `CX_LINK_BROKEN_INTERNAL`
- `CX_LINK_BROKEN_EXTERNAL`
- `CX_LINK_WEAK_ANCHOR`
- `CX_LINK_CLUSTER_GAP`
- `CX_LINK_HIGH_VALUE_LOW_INLINK`

### 17.5 AEO

- `CX_AEO_NO_DIRECT_ANSWER`
- `CX_AEO_ENTITY_UNCLEAR`
- `CX_AEO_AUTHOR_UNCLEAR`
- `CX_AEO_EVIDENCE_MISSING`
- `CX_AEO_CLAIM_SOURCE_MISMATCH`
- `CX_AEO_STRUCTURE_HARD_TO_EXTRACT`
- `CX_AEO_SCHEMA_CONFLICT`
- `CX_AEO_DATE_NOT_TRUSTWORTHY`
- `CX_AEO_AI_CRAWLER_POLICY_REVIEW`

### 17.6 Planning

- `CX_PLAN_EXISTING_PAGE_MATCH`
- `CX_PLAN_CANNIBALIZATION_RISK`
- `CX_PLAN_TOPIC_GAP`
- `CX_PLAN_REFRESH_BEFORE_NEW`

---

## 十八、报告系统

## 18.1 周报

- 新增问题；
- 已解决问题；
- 高优先级任务；
- 新增衰退页面；
- 新增机会页面；
- 优化后开始增长的页面；
- 数据同步异常。

## 18.2 月报

- 健康趋势；
- 内容状态分布；
- 点击和展示变化；
- 优化页面贡献；
- 内容衰退恢复情况；
- 新内容与旧内容更新效果对比；
- AI 可发现性准备度变化；
- AI Prompt 观察趋势；
- 下月建议优先级。

## 18.3 优化效果报告

每次优化保存：

- 修改前内容 Hash；
- 修改后内容 Hash；
- 修改类型；
- 处理问题；
- 发布日期；
- 7 / 28 / 90 天表现；
- 是否达到目标；
- 是否回滚；
- 备注。

避免把所有表现变化归因于插件，报告中使用“相关变化”而不是“插件带来”。

---

## 十九、MVP 范围

## 19.1 必须完成

1. 安装向导和站点画像；
2. 内容资产盘点；
3. 现有 SEO 插件检测与只读适配；
4. 本地内容结构扫描；
5. 内部链接图谱；
6. 孤儿页和失效链接；
7. GSC OAuth 和增量同步；
8. 内容衰退；
9. Striking Distance；
10. 高展示低 CTR；
11. 基础 Cannibalization；
12. 内容健康分项；
13. AI 可发现性基础检查；
14. 问题与机会列表；
15. 优化工作台；
16. 手动 AI 建议，BYOK；
17. 周报；
18. Action Scheduler；
19. REST API；
20. Rank Math、Yoast、AIOSEO 基础兼容。

## 19.2 MVP 不做

- 自动批量发布 AI 文章；
- 自建完整 Rank Tracker；
- 自建全功能爬虫替代 Screaming Frog；
- 自动修改 Canonical / Noindex；
- 自动输出全套 Schema；
- 自动删除或合并文章；
- 所有 AI Provider 一次性全部支持；
- 多站点 Agency SaaS；
- 自动化 Prompt Observatory；
- 复杂反向链接数据库；
- 自建 Keyword Planner 替代服务。

---

## 二十、开发路线图

### Milestone 0：基础重构

- RankDraft 数据迁移；
- 命名空间和目录重构；
- Service Container；
- REST 基础；
- Action Scheduler；
- 权限系统；
- 日志和 Feature Flag。

### Milestone 1：内容资产与本地扫描

- Inventory；
- Block Parser；
- Link Graph；
- SEO Plugin Adapters；
- Issue Engine；
- Dashboard。

### Milestone 2：GSC 与机会引擎

- OAuth；
- Metrics Sync；
- Decay；
- CTR Opportunity；
- Striking Distance；
- Cannibalization；
- 优先级。

### Milestone 3：优化工作台

- 单页详情；
- Evidence Panel；
- 修改建议；
- Revision Diff；
- 内链建议；
- 完成与验证流程。

### Milestone 4：AI 可发现性

- Readiness Rules；
- Author / Organization / Entity 检查；
- Evidence 检查；
- Answer Blocks；
- Schema 冲突检测；
- 实验功能开关。

### Milestone 5：内容规划迁移

- 原关键词库迁移；
- SERP Provider Adapter；
- Topic Cluster；
- Existing Content Match；
- Brief；
- Draft Creation。

### Milestone 6：Pro 与 Agency

- Prompt Observatory；
- 多站点；
- White-label 报告；
- Webhook；
- Remote Worker；
- 团队审批；
- API Usage Budget。

---

## 二十一、原 RankDraft 功能迁移

| 原模块 | 新位置 | 处理方式 |
|---|---|---|
| GSC 关键词拉取 | Integrations / Google + Planning | 保留并升级为页面 × 查询历史数据 |
| Keyword Planner | Planning Provider | 改为可选，不作为核心依赖 |
| SERP Fetcher | Search Provider Adapter | 重构，支持多个数据源 |
| Source Scorer | Evidence Evaluator | 删除单纯 DA 权重，改为主张相关性和来源质量 |
| AI Writer | AI Provider + Optimizer | 拆成分析、建议、Brief 和草稿功能 |
| Content Plans | Planning | 保留并增加已有内容匹配 |
| Trends | Planning / Opportunities | 与 GSC 趋势和季节性合并 |
| 外链资源库 | Evidence Library | 改为来源类型和证据库 |
| 12 小时 Transient | Cache Layer | 按 Provider 和数据类型配置 TTL |
| AJAX 接口 | REST API | 逐步废弃旧 AJAX |
| `cp_` 旧数据表 | `cx_` 新数据表 | 编写可回滚迁移器 |

### 21.1 数据迁移策略

- 读取旧表但不立即删除；
- 创建 v2 表；
- 分批迁移；
- 记录迁移版本；
- 迁移后进行校验；
- 保留一版只读回滚；
- 用户确认后再清理旧表；
- 旧 Shortcode 和设置提供兼容提示。

---

## 二十二、测试要求

## 22.1 单元测试

- 各健康规则；
- 评分边界；
- 衰退算法；
- Cannibalization 归一化；
- URL 和 Canonical 处理；
- Provider Response Mapping；
- Encryption；
- 权限。

## 22.2 集成测试

- WordPress CRUD；
- REST；
- Action Scheduler；
- GSC Mock；
- SEO Plugin Adapters；
- 数据库迁移；
- 多语言；
- WooCommerce。

## 22.3 E2E

- 安装向导；
- 连接 GSC；
- 启动扫描；
- 查看问题；
- 生成 AI 建议；
- 创建 Revision；
- 发布；
- 查看优化效果。

## 22.4 兼容性测试矩阵

| 项目 | 最低目标 | 推荐测试 |
|---|---|---|
| PHP | 8.0 | 8.0 / 8.1 / 8.2 / 8.3 / 8.4 |
| WordPress | 6.6 | 最新稳定版及前两个大版本 |
| MySQL | 5.7 | 8.0 |
| MariaDB | 10.4 | 10.11 |
| Gutenberg | Core | 最新插件版 |
| WooCommerce | 8.x | 最新稳定版 |
| Multisite | 暂不承诺 | 安装和停用不报错 |

> WordPress 当前推荐环境可高于插件最低兼容环境。插件最低版本应根据目标用户和维护成本最终确定。

## 22.5 性能验收

- 前台无新增查询或仅可忽略的常量级查询；
- 后台列表分页；
- 1,000 页面扫描不中断；
- 任务失败可重试；
- 扫描暂停后可恢复；
- 日志可定位到具体 URL 和规则；
- 无单次请求长时间占用 PHP Worker。

---

## 二十三、风险与缓解

| 风险 | 影响 | 缓解措施 |
|---|---|---|
| GSC 数据延迟 | 用户误判当天变化 | 明确数据截止日期，不显示“实时” |
| 季节性误判为衰退 | 错误任务 | 加入 90 天、去年同期和站点基线 |
| AI 建议出现幻觉 | 内容风险 | 标注待核实事实，不自动发布 |
| Schema 重复 | 前台冲突 | 默认只读适配，不重复输出 |
| Cron 不稳定 | 扫描中断 | Action Scheduler、Site Health 检测、WP-CLI |
| 大站点资源过高 | 主机负载 | 分批、锁、限速、增量和 Remote Worker |
| SERP API 成本 | 用户费用不可控 | 预算、预估、缓存、手动模式 |
| 第三方 API 变更 | 功能失效 | Adapter、Capability Detection、降级 |
| 评分造成误导 | 用户错误修改 | 分项、证据、置信度和人工确认 |
| AEO 过度营销 | 信任风险 | 明确准备度而非保证引用 |
| `llms.txt` 被神化 | 错误产品方向 | 实验开关，不计核心分数 |
| SEO 插件字段变化 | 兼容失败 | 版本检测、适配测试和 Null Adapter |

---

## 二十四、版本与商业功能建议

## 24.1 Free

- 最多 500 个内容 URL；
- 本地内容库存；
- 基础健康检查；
- 孤儿页；
- 基础失效链接；
- 手动扫描；
- 基础 AI 准备度；
- CSV 导出；
- 无需账号。

## 24.2 Pro

- GSC 历史与持续同步；
- 内容衰退；
- CTR 和 Striking Distance；
- Cannibalization；
- AI Provider；
- 优化工作台；
- 自动化规则；
- 周报 / 月报；
- 高级 AEO；
- Prompt Observatory；
- 自定义规则和数据保留。

## 24.3 Agency

- 多站点控制台；
- White-label；
- 客户报告；
- 团队角色；
- Webhook；
- Remote Worker；
- 批量策略；
- 统一 API 预算；
- 客户站点健康基线比较。

---

## 二十五、最终产品判断

Citeoryx 最有价值的方向不是“比 Rank Math 多几个 SEO 检查”，也不是“比 AI 写作插件多几个 Prompt”。

真正可形成长期竞争力的产品核心是：

```text
把 WordPress 中分散的内容、查询、链接、历史表现、作者、实体、证据和优化记录
组织成一个持续更新的内容健康图谱，
再把图谱中的问题转化为有证据、有优先级、可执行、可验证的内容任务。
```

推荐最终产品结构：

```text
Citeoryx
├── Content Inventory
├── Content Health Engine
├── Opportunity Engine
├── Link & Topic Graph
├── Optimization Workspace
├── AI Discoverability Readiness
├── Planning & Briefs
└── Reports & Automations
```

其中：

- **内容健康度**是产品入口；
- **问题优先级**是日常使用价值；
- **优化工作台**是执行中心；
- **效果验证**是建立信任的关键；
- **AI 可发现性**是差异化模块；
- **关键词研究和 AI 写作**只是辅助能力。

---

## 附录 A：研究与设计依据

截至 2026 年 7 月，本方案重点参考以下公开产品方向和官方指南：

- Google Search Central：生成式 AI 搜索优化、Helpful Content、结构化数据、作者和组织实体；
- WordPress 官方：插件安全、隐私、REST API、运行环境；
- Action Scheduler：WordPress 后台任务和可追踪队列；
- Rank Math：Content AI、GSC Rank Tracking；
- Yoast：SEO Workouts、Orphaned Content、Internal Linking；
- AIOSEO：Site Audit、Search Statistics、LLMS 文件生成；
- Surfer：Content Audit、Content Editor、自动内部链接；
- Clearscope：Content Inventory、Content Decay、Alerts；
- Semrush：SEO Writing Assistant、Content Audit、AI Visibility；
- Perplexity API：Search、Sonar、引用结果；
- Microsoft IndexNow：内容变更通知协议。

产品吸收这些工具已经验证的工作流，但避免重复其基础 SEO 输出，并对尚未形成统一标准的 AEO 功能保持实验性和透明说明。

---

## 附录 B：首版验收清单

- [ ] 不安装任何外部服务也能完成本地扫描；
- [ ] 检测到 Rank Math / Yoast / AIOSEO 时不输出重复 Meta 和 Schema；
- [ ] 所有问题显示证据和置信度；
- [ ] 内容衰退不会只基于两个短周期；
- [ ] Cannibalization 不会只基于关键词重复；
- [ ] AI 建议默认不自动发布；
- [ ] AEO 页面明确区分基础、支持和实验功能；
- [ ] llms.txt 不计入 Google AI 核心评分；
- [ ] 所有后台任务可查看、重试和取消；
- [ ] API Key 不以明文出现在日志和页面源代码；
- [ ] 前台默认不加载插件资产；
- [ ] 插件停用不删除数据；
- [ ] 卸载时由管理员选择保留或删除；
- [ ] 支持完整导出和隐私擦除；
- [ ] 原 RankDraft 数据可迁移和回滚。
