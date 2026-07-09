# think-orm

ThinkPHP 5.0.24 ORM 的独立 composer 包移植。**保留原 `think\` 命名空间**，零改动拷贝核心源码，配套 8 个桩文件替换框架依赖。

官方 [`topthink/think-orm`](https://github.com/top-think/think-orm) 从 TP **5.1+** 抽出，API 与 5.0.24 不兼容，因此必须直接 fork 5.0.24 实际源码。

---

## 安装

```bash
composer require think-orm/orm
```

需要 PHP >= 7.2 + `ext-pdo`。MySQL 默认 `ext-pdo_mysql`。

---

## 三步上手

### 1. 启动

```php
require __DIR__ . '/vendor/autoload.php';

\ThinkOrm\Orm::boot([
    'database' => [
        'type'     => 'mysql',
        'hostname' => '127.0.0.1',
        'hostport' => 3306,
        'database' => 'app',
        'username' => 'root',
        'password' => 'secret',
        'charset'  => 'utf8mb4',
        'prefix'   => '',
        'debug'    => false,
    ],
]);
```

### 2. 创建模型（默认解析到 `app\<module>\model\<Name>`）

最常见的方式是写一个继承 `think\Model` 的子类：

```php
// 文件: app/model/User.php
namespace app\model;

use think\Model;
use think\traits\model\SoftDelete;

class User extends Model
{
    use SoftDelete;

    protected $table = 'users';
    protected $autoWriteTimestamp = 'datetime';   // 自动管理 create_time / update_time
    protected $deleteTime = 'delete_time';        // 软删字段
    protected $hidden = ['password'];             // 序列化时隐藏
    protected $readonly = ['name'];               // 只读字段

    // 关联
    public function posts()      { return $this->hasMany(Post::class); }
    public function profile()    { return $this->hasOne(Profile::class); }
    public function roles()      { return $this->belongsToMany(Role::class, 'user_roles'); }

    // 读写器
    public function getNameAttr($v) { return ucfirst($v); }

    // 命名范围
    public function scopeActive($q) { return $q->where('is_active', 1); }
}
```

> **yf 项目用户**：可以直接继承本包提供的 `app\common\BaseModel`（位于 `example/app/common/BaseModel.php`，可拷到自己的项目里）。它把 yf 项目的 BaseModel 完整移植过来，提供 `add / adds / upd / updBy / updAttr / del / info / infoBy / lists / listBy / listByIds / listPageBy / search / countBy / maxBy / minBy / avgBy / sumBy / valueBy / inc / dec / upSert` 等 yf 风格方法。完整用法见 **`example/` 目录**（含 Notice/Smartpark 模型与验证器、字段格式化、自动时间戳、JSON 字段、关联、PSR-3 SQL 日志、`run.php` 端到端示例）。

### 3. 用 `model()` / `validate()` 操作

```php
// 取模型实例（单例）
$User = model('User');

// CRUD
$user = $User->find(1);
$users = $User->where('age', '>', 18)->order('id desc')->select();
$newId = $User->insertGetId(['name' => 'a', 'email' => 'a@x']);
$User->where('id', $newId)->update(['age' => 20]);

// 或走静态
$user = \app\model\User::get(1);
$user->age = 21;
$user->save();
\app\model\User::destroy([2, 3]);

// 关联
foreach (\app\model\User::get(1)->posts as $p) { /* ... */ }

// 验证
$v = validate('User');
if (!$v->check($data)) {
    echo $v->getError();
}
```

---

## 创建验证器（默认解析到 `app\validate\<Name>`）

```php
// 文件: app/validate/User.php
namespace app\validate;

use think\Validate;

class User extends Validate
{
    protected $rule = [
        'name'  => 'require|max:30',
        'email' => 'require|email',
        'age'   => 'integer|>=:0|<=:150',
    ];

    protected $message = [
        'name.require' => '名字必须填',
        'name.max'     => '名字不能超过 30 字符',
        'email'        => '邮箱格式错误',
    ];

