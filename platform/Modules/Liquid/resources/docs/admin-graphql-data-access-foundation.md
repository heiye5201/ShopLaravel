# Admin GraphQL 数据获取底座设计思考

> 状态：架构探索稿，供方案评审使用，不代表已进入实施。
>
> 范围：Admin GraphQL 的查询取数、对象解析、关联加载、分页与兼容边界；同时说明它与 Liquid Drop 的关系。Mutation 的业务写入只讨论边界，不展开具体领域流程。

## 1. 结论先行

Admin GraphQL 的主要建设成本不在于把 SDL（Schema Definition Language，模式定义语言）中的 `type Product`、`type Order` 写出来，而在于建立一套稳定的数据获取语义：同一个业务对象如何定位、查询、过滤、分页、加载关联、执行权限裁剪，并最终形成可长期演进的 API 契约。

我的核心判断是：

1. **GraphQL Object 和 Liquid Drop 都不是数据源。** 它们是两个面向不同消费者的展示边界。Object 面向 Admin API 客户端，Drop 面向 Storefront 模板，权限、字段、空值与性能要求都不同。
2. **两者不应该直接复用。** 应该复用它们下面的领域读取接口、读取模型和业务语义，再各自完成安全投影。
3. **现有 REST Service 可以作为迁移入口，但不能直接等同于未来的数据获取底座。** 当 Service 返回 HTTP Resource、依赖全局 Request、混合 Model/array/JSON 等多种形状时，GraphQL Mapper 只能不断吸收不一致，最终形成另一套难维护的“影子领域模型”。
4. **先建一条纵向样板，再扩领域。** 建议以自动折扣的只读查询为第一个样板，先验证 root query、JSON 规则结构类型化、目标资源批量加载、分页、权限和可观测性，再推广到其他折扣类型及 Product、Order、Customer 等领域。

换句话说，这项工作的正确目标不是“让现有对象能从 GraphQL 返回”，而是：

> 建立一条与 HTTP 表现层解耦、可批量加载、可授权、可测试、可被多个展示边界复用的领域读取链路。

## 术语说明

本文保留英文术语，是为了能够与 GraphQL 标准、代码类名和第三方库对应；首次阅读时可以按以下中文含义理解：

| 术语 | 中文含义 | 本文中的职责 |
| --- | --- | --- |
| SDL（Schema Definition Language） | 模式定义语言 | 用文本定义 GraphQL 的对象、字段、参数、类型和空值规则，即 API 合同 |
| Schema | 模式/API 结构 | GraphQL 对外可查询能力的完整定义；SDL 是其文本表达方式 |
| Object | 对象类型 | 例如 `Product`、`Order`，定义客户端可以读取哪些字段，不负责查询数据库 |
| Resolver | 解析器 | 接收 GraphQL 字段参数，调用下层读取能力，并把结果交给 GraphQL 运行时 |
| Root Query | 根查询字段 | GraphQL 查询入口，例如 `automaticDiscounts`、`automaticDiscount` |
| Field Resolver | 字段解析器 | 解析对象的某个字段，尤其用于按需加载 `targets` 等关联数据 |
| REST Controller | REST 控制器 | 接收 REST HTTP 请求并组织响应的入口层 |
| Liquid Drop | Liquid 数据投影对象 | 控制 Storefront 模板能访问哪些数据；它不是领域模型，也不是数据库访问层 |
| Criteria | 查询条件对象 | 统一表达筛选、排序、分页和游标条件 |
| ReadContext | 读取上下文 | 携带店铺/租户、当前身份、权限、API 版本、语言、时区和请求标识 |
| Domain Reader | 领域读取器 | 按领域封装稳定取数入口，例如 `AutomaticDiscountReader`、`ProductReader` |
| Read Model | 读取模型 | Reader 返回的稳定内部数据对象，与 HTTP Resource 和数据库 Model 解耦 |
| Connection Slice | 连接分页切片 | 表达一页 nodes、是否还有前后页及 cursor 等分页结果 |
| DataLoader / Batch Loader | 批量数据加载器 | 将同一请求中的多个关联 ID 合并查询，避免 N+1 查询 |
| Presenter | 展示适配器 | 将 Read Model 转换为 GraphQL schema 约定的字段、ID、枚举和时间格式 |
| Projection | 数据投影/输出适配 | 从同一 Read Model 中选择某个消费端允许看到的字段 |
| Mapper | 映射器 | 把一种数据结构转换为另一种结构；本文建议它只做简单契约映射 |
| Repository | 数据仓储 | 封装数据库查询和持久化访问，不直接承担 GraphQL 或 Liquid 输出职责 |
| Laravel Resource | Laravel 响应资源 | 面向 HTTP JSON 响应的转换层，不应作为 Domain Reader 的标准返回类型 |
| Cursor | 游标 | 表示列表读取位置、对客户端不透明的标记，用于稳定翻页 |
| Request-scoped | 请求级 | 实例只在单次请求内存在，请求结束后释放，避免跨请求数据污染 |
| Allowlist | 允许字段白名单 | 只输出经过明确批准的字段，未列入字段默认不暴露 |
| Facade | 门面/适配层 | 在不重写既有业务逻辑的前提下，为其提供新的 GraphQL 调用入口 |
| Runtime | 运行时 | 实际解析、校验并执行 GraphQL 请求的程序部分 |
| Introspection | 内省查询 | GraphQL 客户端或测试读取运行时 Schema 的标准机制 |
| Typed | 类型化 | 使用明确对象、输入类型或枚举，而不是依赖含义不确定的 JSON |

