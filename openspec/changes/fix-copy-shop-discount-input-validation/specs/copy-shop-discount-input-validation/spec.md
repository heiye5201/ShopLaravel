## ADDED Requirements

### Requirement: Reject malformed discount input before authorization traversal

生成复制店铺密钥接口 `POST /api/admin/copy_shop/key` MUST 在遍历折扣类型或查询权限映射前校验显式传入的 `discount` 结构。非数组顶层值和非字符串元素 SHALL 产生标准 HTTP 422 字段校验错误，不得产生 HTTP 500 或将输入静默转换为合法配置。

#### Scenario: String reproduces reported failure
- **WHEN** 已通过接口登录和模块访问校验的调用方提交 `{"discount":"bad"}`
- **THEN** 接口返回 HTTP 422，包含 `discount` 字段错误，不抛出未处理的遍历异常

#### Scenario: Other invalid container types
- **WHEN** 调用方分别提交显式 null、数字或布尔值作为 `discount`
- **THEN** 接口以 `discount` 参数错误拒绝请求，不生成密钥，不返回 HTTP 500

#### Scenario: Nested arrays and non-string elements
- **WHEN** `discount` 包含嵌套数组、null、布尔值或数字，例如 `{"discount":[["code-coupons"]]}`
- **THEN** 接口返回 HTTP 422，并定位 `discount.<index>` 错误，不以非法元素作为权限映射键

### Requirement: Preserve discount whitelist and permission checks

接口 SHALL 继续仅支持 `code-coupons`、`auto-coupons`、`bulk-coupons`、`buy-coupons`，并 MUST 对所选合法折扣保留现有模块权限校验；输入类型保护不得绕过权限要求。

#### Scenario: Unknown discount string
- **WHEN** 调用方传入 `{"discount":["unknown-coupons"]}`
- **THEN** 接口返回 HTTP 422，错误定位 `discount.0`，不签发密钥

#### Scenario: Legal input with all required permissions
- **WHEN** 调用方传入一种或多种合法折扣，并具备其现有全部读写权限及其他必要前置条件
- **THEN** 接口沿用既有成功响应生成密钥，复制配置与所选折扣保持一致

#### Scenario: Missing discount permissions
- **WHEN** 调用方传入合法折扣但缺少对应已有权限
- **THEN** 接口按现有权限拒绝语义返回错误，不调用密钥生成服务，且不返回 HTTP 500

#### Scenario: Other copy modules and optional discount
- **WHEN** 合法请求省略 `discount` 或携带空数组，并组合其他已支持复制模块
- **THEN** 接口保持该目标项目现有基线行为，不隐式启用折扣，不改变其他模块的权限校验或复制配置

### Requirement: Invalid requests have no key-generation side effects

非法 `discount` 请求 MUST 在调用 `KeyService::setConfigs()` 之前结束，SHALL 不新增复制密钥、访问令牌、源店铺复制记录、关联缓存或生成密钥事件。校验异常 SHALL 沿用目标项目字段错误结构和本地化机制。

#### Scenario: Repeated malformed requests
- **WHEN** 测试环境重复提交 `{"discount":"bad"}`，并对密钥服务及相关存储和事件建立隔离观测
- **THEN** 所有请求均以字段校验错误结束，密钥生成服务未被调用，相关副作用计数不增加

#### Scenario: Lifecycle regression coverage
- **WHEN** 回归测试经 FormRequest 完整解析流程或真实路由执行上述非法请求
- **THEN** 授权阶段不会先发生类型异常，字段错误可被项目异常处理器序列化为预期 HTTP 422 响应