    protected $scene = [
        'create' => ['name', 'email', 'age'],
        'update' => ['name', 'age'],
        'login'  => ['email'],
    ];
}
```

```php
validate('User')->scene('login')->check(['email' => 'a@b.com']);
```

---

## 命名空间与 `model()`/`validate()` 解析

`model()` / `validate()` 解析器有 4 种输入形式，**所有形式都自动把下划线命名转成大驼峰**：

| 调用 | 解析结果 | 目录 |
|---|---|---|
| `model('user')` | `app\model\User` | `app/model/User.php` |
| `model('user_order')` | `app\model\UserOrder` | `app/model/UserOrder.php`（下划线 → 大驼峰） |
| `model('iot/xxx')` | `app\iot\model\Xxx` | `app/iot/model/Xxx.php`（模块/名字） |
| `model('iot/user_order')` | `app\iot\model\UserOrder` | `app/iot/model/UserOrder.php` |
| `model('iot/v1/xxx')` | `app\iot\model\v1\Xxx` | `app/iot/model/v1/Xxx.php`（多层路径） |
| `model('iot/v1/user_order')` | `app\iot\model\v1\UserOrder` | `app/iot/model/v1/UserOrder.php` |
| `model('\\App\\Special\\User')` | 原样使用 | 直接 `new` 这个类 |

### 转换规则（`Loader::parseName`）

- **正向**（小写下划线 → 大驼峰）：`user_order` → `UserOrder`、`car_parking_log` → `CarParkingLog`
- 调用方用小写下划线（更符合 URL/参数习惯），实际类名是大驼峰
- **反向**：`UserOrder` → `user_order`（仅在某些自动场景用，比如 `with('userOrder')` 也可解析到 `user_order` 关联）

### 验证器同规则

`validate('iot/Car')` → `app\iot\validate\Car`、`validate('iot/v1/CarOrder')` → `app\iot\validate\v1\CarOrder`。

### 自定义命名空间根

默认根命名空间是 `app`（由 `App::$namespace` 控制）。如果你的项目不叫 `app`：

```php
\think\App::$namespace = 'My\\App';   // model('User') → My\App\model\User
```

### common 模块 fallback

如果 `model('iot/NotExists')` 在 iot 模块下找不到，Loader 会自动尝试 `app\common\model\NotExists`。这是 yf 的多模块共享模型机制。

### 实际例子（yf 项目风格）

```php
namespace app\parkinglot\model\v1;

use app\parkinglot\model\BModel;
use app\parkinglot\model\v1\Smartpark;

class Car extends BModel
{
    // 关联：直接用类常量 Smartpark::class —— 比 model('xxx/xxx')->class 更直接，
    // IDE 可跳转、PHPStan 可静态分析。
    public function smartparkInfo()
    {
        return $this->belongsTo(Smartpark::class, 'smartpark_id', 'id')
            ->where(['status' => 1, 'is_del' => 0]);
    }
}
```

> 历史写法 `model('smartpark/smartpark')->class` 也能用（`model()` 返回实例，`->class` 取其 FQCN），但**不推荐**——多一次实例化、绕一道字符串解析、IDE 无法跟踪。新代码统一用 `TargetClass::class`。

文件结构：
```
example/app/
├── di/
│   ├── model/
│   │   └── v1/
│   │       ├── Notice.php     ← model('di/v1/Notice') 或 model('di/v1/notice')
│   │       └── Smartpark.php
│   └── validate/
│       └── Notice.php
├── parkinglot/
│   ├── model/
│   │   ├── BModel.php          ← 不通过 model() 解析（直接 use）
│   │   └── v1/
│   │       ├── Car.php         ← model('parkinglot/v1/Car')
│   │       ├── CarOwner.php
│   │       └── ...
│   └── validate/
│       ├── BaseValidator.php
│       └── Car.php             ← validate('parkinglot/Car')
└── common/
    └── BaseModel.php
```

---

## yf 风格业务模块参考：parkinglot example

`example/app/parkinglot/` 是从 yf 真实业务抽出来的最小可运行参考，覆盖以下高级模式（每个都有对应测试）：

### 1. BModel：双 readonly + 默认关联

```php
// example/app/parkinglot/model/BModel.php
class BModel extends BaseModel
{
    public $model = null;                    // 必须为 public（否则被 __set 拦截）
    protected $readonly = ['smartpark_id', 'parkinglot_id'];

    public function smartparkInfo()
    {
        return $this->belongsTo(Smartpark::class, 'smartpark_id', 'id')
            ->where(['status' => 1, 'is_del' => 0]);     // 条件关联
    }

    public function useWithSp()              // 链式预加载快捷方法
    {
        return $this->useWith(['smartpark_info', 'parkinglot_info']);
    }
}
```

### 2. `$insert` 自动字段 + 修改器（默认值兜底）

```php
class Car extends BModel
{
    protected $insert = ['is_temp_number', 'is_new_energy'];