文中出现 `ProductReader`、`ReadContext` 等英文复合词时，通常既是在表达架构角色，也是在建议未来代码类名；中文用于解释职责，英文用于与实现保持一致。

## 2. 本次判断所依据的项目现状

### 2.1 当前工作分支

当前工作分支没有 `platform/Modules/AdminGraphQl`，已有 GraphQL 主要是：

- `platform/graphql/schema.graphql` 与 `platform/Modules/OnlineStoreThemeGraphQl`：基于 Lighthouse 的主题编辑 GraphQL。
- `platform/Modules/Liquid/Drops`：Storefront Liquid 全局 Drop。
- 既有业务读取链路：Controller、Service、Repository、Eloquent Model、Laravel Resource 等多种组合。

Liquid Drop 当前也没有统一取数模式。例如：

- `ShopDrop` 直接读取 Option 或调用支付 Service；
- `PaymentDrop` 调用 Resource Service，并解析 Laravel Resource；
- `ProductReviewSchemaDrop` 一次性返回数组；
- 商品页面的 `product` 主要由 Controller 传入缓存数组，并不是一个 Product Drop。

因此，“Drop 已经是统一领域对象”这个前提目前并不成立。

### 2.2 候选 Admin GraphQL 分支

本仓库本地 `feature/graphql-product` 分支包含一套候选 Admin GraphQL facade，覆盖折扣、商品、分类、库存、订单、弃单、顾客和标签。其优点是：

- 独立 Admin endpoint、版本、feature gate、认证与查询限制；
- Provider 前执行 operation 权限检查；
- 写路径优先复用既有 Service/FormRequest，以保留业务副作用；
- Mapper 试图通过 allowlist 收敛输出；
- 有统一 Global ID、Connection 配置和错误格式。

但候选实现也暴露了数据底座尚未建立的问题：

- `ProductProvider` 从 `ProductService::getProductLists()` 返回的 `ProductCollection->resource` 中反向取得 paginator；
- `OrderProvider` 为兼容既有分页逻辑，会临时替换容器中的全局 `request`；
- Mapper 同时兼容 Model、Collection、array、snake_case、camelCase 和 JSON string；
- `ProductMapper`、`OrderMapper` 已承担关联加载后的结构猜测、JSON 解码、字段重命名和类型转换；
- schema 同时存在版本化 SDL 和 `AdminGraphQlSchemaRegistry` 中的 PHP ObjectType 定义，而运行时主要由 PHP 再构造一遍，存在双事实源风险；
- 多个 input/filter 仍使用 `JSON` scalar，类型安全只停留在输出的一部分；
- root Provider 通常先拿完整对象或 REST 详情形状，再根据 GraphQL 字段输出，尚未形成按字段批量加载关联的机制。

这套实现适合作为“REST 能力的受控 GraphQL facade”验证，但若继续逐字段、逐领域追加 Mapper，它的边际成本会越来越高。

## 3. 先把 Object、Drop 和数据对象分清楚

同一个“商品”在系统里其实至少有四种含义：

