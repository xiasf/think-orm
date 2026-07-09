<?php
// +----------------------------------------------------------------------
// | parkinglot 集成测试：覆盖 yf parkinglot 模块的全部高级关联模式
// |
// | 测试维度：
// |   1. BModel：双 readonly、3 关联默认、2 helper
// |   2. $insert 自动字段 + 修改器
// |   3. belongsTo + ->where() 条件关联
// |   4. belongsTo + ->bind() 字段绑定
// |   5. hasMany 一对多
// |   6. belongsToMany 多对多 + pivot 条件过滤
// |   7. 多层嵌套 with
// |   8. search_or AND/OR 混合搜索
// |   9. BaseValidator 7 个自定义规则
// |  10. validateData 错误处理（规则写错抛 ValidateException）
// +----------------------------------------------------------------------

namespace ThinkOrm\Tests\Integration;

use app\parkinglot\model\v1\Car;
use app\parkinglot\model\v1\CarOwner;
use app\parkinglot\model\v1\CarParking;
use app\parkinglot\model\v1\Parkinglot;
use app\parkinglot\model\v1\Smartpark;
use app\parkinglot\model\v1\User;
use app\parkinglot\validate\Car as CarValidator;
use think\Db;
use think\Request;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class ParkinglotIntegrationTest extends IntegrationTestCase
{
    // ====== 测试辅助 ======

    private function seedSmartpark(int $id = 0, array $overrides = []): int
    {
        $defaults = [
            'id'      => $id,
            'name'    => '示范园区',
            'number'  => 'SP001',
            'status'  => 1,
            'is_del'  => 0,
        ];
        Db::name('pt_smartpark')->insert(array_merge($defaults, $overrides));
        return $id ?: (int) Db::name('pt_smartpark')->getLastInsID();
    }

    private function seedParkinglot(int $smartparkId, array $overrides = []): int
    {
        $defaults = [
            'smartpark_id' => $smartparkId,
            'name'         => 'P1',
            'number'       => 'PT001',
            'is_del'       => 0,
            'status'       => 1,
            'add_time'     => time(),
        ];
        return Db::name('pt_parkinglot')->insertGetId(array_merge($defaults, $overrides));
    }

    private function seedPtUser(array $overrides = []): int
    {
        $defaults = [
            'name'      => '张三',
            'face'      => '/face/zs.png',
            'email'     => 'zs@x.com',
            'mobile'    => '13800138000',
            'nick_name' => '老张',
            'real_name' => '张三',
            'add_time'  => time(),
        ];
        return Db::name('pt_user')->insertGetId(array_merge($defaults, $overrides));
    }

    private function seedCarOwner(int $spId, int $ptId, int $userId, array $overrides = []): int
    {
        $defaults = [
            'smartpark_id'  => $spId,
            'parkinglot_id' => $ptId,
            'user_id'       => $userId,
            'add_time'      => time(),
        ];
        return Db::name('pt_car_owner')->insertGetId(array_merge($defaults, $overrides));
    }

    private function seedCar(int $spId, int $ptId, array $overrides = []): int
    {
        $defaults = [
            'smartpark_id'     => $spId,
            'parkinglot_id'    => $ptId,
            'last_smartpark_id' => $spId,
            'number'           => '鄂A12345',
            'is_temp_number'   => 0,
            'is_new_energy'    => 0,
            'add_time'         => time(),
        ];
        return Db::name('pt_car')->insertGetId(array_merge($defaults, $overrides));
    }

    // ====== 1. BModel：双 readonly + 关联默认 ======

    public function testBModelDoubleReadonly()
    {
        $spId = $this->seedSmartpark();
        $ptId = $this->seedParkinglot($spId);
        $car = Car::create([
            'smartpark_id'     => $spId,
            'parkinglot_id'    => $ptId,
            'last_smartpark_id' => $spId,
            'number'           => '鄂A11111',
        ]);

        // 试图改 readonly 字段，应被忽略
        $car->smartpark_id = 999999;
        $car->parkinglot_id = 888888;
        $car->isUpdate(true)->save();

        $row = Db::name('pt_car')->where('id', $car->id)->find();
        $this->assertSame((string) $spId, (string) $row['smartpark_id']);
        $this->assertSame((string) $ptId, (string) $row['parkinglot_id']);
    }

    public function testBModelSmartparkInfoConditionedRelation()
    {
        $spId = $this->seedSmartpark();
        $ptId = $this->seedParkinglot($spId);
        $carId = $this->seedCar($spId, $ptId);

        // 正常 status=1, is_del=0 应能查到
        $car = Car::get($carId);
        $this->assertSame('示范园区', $car->smartpark_info->name);

        // 把 status 改成 0，关联应查不到（条件关联）
        Db::name('pt_smartpark')->where('id', $spId)->update(['status' => 0]);
        Db::clear();
        $car2 = Car::get($carId);
        $this->assertNull($car2->smartpark_info);
    }

    public function testBModelUseWithSpHelper()
    {
        $spId = $this->seedSmartpark();
        $ptId = $this->seedParkinglot($spId);
        $carId = $this->seedCar($spId, $ptId);

        $car = (new Car())->useWithSp()->getQuery()->find($carId);
        $this->assertSame('示范园区', $car['smartpark_info']['name']);
    }

    public function testBModelMissingFieldReturnsNullNotException()
    {
        // 构造一个 $data 里没有 last_smartpark_id 的实例
        // 模拟 yf 真实场景：访问一个可能不存在的字段，访问器应兜底返回 null 而非抛异常
        $car = new Car([
            'smartpark_id' => 1,
            'parkinglot_id' => 1,
            'number' => 'X',
        ]);

        // last_smartpark_id 未设置，访问器应返回 null
        $this->assertNull($car->last_smartpark_id);
    }

    // ====== 2. $insert 自动字段 ======

    public function testInsertAutoFields()
    {
        $spId = $this->seedSmartpark();
        $ptId = $this->seedParkinglot($spId);

        // 普通车牌（7 位）→ is_new_energy=0；不含"临" → is_temp_number=0
        $car1 = Car::create([
            'smartpark_id'  => $spId,
            'parkinglot_id' => $ptId,
            'number'        => '鄂A12345',
        ]);
        $raw1 = Db::name('pt_car')->where('id', $car1->id)->find();
        $this->assertSame('0', (string) $raw1['is_temp_number']);
        $this->assertSame('0', (string) $raw1['is_new_energy']);

        // 临时车（含"临"）→ is_temp_number=1
        $car2 = Car::create([
            'smartpark_id'  => $spId,
            'parkinglot_id' => $ptId,
            'number'        => '临A12345',
        ]);
        $raw2 = Db::name('pt_car')->where('id', $car2->id)->find();
        $this->assertSame('1', (string) $raw2['is_temp_number']);

        // 新能源（8 位）→ is_new_energy=1
        $car3 = Car::create([
            'smartpark_id'  => $spId,
            'parkinglot_id' => $ptId,
            'number'        => '鄂A12345D',
        ]);
        $raw3 = Db::name('pt_car')->where('id', $car3->id)->find();
        $this->assertSame('1', (string) $raw3['is_new_energy']);
    }

    public function testInsertExplicitValueOverridesMutator()
    {
        $spId = $this->seedSmartpark();
        $ptId = $this->seedParkinglot($spId);

        // 显式传 is_new_energy=1，应被尊重（不被修改器覆盖）
        $car = Car::create([
            'smartpark_id'   => $spId,
            'parkinglot_id'  => $ptId,
            'number'         => '鄂A12345',
            'is_new_energy'  => 1,
        ]);
        $raw = Db::name('pt_car')->where('id', $car->id)->find();
        $this->assertSame('1', (string) $raw['is_new_energy']);
    }

    // ====== 3. belongsTo + bind() 字段绑定 ======

    public function testBelongsToBindFields()
    {
        $spId = $this->seedSmartpark();
        $ptId = $this->seedParkinglot($spId);
        $userId = $this->seedPtUser();
        $ownerId = $this->seedCarOwner($spId, $ptId, $userId);

        // 直接 with('user_info') 后，User 的字段应"绑定"到 CarOwner 实例上
        $owner = CarOwner::with('user_info')->find($ownerId);

        // bind() 字段：相当于把 User 的字段映射到本地属性
        $this->assertSame('张三', $owner->name);       // 绑定自 user.name
        $this->assertSame('老张', $owner->nick_name);
        $this->assertSame('13800138000', $owner->mobile);
        $this->assertSame('zs@x.com', $owner->email);
    }

    // ====== 4. hasMany 一对多 ======

    public function testHasManyRelation()
    {
        $spId = $this->seedSmartpark();
        $ptId = $this->seedParkinglot($spId);
        $carId = $this->seedCar($spId, $ptId);

        // 创建 3 条停车记录
        for ($i = 0; $i < 3; $i++) {
            CarParking::create([
                'smartpark_id'  => $spId,
                'parkinglot_id' => $ptId,
                'car_id'        => $carId,
            ]);
        }

        $car = Car::get($carId);
        $this->assertCount(3, $car->fixcar_list);
    }

    // ====== 5. belongsToMany + pivot 条件过滤 ======

    public function testBelongsToManyNoFilter()
    {
        $spId  = $this->seedSmartpark();
        $spId2 = $this->seedSmartpark($spId + 100, ['name' => '园区2']);
        $ptId  = $this->seedParkinglot($spId);

        $carId   = $this->seedCar($spId, $ptId);
        $ownerId = $this->seedCarOwner($spId, $ptId, $this->seedPtUser());

        // 中间表：一条 sp1，一条 sp2
        Db::name('pt_car_car_owner')->insert([
            'smartpark_id'  => $spId,
            'car_id'         => $carId,
            'car_owner_id'   => $ownerId,
        ]);
        $ownerId2 = $this->seedCarOwner($spId2, $ptId, $this->seedPtUser(['name' => '李四']));
        Db::name('pt_car_car_owner')->insert([
            'smartpark_id'  => $spId2,
            'car_id'         => $carId,
            'car_owner_id'   => $ownerId2,
        ]);

        // 不加 smartpark_id 参数 → 应该看到两个车主
        $car = Car::get($carId);
        $this->assertCount(2, $car->car_owner_list);
    }

    public function testBelongsToManyWithPivotCondition()
    {
        $spId  = $this->seedSmartpark();
        $spId2 = $this->seedSmartpark($spId + 100, ['name' => '园区2']);
        $ptId  = $this->seedParkinglot($spId);

        $carId   = $this->seedCar($spId, $ptId);
        $ownerId = $this->seedCarOwner($spId, $ptId, $this->seedPtUser());
        $ownerId2 = $this->seedCarOwner($spId2, $ptId, $this->seedPtUser(['name' => '李四']));

        Db::name('pt_car_car_owner')->insert([
            'smartpark_id'  => $spId,
            'car_id'         => $carId,
            'car_owner_id'   => $ownerId,
        ]);
        Db::name('pt_car_car_owner')->insert([
            'smartpark_id'  => $spId2,
            'car_id'         => $carId,
            'car_owner_id'   => $ownerId2,
        ]);

        // 通过 Request 注入 smartpark_id 参数 → pivot 过滤
        $mockReq = new class extends Request {
            public function param($name = '', $default = null)
            {
                if ($name === 'smartpark_id/d') return $GLOBALS['__test_smartpark_id'];
                return $default;
            }
        };
        Request::setInstance($mockReq);
        $GLOBALS['__test_smartpark_id'] = $spId;

        try {
            $car = Car::get($carId);
            $list = $car->car_owner_list;
            $this->assertCount(1, $list);
            $this->assertSame((string) $ownerId, (string) $list[0]['id']);
        } finally {
            Request::setInstance(null);
            unset($GLOBALS['__test_smartpark_id']);
        }
    }

    // ====== 6. 多层嵌套 with ======

    public function testNestedEagerLoad()
    {
        $spId = $this->seedSmartpark();
        $ptId = $this->seedParkinglot($spId);
        $carId = $this->seedCar($spId, $ptId);

        // 创建 2 条停车记录
        CarParking::create(['smartpark_id' => $spId, 'parkinglot_id' => $ptId, 'car_id' => $carId]);
        CarParking::create(['smartpark_id' => $spId, 'parkinglot_id' => $ptId, 'car_id' => $carId]);

        // 多层嵌套 with：smartpark_info + fixcar_list.smartpark_info + fixcar_list.parkinglot_info + car_owner_list
        $car = (new Car())->useWithFull()->getQuery()->find($carId);

        $this->assertSame('示范园区', $car['smartpark_info']['name']);
        $this->assertCount(2, $car['fixcar_list']);
        // 嵌套层：每条 fixcar 都应该有自己的 smartpark_info 和 parkinglot_info
        $this->assertSame('示范园区', $car['fixcar_list'][0]['smartpark_info']['name']);
        $this->assertSame('P1', $car['fixcar_list'][0]['parkinglot_info']['name']);
    }

    // ====== 7. search_or AND+OR 混合搜索 ======

    public function testSearchOr()
    {
        $spId = $this->seedSmartpark();
        $ptId = $this->seedParkinglot($spId);

        // 4 辆车：
        // 1,2 在 sp1；3,4 在 sp2（不存在，但插入允许）；number 各不同
        $this->seedCar($spId, $ptId, ['number' => 'A1', 'last_smartpark_id' => 100]);
        $this->seedCar($spId, $ptId, ['number' => 'A2', 'last_smartpark_id' => 200]);
        $this->seedCar($spId, $ptId, ['number' => 'B1', 'last_smartpark_id' => 300]);
        $this->seedCar($spId, $ptId, ['number' => 'B2', 'last_smartpark_id' => 400]);

        // AND: smartpark_id = spId
        // OR: number='A1' OR last_smartpark_id=400
        //   → 命中 A1（条件1）+ B2（条件2）
        $count = 0;
        $m = new Car();
        $rows = $m->search_or(
            ['smartpark_id' => $spId],
            ['number' => 'A1', 'last_smartpark_id' => 400],
            'id desc',
            1, 100,
            $count
        );

        $this->assertSame(2, $count);
        $numbers = array_column($rows, 'number');
        sort($numbers);
        $this->assertSame(['A1', 'B2'], $numbers);
    }

    // ====== 8. BaseValidator 7 个自定义规则 ======

    public function testSometimesRule()
    {
        $v = new CarValidator();

        // mobile 不存在 → 跳过验证 → 通过
        $this->assertTrue($v->scene('add')->check([
            'number' => 'A1', 'smartpark_id' => 1, 'parkinglot_id' => 1,
        ]));

        // mobile 存在但格式错 → 失败
        $this->assertFalse($v->scene('add')->check([
            'number' => 'A1', 'smartpark_id' => 1, 'parkinglot_id' => 1,
            'mobile' => 'abc',
        ]));

        // mobile 存在且格式对 → 通过
        $this->assertTrue($v->scene('add')->check([
            'number' => 'A1', 'smartpark_id' => 1, 'parkinglot_id' => 1,
            'mobile' => '13800138000',
        ]));
    }

    public function testConflictRule()
    {
        $v = new CarValidator();

        // email 存在 + mobile 也存在 → 冲突 → 失败
        $this->assertFalse($v->scene('add')->check([
            'number' => 'A1', 'smartpark_id' => 1, 'parkinglot_id' => 1,
            'email' => 'a@b.com', 'mobile' => '13800138000',
        ]));

        // email 存在但 mobile/name 都不存在 → 通过（nick_name 配合 r_with）
        $this->assertTrue($v->scene('add')->check([
            'number' => 'A1', 'smartpark_id' => 1, 'parkinglot_id' => 1,
            'email' => 'a@b.com', 'nick_name' => 'mynick',
        ]));
    }

    public function testRIfRule()
    {
        $v = new CarValidator();

        // contact_type=phone 且 contact_value 非空 → 通过
        $this->assertTrue($v->scene('add')->check([
            'number' => 'A1', 'smartpark_id' => 1, 'parkinglot_id' => 1,
            'contact_type' => 'phone', 'contact_value' => '13800138000',
        ]));

        // contact_type=phone 但 contact_value 空 → 失败
        $this->assertFalse($v->scene('add')->check([
            'number' => 'A1', 'smartpark_id' => 1, 'parkinglot_id' => 1,
            'contact_type' => 'phone', 'contact_value' => '',
        ]));

        // contact_type=email（非 phone/sms）→ contact_value 不必填 → 通过
        $this->assertTrue($v->scene('add')->check([
            'number' => 'A1', 'smartpark_id' => 1, 'parkinglot_id' => 1,
            'contact_type' => 'email',
        ]));
    }

    public function testRWithRule()
    {
        $v = new CarValidator();

        // name 出现 → nick_name 必填
        $this->assertFalse($v->scene('add')->check([
            'number' => 'A1', 'smartpark_id' => 1, 'parkinglot_id' => 1,
            'name' => 'car',
        ]));

        // name 出现且 nick_name 出现 → 通过
        $this->assertTrue($v->scene('add')->check([
            'number' => 'A1', 'smartpark_id' => 1, 'parkinglot_id' => 1,
            'name' => 'car', 'nick_name' => 'mycar',
        ]));
    }

    public function testRWithAllRule()
    {
        $v = new BaseValidatorHelper();
        $v->mergeRule([
            'target' => 'r_with_all:a,b',
        ]);

        // a 和 b 都出现 → target 必填
        $this->assertFalse($v->check(['a' => 1, 'b' => 1]));

        // a 和 b 都出现且 target 也出现 → 通过
        $this->assertTrue($v->check(['a' => 1, 'b' => 1, 'target' => 'x']));

        // 只 a 出现（b 不出现）→ target 不必填 → 通过
        $this->assertTrue($v->check(['a' => 1]));
    }

    public function testRWithoutRule()
    {
        $v = new BaseValidatorHelper();
        $v->mergeRule([
            'target' => 'r_without:a,b',
        ]);

        // a 不出现（任一不出现）→ target 必填；这里 target 空 → 失败
        $this->assertFalse($v->check(['b' => 1]));

        // a 不出现 + target 非空 → 通过
        $this->assertTrue($v->check(['b' => 1, 'target' => 'x']));
    }

    public function testRWithoutAllRule()
    {
        $v = new BaseValidatorHelper();
        $v->mergeRule([
            'target' => 'r_without_all:a,b',
        ]);

        // a 和 b 都不出现 → target 必填；空 → 失败
        $this->assertFalse($v->check([]));

        // a 和 b 都不出现 + target → 通过
        $this->assertTrue($v->check(['target' => 'x']));

        // a 出现 → target 不必填 → 通过
        $this->assertTrue($v->check(['a' => 1]));
    }

    // ====== 9. validateData 错误处理 ======

    public function testValidateDataConvertsBrokenRuleToException()
    {
        $spId = $this->seedSmartpark();
        $ptId = $this->seedParkinglot($spId);

        // 故意写错规则：require|integer|egt:0 拼成 'require|integeregt:0'
        // 原 TP 5.0.24 会触发 PHP Warning 然后静默通过
        // 我们的 BaseModel::validateData 应转成 ValidateException
        // 用数组形式绕过 Loader 解析（直接传入 rule/msg）
        $brokenCar = new class extends Car {
            protected $validate = [
                'rule' => ['number' => 'require|integeregt:0'],
                'msg'  => [],
            ];
        };

        $this->expectException(\think\exception\ValidateException::class);
        $brokenCar::create([
            'smartpark_id'  => $spId,
            'parkinglot_id' => $ptId,
            'number'        => 'A1',
        ]);
    }
}

/**
 * 测试辅助：可临时合并规则的 BaseValidator 子类
 */
class BaseValidatorHelper extends \app\parkinglot\validate\BaseValidator
{
    public function mergeRule(array $rule)
    {
        $this->rule = array_merge($this->rule, $rule);
    }
}