    // 显式传值则尊重，否则按车牌号推断
    protected function setIsTempNumberAttr($value, $data)
    {
        if (is_null($value) || $value === '') {
            return stripos($data['number'] ?? '', '临') === false ? 0 : 1;
        }
        return $value;
    }
}
```

### 3. belongsTo + `->bind()` 字段绑定

把关联表字段"绑"成本模型字段，访问 `$owner->name` 实际取自 user 表：

```php
public function userInfo()
{
    return $this->belongsTo(User::class, 'user_id', 'id')->bind([
        'name', 'face', 'email', 'mobile', 'nick_name', 'real_name',
    ]);
}
```

### 4. belongsToMany + pivot 条件过滤

通过 `Request::instance()->param('smartpark_id/d')` 在运行时给 pivot 加条件：

```php
public function carOwnerList()
{
    $rel = $this->belongsToMany(CarOwner::class, 'pt_car_car_owner', 'car_owner_id', 'car_id');
    $sp = Request::instance()->param('smartpark_id/d', 0);
    if ($sp) {
        $rel->getQuery()->where(['pivot.smartpark_id' => $sp]);
    }
    return $rel;
}
```

### 5. 多层嵌套 with

```php
public function useWithFull()
{
    return $this->useWith([
        'smartpark_info',
        'fixcar_list' => ['smartpark_info', 'parkinglot_info'],   // 二级嵌套
        'car_owner_list',
    ]);
}
```

### 6. 7 个 yf 风格验证规则（`BaseValidator`）

| 规则 | 语义 | 示例 |
|---|---|---|
| `sometimes` | 字段存在时才校验（TP 默认行为已涵盖，这里仅作声明） | `'mobile' => 'sometimes\|regex:^1\d{10}$'` |
| `conflict:a,b` | 当前字段存在时，a/b 都不能存在 | `'email' => 'conflict:mobile,name'` |
| `r_if:field,v1,v2` | field 等于 v1 或 v2 时当前字段必填 | `'mobile' => 'r_if:contact_type,phone,sms'` |
| `r_with:a,b` | a 或 b 存在时当前字段必填 | `'nick_name' => 'r_with:name,email'` |
| `r_with_all:a,b` | a 和 b 都存在时当前字段必填 | 同上 |
| `r_without:a,b` | a 或 b 不存在时当前字段必填 | 同上 |
| `r_without_all:a,b` | a 和 b 都不存在时当前字段必填 | 同上 |

> 本包的 `Validate::checkItem` 已扩展：所有 `r_*` 规则即使在字段为空时也会触发（默认 TP 行为只对 `require*` 规则如此）。这是与上游 TP 5.0.24 的**唯一行为差异**，但完全是 yf 业务必需的。

### 7. `BaseModel::validateData` 错误转异常

规则写错（如 `'require|integeregt:0'` 拼错）时，原 TP 5.0.24 会触发 PHP Warning 然后静默通过。本包的 BaseModel 用 `set_error_handler` 包裹，转成 `ValidateException` 便于排查：

```php
protected function validateData($data, $rule = null, $batch = null)
{
    // ...build $validate...
    set_error_handler(function ($code, $msg, $file, $line) {
        // iconv 转 GBK→utf-8 后抛 ValidateException
        throw new ValidateException("Validate ERROR: [{$code}] {$msg} in file: {$file} on line: {$line}");
    });
    try {
        $ok = $validate->batch($batch)->check($data);
    } finally {
        restore_error_handler();
    }
    // ...
}
```

---

## 配置

### 数据库（完整选项）

```php
\ThinkOrm\Orm::boot([
    'database' => [
        'type'            => 'mysql',          // 本包仅支持 mysql
        'hostname'        => '127.0.0.1',
        'hostport'        => 3306,
        'database'        => 'app',
        'username'        => 'root',
        'password'        => '',
        'dsn'             => '',               // 显式 dsn 优先
        'charset'         => 'utf8mb4',
        'prefix'          => '',
        'debug'           => false,
        'deploy'          => 0,                // 0=单库，1=分布式
        'rw_separate'     => false,
        'master_num'      => 1,
        'slave_no'        => '',
        'fields_strict'   => true,             // 严格字段检查
        'resultset_type'  => 'array',          // 或 'collection'
        'auto_timestamp'  => false,            // 全局自动时间戳
        'datetime_format' => 'Y-m-d H:i:s',
        'use_schema'      => false,            // 读取 RUNTIME_PATH/schema 字段缓存
        'query'           => '\\think\\db\\Query',   // 自定义 Query 类
    ],
    'paginate' => [
        'type'      => 'bootstrap',
        'var_page'  => 'page',
        'list_rows' => 15,
    ],
]);
```

### 自定义常量

```php
// boot 之前先定义，否则自动 fallback 到 sys_get_temp_dir()/think-orm/
define('RUNTIME_PATH', '/var/log/myapp/');
\ThinkOrm\Orm::boot([...]);
```

---

## 注入（可选）

```php
// PSR-3 SQL 日志
\think\Log::setLogger($monolog);