| 名称 | 职责 | 是否可直接取数 | 是否可跨端直接复用 |
| --- | --- | --- | --- |
| Eloquent Model / 持久化对象 | 表达数据库记录与关系 | 可以，但不应由展示层任意查询 | 否，字段过宽且带 ORM 行为 |
| Domain Read Model | 表达某个稳定业务读取结果 | 由 Reader/Loader 构造 | 是，适合成为底层复用点 |
| GraphQL Object | 定义 Admin API 对外字段契约 | 否，通过 resolver 读取 | 否，只属于 Admin GraphQL 契约 |
| Liquid Drop | 定义 Storefront 模板可访问能力 | 否，通过字段方法委托读取 | 否，只属于 Storefront 安全边界 |

关键关系应当是：

```text
                          ┌──────────────────────┐
                          │  Domain Read Model   │
                          │ 稳定语义、非 HTTP 形状 │
                          └──────────┬───────────┘
                                     │
                    ┌────────────────┴────────────────┐
                    │                                 │
          ┌─────────▼─────────┐             ┌─────────▼─────────┐
          │ GraphQL Presenter │             │ Liquid Projection │
          │ Admin 权限与契约投影 │             │ Storefront 安全投影 │
          └─────────┬─────────┘             └─────────┬─────────┘
                    │                                 │
          ┌─────────▼─────────┐             ┌─────────▼─────────┐
          │  GraphQL Object   │             │    Liquid Drop    │
          └───────────────────┘             └───────────────────┘
```

因此，不建议设计 `ProductObject extends ProductDrop`、让 GraphQL resolver 调用 Drop，或让 Drop 直接返回 GraphQL Object。这会把 Admin 权限和 Storefront 暴露边界混在一起，并让两种运行时互相依赖。

### 3.1 什么是“统一取数模式”

统一取数模式不是让所有领域共用一个万能 Repository，也不是要求 GraphQL、REST 和 Liquid 返回相同的对象。它是要求不同调用入口遵循同一套读取协议：

```text
GraphQL 解析器（Resolver）────┐
REST 控制器（Controller）─────┼──▶ 查询条件 + 读取上下文 ──▶ 领域读取器
Liquid 数据对象（Drop）───────┘    Criteria   ReadContext     Domain Reader
                                                               │
                                                               ▼
                                            读取模型 + 连接分页切片
                                      Read Model   Connection Slice
                                                               │
                                    ┌──────────────────────────┴──────────────────────────┐
                                    ▼                                                     ▼
                    GraphQL 展示适配器（Presenter）                       Liquid 数据投影（Projection）
```

这张图从上到下表示一次读取请求的完整数据流：

- **GraphQL Resolver、REST Controller、Liquid Drop 是调用入口**：它们服务于不同消费者，负责接收各自的参数和组织最终响应，但不应该分别实现商品、订单等领域的查询逻辑。
- **Criteria 是统一的查询条件**：把 GraphQL arguments、REST query parameters 或 Liquid 当前页面条件转换为明确的筛选、排序、分页和 cursor，不让下层依赖某一种 HTTP 参数格式。
- **ReadContext 是统一的读取上下文**：明确携带当前店铺/租户、访问身份、权限、API 版本、语言、时区和 request ID，避免 Reader 隐式读取全局 Request 或可变配置。
- **Domain Reader 是领域读取入口**：例如 `ProductReader`、`OrderReader`。它封装从 Repository、数据库、缓存或既有 Service 取得业务数据的方式，并保证不同调用入口获得一致的领域语义。
- **Read Model 是稳定的单体读取结果**：例如 `ProductReadModel` 明确商品 ID、标题、状态和价格的内部类型，不直接把 Eloquent Model、Laravel Resource 或不定形数组暴露给上层。
- **Connection Slice 是稳定的列表读取结果**：它包含 nodes、是否还有下一页及 cursor 等分页信息，使 GraphQL Connection 不需要反向解析 REST paginator。
- **GraphQL Presenter 与 Liquid Projection 是不同的输出适配层**：前者按 Admin GraphQL schema 输出字段，后者按 Storefront 安全边界输出模板字段。它们可以使用同一个 Read Model，但不能因此暴露相同字段。

