<?php
// +----------------------------------------------------------------------
// | example 启动脚本
// | 用法：php example/run.php
// | 前置：mysql -u root -p123456 -e "CREATE DATABASE think_orm_example CHARSET utf8mb4"
// +----------------------------------------------------------------------

require __DIR__ . '/../vendor/autoload.php';

use app\di\model\v1\Notice;
use app\di\model\v1\Smartpark;
use app\parkinglot\model\v1\Car;
use app\parkinglot\model\v1\CarOwner;
use app\parkinglot\model\v1\CarParking;
use app\parkinglot\model\v1\Parkinglot;
use app\parkinglot\model\v1\Smartpark as PtSmartpark;
use app\parkinglot\model\v1\User as PtUser;
use think\App;
use think\Db;
use ThinkOrm\Orm;
use think\Log;
use think\Request;

// 1) 让 model() 解析到 app 命名空间（默认就是 'app'，这里显式声明便于阅读）
App::$namespace = 'app';

// 2) 启动 ORM（从环境变量读，回退到本地默认）
Orm::boot([
    'database' => [
        'type'     => 'mysql',
        'hostname' => getenv('TORM_DB_HOST') ?: '127.0.0.1',
        'hostport' => getenv('TORM_DB_PORT') ?: 3306,
        'database' => getenv('TORM_DB_NAME') ?: 'think_orm_example',
        'username' => getenv('TORM_DB_USER') ?: 'root',
        'password' => getenv('TORM_DB_PASS') ?: '123456',
        'charset'  => 'utf8mb4',
        'prefix'   => '',
        'debug'    => true,
    ],
    // 启用文件 SQL 日志（PSR-3 logger 未注入时生效）
    'log' => [
        'file' => __DIR__ . '/runtime/sql.log',
    ],
]);

// 3) SQL 日志策略（按优先级）：
//    a) PSR-3 logger（Log::setLogger）— 最高
//    b) 文件日志（Orm::boot 中 'log.file'）— 中
//    c) 内存缓冲（默认）— 最低
//
// 这里注释掉 PSR-3，让上面 boot() 配的 'log.file' 生效，SQL 会写到 example/runtime/sql.log
// 如果你想 SQL 同时打到 stdout，取消下面 4 行注释即可（PSR-3 优先，文件将不再写入）：
// $enableLogger = new class {
//     public function log($level, $message, array $context = []) { echo "[SQL] {$message}\n"; }
// };
// Log::setLogger($enableLogger);

echo "SQL 日志写入: " . __DIR__ . "/runtime/sql.log\n";

// 4) 准备 schema（独立运行时重建）
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    getenv('TORM_DB_HOST') ?: '127.0.0.1',
    getenv('TORM_DB_PORT') ?: 3306,
    getenv('TORM_DB_NAME') ?: 'think_orm_example'
);
$pdo = new PDO($dsn, getenv('TORM_DB_USER') ?: 'root', getenv('TORM_DB_PASS') ?: '123456');
foreach (array_filter(array_map('trim', explode(';', file_get_contents(__DIR__ . '/schema.sql')))) as $stmt) {
    if ($stmt !== '') $pdo->exec($stmt);
}
Db::clear();

// 5) 演示：通过 model() 用法创建一条 Smartpark 和 Notice
echo "\n========== 1. 通过 model() 创建园区 ==========\n";
$sp = model('di/v1/Smartpark');
$spId = $sp->add([
    'name' => '示范园区',
    'number' => 'SP001',
]);
echo "创建园区 ID: {$spId['id']}\n";

echo "\n========== 2. 创建通知（自动写入 add_time、payload JSON 编码、channels 数组转字符串） ==========\n";
$notice = model('di/v1/Notice')->add([
    'smartpark_id' => $spId['id'],
    'name'         => '到期提醒',
    'channels'     => ['sms', 'email'],      // 写入时序列化为 "sms,email"
    'payload'      => ['tpl_id' => 100, 'args' => ['鄂A001']],  // type=json 自动编码
    'status'       => 0,
    'amount'       => '12.50',
]);
echo "创建通知 ID: {$notice['id']}, add_time(int): {$notice['add_time']}\n";

echo "\n========== 3. 查询并触发字段格式化（channels → 数组、payload → array、status_text、amount → float） ==========\n";
$row = model('di/v1/Notice')->info($notice['id']);
echo "channels: " . json_encode($row['channels'], JSON_UNESCAPED_UNICODE) . "\n";  // ['sms','email']
echo "payload: " . json_encode($row['payload'], JSON_UNESCAPED_UNICODE) . "\n";
echo "status_text: {$row['status_text']}\n";     // "待发送"
echo "amount: {$row['amount']} (type=" . gettype($row['amount']) . ")\n";  // float

echo "\n========== 4. 关联查询：with('smartpark_info') ==========\n";
$with = Notice::with('smartparkInfo')->find($notice['id']);
echo "园区名: {$with->smartpark_info->name}\n";

echo "\n========== 5. 测试 readonly：smartpark_id 应保持不变 ==========\n";
// readonly 由 Model::save 强制；Query::update 不强制
$n = Notice::get($notice['id']);
$origSp = $n->smartpark_id;
$n->smartpark_id = 999;
$n->status = 1;
$n->isUpdate(true)->save();
$after = model('di/v1/Notice')->info($notice['id']);
echo "smartpark_id: {$after['smartpark_id']}（应为 {$origSp}）\n";
echo "status: {$after['status']}（应为 1）\n";
echo "status_text: {$after['status_text']}（应为 已发送）\n";

echo "\n========== 6. 验证器：场景 add 缺 smartpark_id 时失败 ==========\n";
$m = model('di/v1/Notice');
$res = $m->add(['name' => 'x', 'channels' => 'sms']);
if ($res === false) {
    echo "验证失败: " . $m->error . "\n";
} else {
    echo "（异常：应返回 false 但返回了 true）\n";
}

