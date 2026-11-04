## Context

需求依据与位置见 `proposal.md`。本次仅生成修复引导，未在截图所示测试环境发起请求，也未在 WShop 运行复现测试。当前 ShopLaravel 的 PHP 8.5/Laravel 13 依赖不能作为 WShop 的版本依据。

只读参考目标检出的调用链为：

1. `platform/Modules/SyncShop/Routes/api.php`：`POST /api/admin/copy_shop/key`，经过登录与复制店铺模块权限中间件。
2. 目标检出 `vendor/laravel/framework/src/Illuminate/Validation/ValidatesWhenResolvedTrait.php` 的 `validateResolved()` 先调用 `prepareForValidation()`，再调用 `passesAuthorization()`，最后才创建并执行字段 validator。
3. `GetKeyRequest::authorize()` 直接将 `$this->input('discount', [])` 用作 `foreach` 输入，并以元素为键查询权限映射。
4. `rules()` 已有顶层 `discount: array` 与四类折扣白名单，但执行时机晚于上述遍历；现有 `Tests/Feature/GetKeyRequestTest.php` 主要覆盖手动构造 validator 和合法数组的权限校验，未覆盖这条字符串解析链路。
5. 通过请求校验后，`CopyShopController::getKey()` 才调用 `KeyService::setConfigs()`，后者涉及令牌创建、`SourceRecord` 写入、缓存和事件。

**推断而非线上堆栈结论**：字符串在授权阶段触发非数组遍历异常，是截图 500 的高可信原因。数组内嵌套数组还可能在权限映射查询时触发非法键类型异常，需一并通过回归测试核实。

## Goals / Non-Goals

**Goals:**

- 将非法折扣输入转为 HTTP 422 参数校验响应，错误定位至 `discount` 或 `discount.<index>`；沿用目标 `ApiExceptionHandler` 的响应结构和本地化文案。
- 在任何不安全遍历、映射取值及密钥签发前拒绝不合法数据。
- 保留合法折扣的权限要求、正常返回以及既有省略字段/空数组语义。

**Non-Goals:**

- 不新增接口、折扣类型、复制模块或数据库字段。
- 不修改 KeyService 的令牌、缓存与密钥有效期规则，不重构全部模块授权流程。
- 不在 ShopLaravel 实现缺失的 SyncShop，也不自动修改外部仓库或飞书工作项。

## Decisions

### 在进入 discount 授权分支前执行窄范围结构校验

对传入的 `discount` 校验顶层数组及单个元素的字符串类型；只有结构安全的数据进入权限映射。复用目标框架 validator/ValidationException 和既有翻译，保持字段错误键稳定。合法类型白名单集中定义或复用，避免请求规则和权限映射出现两份不同的名单。

实施时优先在 `authorize()` 的折扣分支入口做窄范围校验，或在 `prepareForValidation()` 做同等保护；后者会提前所有模块的输入校验优先级，必须用混合模块回归验证。未知字符串仍按白名单规则拒绝。对嵌套数组使用 `bail` 或等价短路，避免结构失败后仍执行不支持该类型的规则。

不将所有 `rules()` 提前执行，因为其他模块含插件和数据库相关闭包，可能改变原有授权顺序。不采用强制转换数组或静默跳过非法输入，这会掩盖调用方错误或签发意外配置的密钥。仅给晚执行的 `rules()` 添加 `array` 无法解决已存在的执行顺序问题。

### 保留权限和响应兼容性

合法字符串仅允许 `code-coupons`、`auto-coupons`、`bulk-coupons`、`buy-coupons`，保留各自已有读写权限要求。结构合法但权限不足时仍走现有拒绝路径；不假设该项目的业务权限错误必然使用 HTTP 403。

参数错误使用 `ValidationException`。参考检出 `app/Exceptions/ApiExceptionHandler.php` 已处理此异常及字段错误；HTTP 状态和响应外壳需通过实际路由测试固化为 422，而不是复制 ShopLaravel 的异常处理格式。

### 测试完整请求解析流程

先新增失败测试，经 `validateResolved()` 或路由解析经过真实 FormRequest 生命周期，避免仅直接调用 `Validator::make($request->rules())` 造成假阳性。使用现有 `Tests\ModuleTestCase`、模块加载方式和 `PermissionProbeUser`；接口级验证使用测试环境既有鉴权 fixture。非法输入时断言控制器服务未调用，并按测试隔离能力检查无令牌、源记录、缓存和事件新增，不能只检查状态码。

## Risks / Trade-offs

- **目标检出与部署版本不同** → 实施前记录目标仓库、分支和提交，使用该仓库框架与异常处理源码重新确认，当前代码定位仅作线索。
- **预校验改变混合模块报错优先级** → 限定校验范围并覆盖混合 payload；保留其他模块授权与插件校验。
- **非法嵌套值导致规则本身异常** → 容器和元素类型先短路，再进行枚举和权限映射。
- **mock 仅覆盖 rules 而遗漏 authorize** → 保留生命周期/路由级回归场景。
- **误将缺省、null、空数组统一归一化** → 缺省与空数组先建立现有行为基线；显式 null 按原 array 校验拒绝，不创造默认复制范围。

## Migration Plan

1. 确认并切换到实际 WShop 实施仓库，读取其 AGENTS 和 OpenSpec 规则；将本变更迁入或关联至其规划上下文，再执行实施命令。
2. 对已有测试建立基线；用字符串 payload 新增可失败的回归测试，再做最小请求层修改。
3. 运行目标项目 GetKeyRequest 与复制店铺相关测试，更新既有接口契约中的数组参数和 422 示例。
4. 在获授权的测试环境，以测试账号分别验证非法输入、合法有权限输入和合法无权限输入；不要在生产生成复制密钥来验证。
5. 无数据迁移与依赖变更。回滚仅撤销请求层及配套契约修改，恢复旧行为会重新暴露该 500 风险。

## Open Questions

- 实际实施仓库是 `wshop/wshop`、`wshop/WshopAi` 还是其他检出？当前文档保存在 ShopLaravel，不授权跨仓库实施。
- 截图请求账号有哪些权限、部署提交是什么？缺陷描述未提供。
- 省略 `discount`、空数组及关联数组键的历史契约需补充基线；本次不引入新的去重、顺序或数组键约束。
- 线上具体异常栈尚未获取；先以本地失败回归验证调用顺序推断。