例如，请求 `product(id:)` 时，GraphQL Resolver 先把 Global ID 和当前管理员上下文转换为 Criteria/ReadContext，随后调用 `ProductReader`。Reader 从正确店铺的数据源取得并构造 `ProductReadModel`，最后由 GraphQL Presenter 编码 ID、序列化类型并按 schema 返回。Liquid 若读取同一商品，则可以调用相同 Reader，但由 Liquid Projection 只输出 Storefront 允许的标题、公开 URL 和可购买状态。

这套协议至少统一以下六件事：

1. **统一输入**：filter、sort、page size、cursor 等参数先转换为领域 Criteria，Reader 不直接读取 `request()`、路由或容器中的全局 Request。
2. **统一上下文**：通过 request-scoped `ReadContext` 明确传入店铺/租户、当前身份、API 版本、locale、timezone 和 request ID。
3. **统一领域入口**：按领域提供 `ProductReader`、`OrderReader`、`CustomerReader` 等窄接口，不建立可以查询任意资源的万能 Service。
4. **统一内部返回**：Reader 返回明确的 Read Model 或 Connection Slice，不在 Model、Laravel Resource、array 和 JSON string 之间随机切换。
5. **统一关联加载**：关联字段通过 request-scoped batch loader 按 ID 集合加载并在请求内去重，避免每个 node 单独查询。
6. **统一基础语义**：ID、Money、DateTime、Enum、空值、分页和错误分类使用一致规则。

统一发生在展示层下面。GraphQL Object 与 Liquid Drop 可以共享 Reader、Read Model 和业务计算，但仍分别维护字段 allowlist、权限、序列化、缓存上下文和错误表现。

### 3.2 建议先写一个自动折扣只读 Demo

建议写 Demo，但它应是验证底层模式的纵向样板，而不是再写一组把 `DiscountRule` 和 JSON 数组直接返回的 resolver(解析器)。自动折扣适合作为样板，是因为候选 Admin GraphQL 已有对应 facade(外观层)，而现有 `filters`、`conditions`、`adjustments` 同时包含 Model accessor、JSON 结构和目标资源 ID，能够较早暴露统一取数模式是否真正成立。

首期只覆盖自动折扣单体、列表以及一个类型化的目标资源字段：

```graphql
enum DiscountTargetType {
  PRODUCT
  CATEGORY
  ALL_PRODUCTS
}

type DiscountTarget {
  resourceId: ID
  title: String!
  type: DiscountTargetType!
}

type AutomaticDiscount {
  id: ID!
  title: String!
  enabled: Boolean!
  startsAt: DateTime
  endsAt: DateTime
  targets: [DiscountTarget!]!
}

type Query {
  automaticDiscount(id: ID!): AutomaticDiscount
  automaticDiscounts(first: Int!, after: String): AutomaticDiscountConnection!
}
```

Demo 的最小组成包括：

- `ReadContext`；
- `AutomaticDiscountQueryCriteria`；
- `AutomaticDiscountReader` 与一个包装既有 `DiscountRuleService`/Repository 的实现；
- `AutomaticDiscountReadModel`、`AutomaticDiscountConnectionSlice`；
- 把 `filters`、`conditions`、`adjustments` 从不定形 JSON 收敛为明确内部结构的转换器；
- 根据折扣目标 ID 批量加载商品或分类摘要的 `DiscountTargetsLoader`；
- AutomaticDiscount root resolver、targets field resolver 和简单 Presenter；
- SDL/runtime contract test、Reader 单元测试、权限/租户测试及关联查询次数测试。

Demo 暂不包含折扣 Mutation、不同时迁移优惠码、阶梯折扣和买赠折扣、不改造全部 Liquid Drop，也不要求一次性移除既有 `DiscountRuleService`。它必须证明：

- Reader 测试不需要构造路由或替换容器全局 Request；
- 单体 AutomaticDiscount 与列表 node 的同名字段语义一致；
- GraphQL resolver 不直接消费 Laravel HTTP Resource；
- `filters`、`conditions`、`adjustments` 不以未审计 JSON 直接穿透 GraphQL，而是由 Reader 转换为稳定内部语义；
- 查询 1 个和 50 个自动折扣的 targets 时，同类目标资源查询次数保持常数级；
- GraphQL 与另一个内部消费者可以复用 `AutomaticDiscountReader`，但各自的输出字段和权限仍彼此独立；
- 跨店铺或无权限数据不能通过 root field、关联字段或 JSON 结构泄露。