// PSR-16 让 Query::cache() 真正生效
\think\Cache::setInstance($psr16Cache);

// 自定义 Request（用于 Paginator 解析 page 参数、Validate::method）
\think\Request::setInstance(new MyRequest());
```

未注入时所有调用都走 NoOp 桩，不报错。

---

## API 速查

| 用法 | 例子 |
|---|---|
| 模型实例 | `model('User')` |
| 验证器实例 | `validate('User')` |
| 单条 | `User::get($id)` / `User::where('x',1)->find()` |
| 多条 | `User::all()` / `User::where(...)->select()` |
| 单值 | `User::where('id',1)->value('name')` |
| 列值 | `User::column('name','id')` |
| 插入 | `User::create($data)` / `(new User)->save($data)` |
| 更新 | `User::update($data)` / `$u->save()` |
| 删除 | `User::destroy($ids)` / `$u->delete()` |
| 自增自减 | `User::where('id',1)->inc('hits')->update()` |
| 关联 | `User::with('posts,profile')->select()` |
| 分页 | `User::where(...)->paginate(15)` |
| 事务 | `Db::transaction(fn() => ...)` |
| 分批 | `User::chunk(100, function($rows){...})` |
| 软删恢复 | `User::onlyTrashed()->find()->restore()` |

---

## 测试

```bash
# 准备 MySQL 测试库
mysql -u root -p123456 -e "CREATE DATABASE think_orm_test CHARACTER SET utf8mb4"

# 全套（默认 127.0.0.1:3306 root/123456，可用环境变量覆盖）
composer test

# 不需 DB 的单元测试
composer test:unit

