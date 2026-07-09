# Changelog

本项目遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/) 格式，版本号遵守 [Semantic Versioning](https://semver.org/lang/zh-CN/)。

## [Unreleased]

## [1.0.0] - 2026-07-09

首个稳定发布。从 ThinkPHP 5.0.24 抽离的独立 ORM 包，保留 `think\` 命名空间，可在非 ThinkPHP 项目中复用 TP 5.0 风格 ORM。

### 新增

- **核心包**：`Orm::boot()` 程序化入口，支持 `database` / `paginate` / `log` 顶层配置
- **DB 层**：`think\Db` facade、`think\db\Query`（3064 行，零改动）、`think\db\Connection`、`think\db\Builder`、`think\db\builder\Mysql`、`think\db\connector\Mysql`
- **Model 层**：`think\Model`、`think\model\Collection`、`think\model\Pivot`、`think\model\Relation` 及 9 种关联（BelongsTo、BelongsToMany、HasMany、HasManyThrough、HasOne、MorphMany、MorphOne、MorphTo、OneToOne）
- **Validate**：`think\Validate` 全部内置规则 + 中文错误消息 + 场景 + `r_*` 条件必填扩展（与 TP 5.0.24 的唯一行为差异）
- **Loader**：`model()` / `validate()` / `controller()` / `action()` / `import()` / `db()` helper、`parseName` / `parseClass` 解析器、`addClassMap` / `addNamespaceAlias`、common 模块 fallback
- **Paginator**：`think\Paginator` + `think\paginator\driver\Bootstrap`，支持 `appends` / `fragment` / `getUrlRange` / `render`
- **Collection**：`think\Collection`，扩展 `_think\Collection` 实现 ArrayAccess + each + toArray
- **Config / Env / Exception**：`think\Config`（移除依赖 `Request::module()` 的动态加载分支）、`think\Env`、`think\Exception` + 11 个异常类
- **traits**：`think\traits\model\SoftDelete`、`think\traits\Instance`（命名空间从顶层 `traits\*` 迁移到 `think\traits\*`）
- **Support 桩**：`think\App` / `think\Request` / `think\Lang` / `think\Hook` / `think\Session` / `think\Debug` / `think\Cache` / `think\Log` 共 8 个 NoOp 桩，支持注入 PSR-3 Logger、PSR-16 Cache、自定义 Request
- **helper 函数**：精简版 `exception / config / dump / debug / model / validate / db / import / trace / load_relation / collection`
- **Example**：完整 yf 风格业务模块参考（`example/app/`）：BaseModel（30 个 yf 风格方法）+ TModel trait（12 个方法）+ parkinglot 模块（BModel 双 readonly + 条件关联 + pivot 过滤 + 多层 with）+ di 模块（Notice / Smartpark 验证器）
- **配置兼容性**：`Orm::boot()` defaults 含 yf / TP 5.0.24 `database.php` 全部标准 key（type / hostname / database / username / password / hostport / dsn / params / charset / prefix / debug / deploy / rw_separate / master_num / slave_no / read_master / fields_strict / resultset_type / auto_timestamp / datetime_format / sql_explain / socket / use_schema / builder / query / break_reconnect）

### 测试

- **411 tests / 801 assertions 全绿**
- **Unit 128 个**（不需 MySQL）：SupportStub、Config（含 yf 全 key 校验）、Loader 解析、Validate 全规则、Collection
- **Integration 283 个**：CRUD、事务（嵌套 + 回滚）、Query Builder、高级 Query（whereRaw / whereExists / whereExp / whereTime / useSoftDelete / fetchSql）、子查询、chunk + cursor、InsertAll、Model CRUD、读写器、自动时间戳、Model 校验、软删、6 种关联、复合主键、JSON 字段、分页（含 URL 辅助）、闭包 where、Model 高级 API（has / hasWhere / together）、Paginator 全 API
- **yf 风格覆盖 97 个测试**：`YfBaseModelTest` 77 个（每个 BaseModel + TModel 公共方法直接覆盖）+ `ParkinglotIntegrationTest` 20 个（BModel + 条件关联 + pivot + 多层 with + 7 个自定义规则）

### 文档

- README.md：安装、三步上手、模型 / 验证器、`model()` / `validate()` 4 种调用形式 + 命名转换规则、yf 业务模块参考（7 种高级模式）、完整配置说明、注入选项、API 速查、测试覆盖矩阵、与原 TP 5.0.24 的 11 项差异说明

### 与 ThinkPHP 5.0.24 的差异

详见 README "与原 TP 5.0.24 的差异" 章节。核心差异：

1. `helper.php` 精简，删除 web 相关助手
2. `Config.php` 移除依赖 `Request::module()` 的动态 extra-config 加载分支
3. `Validate.php` `$typeMsg` 硬编码中文；移除 `{%xxx%}` 多语言包装与 `Lang::get/has` 调用
4. `Validate::checkItem` 扩展：所有 `r_*` 规则即使在字段为空时也触发（yf 业务必需）
5. `behavior` 规则桩返回 true，视为通过
6. `token` 规则依赖 Session 注入
7. `Paginator` 默认页码 1，注入 Request 才能从 HTTP 上下文解析
8. `Loader.php` 移除 SPL autoload 注册（Composer 已处理），跨模块查找路径改读项目根的 `extend/` 与 `app/`
9. `traits\model\SoftDelete` → `think\traits\model\SoftDelete`，`traits\think\Instance` → `think\traits\Instance`
10. `Query::setInc / setDec` 实时写入，移除 `$lazyTime` 延迟累积更新分支
11. `think\Cache` 默认 NoOp，所有操作返回 false / null / 空；`Query::cache()` API 保留但每次走 DB

### 已知限制

- 仅支持 MySQL（Pgsql / Sqlite / Sqlsrv builder/connector 未移植）
- `behavior` 规则需注入 Hook 实现
- `token` 规则需注入 Session 实现
- PSR-3 Logger 和 PSR-16 Cache 都是可选注入，未注入时静默 / NoOp