echo "\n========== 7. 聚合：sumBy / avgBy（验证 decimal 字段） ==========\n";
model('di/v1/Notice')->add([
    'smartpark_id' => $spId['id'],
    'name' => 't2',
    'channels' => 'sms',
    'amount' => '7.50',
]);
$sum = model('di/v1/Notice')->sumBy(['smartpark_id' => $spId['id']], 'amount');
echo "总金额: {$sum}（应为 20）\n";

// ====================================================================
// parkinglot 模块：演示 yf 真实业务里的高级关联模式
// ====================================================================

echo "\n========== 8. parkinglot：BModel 双 readonly + 条件关联 ==========\n";
$ptSp  = PtSmartpark::create(['name' => '示范园区', 'number' => 'SP001', 'status' => 1, 'is_del' => 0]);
$ptLot = Parkinglot::create(['smartpark_id' => $ptSp->id, 'name' => 'P1', 'number' => 'PT001']);

// 普通车牌（7 位）→ is_new_energy=0；不含"临" → is_temp_number=0
$car = Car::create([
    'smartpark_id'      => $ptSp->id,
    'parkinglot_id'     => $ptLot->id,
    'last_smartpark_id' => $ptSp->id,
    'number'            => '鄂A12345',
]);
$rawCar = Db::name('pt_car')->where('id', $car->id)->find();
echo "Car#{$car->id} number={$rawCar['number']} is_temp={$rawCar['is_temp_number']} is_new_energy={$rawCar['is_new_energy']}（应为 0/0）\n";

// 新能源（8 位）→ is_new_energy=1
$car2 = Car::create([
    'smartpark_id'  => $ptSp->id,
    'parkinglot_id' => $ptLot->id,
    'number'        => '鄂A12345D',
]);
$rawCar2 = Db::name('pt_car')->where('id', $car2->id)->find();
echo "Car#{$car2->id} number={$rawCar2['number']} is_new_energy={$rawCar2['is_new_energy']}（应为 1）\n";

echo "\n========== 9. parkinglot：belongsTo + bind 字段绑定 ==========\n";
$ptUser = PtUser::create([
    'name' => '张三', 'mobile' => '13800138000', 'email' => 'zs@x.com', 'nick_name' => '老张',
]);
$owner = CarOwner::create([
    'smartpark_id'  => $ptSp->id,
    'parkinglot_id' => $ptLot->id,
    'user_id'       => $ptUser->id,
]);
// with('user_info') 后 User 的字段"绑"到 CarOwner 上
$ownerLoaded = CarOwner::with('user_info')->find($owner->id);
echo "Owner#{$owner->id} name={$ownerLoaded->name}（绑自 user.name=张三） mobile={$ownerLoaded->mobile} nick={$ownerLoaded->nick_name}\n";

echo "\n========== 10. parkinglot：hasMany 一对多 ==========\n";
CarParking::create(['smartpark_id' => $ptSp->id, 'parkinglot_id' => $ptLot->id, 'car_id' => $car->id]);
CarParking::create(['smartpark_id' => $ptSp->id, 'parkinglot_id' => $ptLot->id, 'car_id' => $car->id]);
$carReloaded = Car::get($car->id);
echo "Car#{$car->id} fixcar_list count=" . count($carReloaded->fixcar_list) . "（应为 2）\n";

echo "\n========== 11. parkinglot：多层嵌套 with（useWithFull） ==========\n";
// 重建连接清掉字段缓存，避免 schema 缓存干扰
Db::clear();
$carFull = (new Car())->useWithFull()->getQuery()->find($car->id);
echo "Car smartpark_info.name={$carFull['smartpark_info']['name']}\n";
echo "Car fixcar_list[0].smartpark_info.name={$carFull['fixcar_list'][0]['smartpark_info']['name']}\n";
echo "Car fixcar_list[0].parkinglot_info.name={$carFull['fixcar_list'][0]['parkinglot_info']['name']}\n";

echo "\n========== 12. parkinglot：belongsToMany + pivot 过滤 ==========\n";
// 注入 Request 模拟 HTTP 参数 smartpark_id，触发 pivot 过滤
$mockReq = new class extends Request {
    public function param($name = '', $default = null)
    {
        if ($name === 'smartpark_id/d') return $GLOBALS['__example_smartpark_id'] ?? 0;
        return $default;
    }
};
Request::setInstance($mockReq);
$GLOBALS['__example_smartpark_id'] = $ptSp->id;

// 在中间表插入一条 car↔owner 关联（带 smartpark_id）
Db::name('pt_car_car_owner')->insert([
    'smartpark_id'  => $ptSp->id,
    'car_id'         => $car->id,
    'car_owner_id'   => $owner->id,
]);
$carWithOwners = Car::get($car->id);
echo "Car#{$car->id} car_owner_list count=" . count($carWithOwners->car_owner_list) . "（按 smartpark_id={$ptSp->id} 过滤，应为 1）\n";

Request::setInstance(null);
unset($GLOBALS['__example_smartpark_id']);

echo "\n========== 13. parkinglot：双 readonly 强制（更改应被忽略） ==========\n";
$car->smartpark_id = 999999;
$car->parkinglot_id = 888888;
$car->isUpdate(true)->save();
$reloaded = Db::name('pt_car')->where('id', $car->id)->find();
echo "Car#{$car->id} 改后 smartpark_id={$reloaded['smartpark_id']}（应为 {$ptSp->id}） parkinglot_id={$reloaded['parkinglot_id']}（应为 {$ptLot->id}）\n";

echo "\nDone.\n";