这个 Demo 的价值不在于证明 GraphQL 能返回折扣，而在于验证 HTTP 解耦、历史 JSON 规则结构收敛、批量目标加载、权限/租户隔离和跨调用入口复用是否同时成立。验证通过后，再决定是否将该模式推广到其他折扣类型及商品、订单等领域。

## 4. 目标数据获取架构

建议把读取链路拆成六层：

```text
GraphQL Request
      │
      ▼
1. Contract / Schema
   字段、参数、空值、版本
      │
      ▼
2. Root Resolver / Field Resolver
   编排，不写查询和业务判断
      │
      ▼
3. Query Criteria + Access Policy
   规范化 filter/sort/page/tenant/admin scope
      │
      ▼
4. Domain Reader
   ProductReader / OrderReader / CustomerReader
      │
      ├──────────────┐
      ▼              ▼
5a. Repository   5b. Batch Loader
   基础集合/单体      categoriesByProductIds 等关联
      │              │
      └──────┬───────┘
             ▼
6. Read Model + Presenter
   稳定内部语义 → GraphQL 契约
```

### 4.1 Contract / Schema：必须只有一个事实源

版本化 SDL 应成为公开合同的唯一事实源，至少覆盖：

- Object、Interface、Enum、Input、Connection；
- 字段空值规则和废弃策略；
- Query/Mutation 参数；
- 版本内稳定性。

运行时可以继续使用 webonyx，也可以复用项目已有 Lighthouse 能力，但不应再手工维护一份等价 PHP ObjectType 树。若短期不能移除 PHP schema builder，至少需要自动化 contract test 对比 SDL 与运行时 introspection，阻止字段、类型和 nullability 漂移。

### 4.2 Query Criteria：不要把 GraphQL 参数伪装成 REST Request

建议为每个领域建立显式读取条件，例如概念上的：

```php
ProductQueryCriteria {
    filters
    sorts
    pageSize
    cursor
    visibilityScope
}
```

它负责：

- filter/sort allowlist；
- Global ID 解码；
- 默认排序和稳定的 tie-breaker；
- cursor 与排序、过滤条件绑定；
- page size 上限；
- 租户、可见性和权限 scope。

GraphQL adapter 和 REST adapter 都可以构造 Criteria，但 Reader 不再读取 `request()` 或 `app('request')`。这一步是把底层从 HTTP 中解耦的关键。

### 4.3 Domain Reader：统一的是业务读取语义，不是响应 JSON

建议逐领域建立窄接口，而不是建立一个万能 Repository：

```php
interface ProductReader
{
    public function find(ProductId $id, ReadContext $context): ?ProductReadModel;

    public function search(
        ProductQueryCriteria $criteria,
        ReadContext $context
    ): ProductConnectionSlice;

    public function categoriesByProductIds(
        array $productIds,
        ReadContext $context
    ): array;
}
```

Reader 的要求：

- 返回明确的 Read Model/Connection Slice，不返回 Laravel HTTP Resource；
- 不依赖 Controller、route name 或全局 Request；
- 内部可以复用现有 Repository/Service，也可以暂时包住遗留链路；
- 明确是否包含软删除、隐藏记录、店铺/租户 scope；
- 查询单体和列表使用同一字段语义；
- 不因调用端是 REST、GraphQL 或 Liquid 改变核心业务含义。

### 4.4 Read Model：让不一致在底层收敛一次

Read Model 不需要一开始就做完整 DDD，也不应该复制整张表。它只需要解决当前最贵的问题：同一字段可能来自 Model accessor、关系、缓存数组、Resource 或 JSON string。

例如 ProductReadModel 应明确：

- `id` 是内部标识，Global ID 在 Presenter 层编码；
- Money 使用明确的 amount/currency 语义，不靠 `float` 猜测；
- image、variant、category 是明确对象或引用，不是任意 JSON；
- 时间统一为明确时区和格式；
- `null`、空列表和“不允许读取”是不同状态；
- `buyAble` 等派生字段由领域读取服务产生，不在 Mapper 中临时推断。

这使 GraphQL Presenter 和 Liquid Projection 只负责“挑哪些字段、改什么名字、如何安全输出”，而不是继续修复来源数据。

