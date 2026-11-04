## 1. 确认实施上下文与建立基线

- [x] 1.1 确认本地 WShop 仓库、分支和基线提交，迁入其 OpenSpec 上下文并读取目标规则；明确部署版本未核验，测试环境仍待确认（环境验收保留在 3.4）。
- [x] 1.2 核查目标 `GetKeyRequest`、FormRequest 生命周期、`ApiExceptionHandler` 和 `copy_shop/key` 路由鉴权，确认输入类型异常发生位置及 HTTP 422 响应格式。
- [ ] 1.3 运行现有 `GetKeyRequestTest` 建立基线，记录合法折扣、有无权限、缺省 discount 与空数组的既有行为；使用目标项目现有测试命令和 PHP 版本。

## 2. 新增失败回归并修复入参边界

- [ ] 2.1 在 `platform/Modules/SyncShop/Tests/Feature/GetKeyRequestTest.php` 增加完整 FormRequest 生命周期回归，验证 `{"discount":"bad"}` 在旧代码可复现授权阶段类型异常。
- [x] 2.2 增加 null、数字、布尔值、嵌套数组、混合合法与非法元素的数据驱动用例，断言字段错误为 `discount` 或 `discount.<index>`。
- [x] 2.3 在不安全遍历和权限映射前加入容器与元素类型保护，使用标准 ValidationException/目标 validator、本地化错误及短路规则，不强制转换或静默忽略错误输入。
- [ ] 2.4 保留四种折扣白名单及原读写权限映射；用合法单类型、多类型、未知类型及缺少权限场景验证没有权限绕过。
- [ ] 2.5 验证混合复制模块、缺省字段和空数组没有行为回归，不修改 KeyService 的密钥、令牌、源记录或缓存实现。

## 3. 接口契约与副作用验收

- [x] 3.1 按目标项目既有接口文档维护 `POST /api/admin/copy_shop/key`：discount 为字符串枚举数组，补充 `{"discount":"bad"}` 的 HTTP 422 字段错误示例，使用项目真实响应外壳。
- [ ] 3.2 通过路由或等价真实解析流程验证非法 payload 返回 422 而非 500，并断言 `KeyService::setConfigs()` 从未被调用。
- [ ] 3.3 使用隔离数据库、缓存、事件及令牌测试能力，验证重复非法请求不会新增密钥、访问令牌、SourceRecord、关联缓存或生成密钥事件。
- [ ] 3.4 在获授权测试环境用测试账号执行非法输入、合法有权限和合法无权限三类验收，记录环境、提交和响应；不使用真实生产密钥作为测试 fixture。

## 4. 完成检查与交付

- [ ] 4.1 运行目标项目请求层、复制店铺相关测试及要求的格式检查；仅在新增变更或失败时扩大测试范围。
- [x] 4.2 执行迁入后的 OpenSpec 校验，逐项核对规格场景与测试证据，记录尚未取得的线上堆栈或环境信息。
- [x] 4.3 交付代码差异、验证结果和无需迁移的回滚说明，待实际完成后再勾选任务；本次生成文档不代表缺陷已修复。

## 本次实施记录

- 目标仓库：`/Users/jiweijian/Public/Docker/laradock/www/wshop/wshop`，分支 `feature/7153528460`，本地基线 `c44dd2976e`；未核验线上部署提交。
- 已修改请求层、增加完整请求生命周期及测试路由的回归用例，并维护既有 SyncShop OpenAPI 契约；未修改密钥服务、权限映射或数据结构。
- 已通过：两个修改 PHP 文件的 `php -l`、`git diff --check`、迁入后 `openspec validate fix-copy-shop-discount-input-validation --strict`。
- 未执行 PHPUnit：没有 `.env.testing`，当前配置指向本机 `172.20.0.5:3306/wshop_store` 和 `172.20.0.6:6379`；按 `.ai/context/testing.md`，环境未确认前不运行事务和模块装载测试。测试代码已补充，不代表测试通过，也未实测旧代码失败。
- 待环境确认后在目标 `platform/` 使用宿主机 PHP 执行：`php vendor/bin/phpunit Modules/SyncShop/Tests/Feature/GetKeyRequestTest.php`。先建立原逻辑的失败证据，再验证修复及相关复制店铺回归；不得自动切换或重建数据库。
- 访问令牌、缓存和事件副作用通过密钥服务调用入口隔离及源记录数量断言设计约束；更完整运行态证据仍须执行测试取得。
- 回滚：仅撤销本缺陷的请求层补丁及对应契约改动即可；没有 migration。回滚后会恢复非法输入触发 500 的风险。保留用户已有的未跟踪文件，不回滚其他变更。
- 进度：7/15。其余任务依赖回归执行与环境验收，未提前勾选；当前不归档。
