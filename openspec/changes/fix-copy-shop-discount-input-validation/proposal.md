## Why

飞书缺陷 [7153528460](https://project.feishu.cn/wshop/issues/detail/7153528460) 的截图显示，复制店铺生成密钥接口 `POST /api/admin/copy_shop/key` 接收 `{"discount":"bad"}` 时返回 HTTP 500。非法客户端输入应得到可定位字段的参数校验错误，不能触发服务器内部异常。

## What Changes

- 在折扣类型遍历和权限映射查询前校验 `discount` 的容器及元素类型，拒绝字符串和嵌套数组等非法输入。
- 保留四类合法折扣的权限校验与密钥生成流程，不将字符串包装成合法数组，也不吞掉异常后继续签发密钥。
- 增加覆盖完整 FormRequest 解析流程、错误响应及“无密钥生成副作用”的回归测试，并维护目标项目既有接口契约。
- 交付修复引导、验收场景和实施任务；本次只生成文档，不修改业务代码。

## Capabilities

### New Capabilities

- `copy-shop-discount-input-validation`: 明确生成复制密钥时折扣入参结构、非法值处理、权限回归及副作用约束。本地尚无该能力规格，因此新增规格，不代表新增业务接口。

### Modified Capabilities

无。实施仓库若已有对应主规格，迁入时应改为针对原能力的增量规格，避免重复定义。

## Impact

- 来源：SaaS-平台 / 缺陷管理，标题“【应用中心-复制店铺】复制店铺生成密钥时，discount字段的值为字符串时，返回500”，等级 P3；正文截图是唯一具体复现材料，未提供调用者权限或服务端堆栈，评论为空。
- 文档位置：当前 `ShopLaravel/openspec/changes/fix-copy-shop-discount-input-validation`。当前仓库无 `SyncShop` 模块，不能直接在这里实施修复。
- 参考源码：本机只读核查 `/Users/jiweijian/Public/Docker/laradock/www/wshop/wshop/platform/Modules/SyncShop`；另有 `WshopAi` 检出，实施前必须确认实际目标仓库、分支及环境。
- 重点文件（相对目标 WShop 仓库）：`platform/Modules/SyncShop/Http/Requests/GetKeyRequest.php`、`Routes/api.php`、`Http/Controllers/Admin/API/CopyShopController.php`、`Services/KeyService.php`、`Tests/Feature/GetKeyRequestTest.php`。
- 不涉及当前商城后台导航、商品管理、数据库迁移、依赖升级或远端飞书状态修改。

## 实施位置更新

用户后续要求实施，代码及正式变更已迁入 `/Users/jiweijian/Public/Docker/laradock/www/wshop/wshop` 的 `feature/7153528460` 分支。当前目录保留原始引导；进度见同步后的 tasks.md。后续 apply/verify/archive 应在 WShop 仓库执行，不在 ShopLaravel 修改业务代码。