### 4.5 Field Resolver + DataLoader：GraphQL 的价值应体现在关联按需加载

GraphQL 不应简单地先构造一个完整 REST 详情对象，再删掉未请求字段。建议：

- root query 只加载对象基础字段；
- `Product.categories` 使用 request-scoped `categoriesByProductIds` loader；
- `Product.variants` 使用 request-scoped `variantsByProductIds` loader；
- `Order.customer`、`Order.lineItems` 使用各自批量 loader；
- 同一请求内按 `(tenant, resource, id, scope)` 去重和缓存；
- loader 必须批量查询，禁止每个 node 单独访问 Repository；
- loader 只做加载与组装，不绕过权限策略。

```text
products(first: 20)                 1 次基础查询
  ├─ categories                     1 次 WHERE product_id IN (...)
  ├─ variants                       1 次 WHERE product_id IN (...)
  └─ variants.featureImage          1 次批量文件查询

目标：查询次数随字段组变化，而不是随 node 数量线性增长。
```

不建议让 Repository 直接解析 GraphQL selection AST 并动态拼 SQL。那会把公开 schema 与持久化结构强耦合。selection awareness 应主要由字段 resolver 是否触发 loader 来实现；基础标量可按稳定字段组选择查询，而不是逐字段微优化。

### 4.6 Presenter：只做契约投影

GraphQL Presenter/Mapper 应只承担：

- Read Model → schema 字段命名；
- Global ID 编码；
- Enum、DateTime、Money 等 API 类型序列化；
- 明确的字段 allowlist；
- Admin 权限导致的字段裁剪或受控错误。

它不应承担：

- 查询数据库；
- 猜测输入是 Model 还是 array；
- 自动把所有 snake_case 转为 camelCase；
- 随机解析 JSON string；
- 补齐领域默认值；
- 触发写操作或业务副作用。

当 Mapper 中持续出现 `is_array`、`toArray`、`json_decode`、多套字段 fallback 时，应视为 Reader/Read Model 缺失的信号，而不是 Mapper 的正常职责。

## 5. Object 与 Drop 到底共享什么

建议按下面的边界复用：

| 能力 | GraphQL Object | Liquid Drop | 是否共享底层 |
| --- | --- | --- | --- |
| 商品标题、slug 等稳定业务值 | Admin 字段 | Storefront 字段 | 共享 Reader/Read Model |
| Admin 编辑 URL、内部状态 | 可以按权限暴露 | 不应暴露 | 不共享投影 |
| Storefront public URL、可购买状态 | 可按合同暴露 | 常用 | 可共享领域计算 |
| PII、支付后台配置 | 受 Admin 权限控制 | 默认禁止 | 只共享安全服务，不共享返回对象 |
| 字段懒加载 | Field Resolver/DataLoader | Drop 方法调用时加载 | 共享 Reader，可分别懒加载 |
| 请求级缓存 | GraphQL execution context | Liquid render context | 共享机制思想，不共享实例 |
| 错误表现 | GraphQL errors/userErrors | 空值/降级/渲染日志 | 不共享表现层 |

这里尤其要防止把 Liquid Drop 当成“现成领域对象”。当前 Drop 本质上是模板安全网关，其生命周期、错误降级和公开字段要求与 Admin GraphQL 不同。

## 6. 列表、分页与过滤的底层约束

候选实现把基于 page number 的 REST 分页包装成 cursor，这是可接受的过渡方案，但不能误认为已获得稳定 cursor pagination。

长期建议：

1. cursor 至少绑定资源类型、排序字段、排序方向、最后一个稳定键、filter signature 和版本；
2. 排序必须有唯一 tie-breaker，例如 `created_at DESC, id DESC`；
3. 高频大表优先 keyset pagination，不用 page number 伪装 cursor；
4. 暂时只能 page pagination 的领域，应在内部 Connection Slice 中明确标记能力限制；
5. filter/sort 必须是 typed input/enum，`JSON` 只作为短期兼容逃生口；
6. 总数 `totalCount` 若代价高，应显式按需计算，不能默认附带全表 count。

## 7. 权限、租户与敏感数据

Admin GraphQL 的权限不能只停留在 root operation。

需要至少三层控制：

- **operation policy**：能否查询 products、能否修改 orders；
- **object scope**：当前店铺/租户能看到哪些记录；
- **field policy**：email、phone、地址、支付或风控字段是否允许当前身份读取。