# 过滤
composer test:filter -- RelationHasMany
```

### 跑 example

```bash
mysql -u root -p123456 -e "CREATE DATABASE think_orm_example CHARSET utf8mb4"
php example/run.php
```

`example/run.php` 端到端演示：`model()` 解析 → 创建 → 字段格式化（JSON/数组/decimal→float/append）→ 关联预加载 → readonly → 验证器场景 → 聚合。**包含 13 个 section**：di 模块（Notice/Smartpark）+ parkinglot 模块（Car/CarOwner/Parkinglot/Smartpark/User 的 BModel + 条件关联 + bind + 多层嵌套 + pivot 过滤 + readonly）。所有 SQL 通过 PSR-3 logger 打到 stdout。

**测试覆盖**（385 tests / 737 assertions）：

| 范围 | 测试文件 |
|---|---|
| 桩文件 | `SupportStubTest` |
| 配置 | `ConfigTest` |
| Loader 类 | `LoaderTest`（parseName / parseClass / addNamespaceAlias / addClassMap / model() / validate() 解析、缓存、common fallback、FQCN passthrough、异常） |
| 验证规则 | `ValidateRulesTest`（全部内置规则 + 中文消息） |
| 集合 | `CollectionTest` |
| CRUD | `QueryCrudTest`、`InsertAllTest` |
| 事务 | `TransactionTest`（commit/rollback/嵌套） |
| Query Builder | `QueryBuilderTest`（where/join/group/having/order/limit/page/inc/dec） |
| Query 高级 API | `AdvancedQueryTest`（whereRaw/whereOrRaw/whereExists/whereNotExists/whereExp/whereTime/whereNotNull/whereNotBetween/whereNotLike/useSoftDelete/fetchSql/getPk/getTableFields） |
| 子查询 | `SubqueryTest` |
| 分批 | `ChunkCursorTest` |
| Model CRUD | `ModelCrudTest`、`ModelAccessorMutatorTest`、`ModelAutoTimestampTest` |
| Model 高级 | `ModelWorkflowTest`（`model()`/`validate()` helper、hidden/append、scope、事件、readonly） |
| Model 高级 API | `AdvancedApiTest`（Paginator URL 辅助：appends/fragment/getUrlRange/getCurrentPage/getCurrentPath/render；Model::has / hasWhere / together） |
| 验证器 | `ModelValidationTest`（Model 内嵌规则 + 失败回滚） |
| 软删 | `SoftDeleteTest` |
| 关联 | `RelationHasOneTest`、`RelationHasManyTest`、`RelationBelongsToTest`、`RelationBelongsToManyTest`、`RelationHasManyThroughTest`、`RelationMorphTest` |
| 复合主键 | `ComplexPkTest` |
| JSON 字段 | `JsonFieldTest` |
| 分页 | `PaginatorTest` |
| 闭包 where | `ClosureWhereTest` |
| **yf 风格 BaseModel** | `YfBaseModelTest`（59 个测试：initialize/curr_model、自动时间戳、JSON、读写器、append、关联、readonly、CRUD、search 分页、聚合、upSert、trait spd/sca/listIndexBy/fieldWhere/withModel/rollbackQuery、PSR-3 SQL 日志、validate helper、端到端） |
| **yf parkinglot 模块** | `ParkinglotIntegrationTest`（20 个测试：BModel 双 readonly + 条件关联 + helper，$insert 自动字段 + 修改器，belongsTo+bind，hasMany，belongsToMany+pivot 过滤，多层嵌套 with，search_or，7 个 BaseValidator 自定义规则，validateData 错误转异常） |

---

## 与原 TP 5.0.24 的差异

1. **`helper.php` 精简**：保留 `exception / config / dump / debug / model / validate / db / import / trace / load_relation / collection`，删除 web 相关助手（lang/input/widget/controller/action/url/session/cookie/cache/request/response/view/json/jsonp/xml/redirect/abort/halt/token/load_trait/vendor）。
2. **`Config.php`**：移除依赖 `Request::module()` 的动态 extra-config 加载分支。
3. **`Validate.php`**：`$typeMsg` 改为硬编码中文；移除 `{%xxx%}` 多语言包装与 `Lang::get/has` 调用。
4. **`behavior` 规则**：本包未移植 `think\Hook` 类。`Validate::behavior()` 直接返回 true（规则视为通过）；如需自定义行为验证，覆盖该方法即可。
5. **`token` 规则**：依赖 Session，桩默认返回 null，token 规则将失败；可注入 Session 实现启用。
6. **`Paginator`**：默认 `Request::param()` 桩返回 null，页码默认为 1；要支持 HTTP 上下文需注入自定义 Request。
7. **`Validate::checkItem` 扩展**：所有以 `r_` 开头的规则（`r_if` / `r_with` / `r_with_all` / `r_without` / `r_without_all`）即使在字段为空时也会触发——这是 yf 业务必需的条件必填语义。原 TP 5.0.24 只对 `require*` 规则做此处理，本包扩展至 `r_*`。
8. **`Loader.php`**：移除了 `register()` / `loadComposerAutoloadFiles()` / `registerComposerLoader()` 等 SPL autoload 注册逻辑——Composer 已处理自动加载。`Loader::model()` 在跨模块查找时的 `EXTEND_PATH`/`APP_PATH` 改为读项目根的 `extend/` 与 `app/` 目录。
9. **trait 命名空间迁移**：原 TP 5.0.24 的 `traits\model\SoftDelete` 与 `traits\think\Instance` 占用顶层 `traits\` 命名空间，不适合作为公开 composer 包发布。本包改为：
   - `traits\model\SoftDelete` → **`think\traits\model\SoftDelete`**
   - `traits\think\Instance` → **`think\traits\Instance`**（同时整理目录结构）
   
   从 yf 迁移过来的代码需把 `use traits\model\SoftDelete;` 改为 `use think\traits\model\SoftDelete;`。
10. **`Query::setInc / setDec` 实时写入**：移除了 `$lazyTime` 延迟累积更新分支（依赖 Cache 的 inc/dec），inc/dec 永远实时写入 DB。`$lazyTime` 参数保留以维持签名兼容但被忽略。
11. **不依赖缓存**：`think\Cache` 桩默认所有操作返回 false / null / 空。`Query::cache()` API 保留（每次仍走 DB），PSR-16 缓存可选注入但不推荐。

---

## License

Apache-2.0（同 ThinkPHP 5.0.24）。