`ReadContext` 应是 request-scoped immutable context，包含 admin identity、tenant/store、API version、request ID、locale/timezone 和 capability 信息。Repository/Reader 必须显式接收它或接收由它生成的 scope，不能仅依赖进程全局 config。

PII、支付配置和内部 JSON 不应因为底层 Model/Resource 已存在就自然进入 GraphQL。schema 中每个字段都应有来源和暴露理由；`JSON` scalar 不能成为绕过字段级安全审查的通道。

## 8. Mutation 的边界

读取底座不等于写入底座。Mutation 应继续优先复用现有领域 Service/Command，以保留事务、事件、缓存失效和权限副作用，但需要逐步去掉对 Controller 与伪造 HTTP Request 的依赖。

推荐方向：

```text
GraphQL Input
    → typed Command DTO
    → Validator / Policy
    → existing Domain Service
    → transaction + events
    → Reader 重新读取结果
    → GraphQL Payload
```

短期复用 FormRequest adapter 是合理迁移手段；长期规则应下沉到可被 REST 与 GraphQL 同时调用的 Validator/Command，而不是永久依赖 route name、URI segment 或 action method 来选择规则。

## 9. 分阶段建设路线

### Phase 0：冻结扩张并建立盘点表

- 暂停继续给大 Mapper 无边界加字段；
- 盘点每个 GraphQL 字段的数据源、权限、格式、成本和 REST/Drop 对应关系；
- 明确版本化 SDL 的事实源；
- 给已有字段标记：稳定、兼容、待类型化、待移除。

输出应是一张 field provenance 表，而不只是 schema 列表。

### Phase 1：建立最小读取内核

- `ReadContext`；
- Criteria、Sort、Cursor、Connection Slice 通用约束；
- Reader/Read Model 基础接口；
- request-scoped Loader Registry；
- 统一 ID、DateTime、Money、Enum、错误分类；
- SDL 与运行时 schema 一致性测试。

### Phase 2：自动折扣只读纵向样板

- `automaticDiscounts`、`automaticDiscount`；
- 基础标量与类型化 `targets` 关联；
- 收敛 `filters`、`conditions`、`adjustments` 的内部读取语义，禁止未审计 JSON 直接穿透；
- 对比现有 REST/候选 GraphQL 的折扣字段语义；
- 建立 query count、慢查询和 loader batch 指标；
- 其他内部消费者若需要相同折扣语义，只复用 AutomaticDiscountReader，不复用 GraphQL Presenter。

### Phase 3：迁移复杂领域

推荐顺序：其他折扣类型 → Product/Category/Tag → Customer → Order/Checkout。

原因是其他折扣类型可以复用自动折扣样板中的规则结构和目标加载能力；Product/Category/Tag 可继续验证多级关联；Order/Checkout 包含更多 PII、历史字段、支付状态和复合关系，不适合作为第一个底座试验对象。折扣 Mutation 的写入语义复杂，也应在读取内核稳定后另行处理。

### Phase 4：收敛遗留 adapter

- Reader 逐步替代对 Resource 和全局 Request 的依赖；
- Mapper 退化为简单 Presenter；
- typed inputs/filters 逐步替代 JSON；
- 删除重复 schema builder 或将其改为 SDL 编译结果；
- REST Controller 与 GraphQL resolver 共同调用领域 Query/Command，不互相调用。

## 10. 建设成本估算

以下是假设由熟悉 WShop 折扣、商品、订单和权限模型的工程师推进，不含前端迁移、完整 QA 自动化与所有领域字段补齐的粗估：

| 工作 | 粗估 | 主要不确定性 |
| --- | ---: | --- |
| 现有字段/来源/权限盘点 | 3～5 人日 | 历史字段与 Resource 隐式 accessor |
| schema 单事实源与合同校验 | 4～8 人日 | 保留自研 runtime 还是复用 Lighthouse |
| ReadContext、Criteria、Cursor、Connection 基础 | 5～10 人日 | 租户 scope 与旧分页依赖 |
| Loader Registry 与批量加载规范 | 4～8 人日 | PHP 7.4/现有依赖下的实现方式 |
| 自动折扣纵向样板 | 8～15 人日 | 历史 JSON 规则结构、目标资源和折扣类型判定 |
| 每个简单领域迁移 | 3～7 人日 | Category、Tag 一类 |
| 每个复杂领域迁移 | 8～15 人日 | Order、Checkout、Customer 一类 |

因此，一个“可证明有效、可以继续复制”的底座加自动折扣样板，大约是 **4～7 工程周**；把候选分支已有主要领域全部收敛到该底座，通常还需要 **6～12 工程周**。如果继续采用当前 facade + 大 Mapper 路线，短期单个接口会更快，但成本会以字段数量、返回形状和领域数量的乘积累积。

这不是排期承诺。正式估算前至少要完成 Phase 0，并确认以下选择：

- Admin GraphQL runtime 是否继续自研 webonyx executor；
- SDL 是否能成为运行时事实源；
- 自动折扣及后续领域的目标字段范围；
- 是否要求与 Shopify Admin GraphQL 兼容到命名层，还是只借鉴其 contract；
- 首期是否同时改造 Liquid，还是只为未来复用预留 Reader 边界。

## 11. 必须提前定下来的架构决策

### 决策 A：兼容 facade 还是长期平台

- 若目标只是给少量内部 Admin 页面提供过渡 API，当前 Provider/Adapter/Mapper 模式可以继续，但要限制字段和领域数量。
- 若目标是未来 Admin 的主要 API，必须先投入数据读取底座，不应继续把 REST response shape 搬进 GraphQL。

建议按“长期平台”设计，但用自动折扣只读样板控制首期范围。

### 决策 B：Object/Drop 是否同构

不建议同构。两者只共享 Reader/Read Model 与领域计算。字段合同、权限、错误和缓存上下文分别维护。

### 决策 C：Schema-first 还是 code-first

项目已经有版本化 SDL，也需要清晰的 API review 与版本治理，因此建议 schema-first。PHP 类型对象可以是编译产物或 runtime representation，但不应成为第二份人工事实源。

### 决策 D：是否追求按字段 SQL 投影

首期不追求逐标量字段裁剪。优先解决关联 N+1、全局 Request、混合返回形状和权限边界。基础字段按稳定字段组读取即可，避免过早把 schema AST 耦合到 SQL。

## 12. 验收底座是否真的成立

只有满足以下条件，才能认为“数据获取方式与底层”已经建设出来，而不只是增加了一层 GraphQL：

- 新增一个 AutomaticDiscount 字段时，不需要同时修改 Controller、Resource 和多个 Mapper fallback；
- 同一 Reader 可被 GraphQL 与另一个非 HTTP 消费者调用；
- Reader 测试不需要构造路由或替换容器全局 Request；
- 查询 1 个与 50 个 AutomaticDiscount 的 targets，关联查询次数保持常数级；
- SDL introspection 与仓库版本化 schema 自动一致；
- filter/sort/cursor 改变后，旧 cursor 会被稳定拒绝；
- 无权限字段不能通过 JSON、Model relation 或 Mapper fallback 泄露；
- 单体查询与列表节点的同名字段语义一致；
- Liquid Drop 和 GraphQL Object 可返回不同字段，但共享字段的业务值来自同一领域语义；
- 运行指标能回答某个 GraphQL 字段由哪个 loader 获取、耗时多少、批量大小是多少。

## 13. 风险与回归关注点

- **语义回归**：从 REST Resource 迁移到 Read Model 时，accessor、append、默认值可能遗漏。
- **性能回归**：错误的字段 resolver 会产生 N+1；过度 eager load 又会让轻量 query 失去意义。
- **权限回归**：root 权限正确不代表关联对象和 PII 字段正确。
- **缓存回归**：Storefront Drop 可接受的缓存策略不一定适合 Admin 实时数据。
- **分页回归**：page cursor 转 keyset cursor 时，排序稳定性和翻页边界需要双轨验证。
- **合同回归**：PHP runtime schema 与 SDL 双写会产生不可见漂移。
- **Mutation 回归**：绕开既有 Service 会丢事件、锁、事务与缓存失效；伪造 Request 长期保留又会固化 HTTP 耦合。

验证应以 Reader 单元测试、schema contract test、DataLoader query-count test、权限矩阵测试和现有 REST/GraphQL 对照测试为主。本文未执行数据库或业务测试，因为本次只产出架构探索文档，没有修改运行时代码。
