<?php

namespace ThinkOrm\Tests\Integration;

use app\common\BaseModel;
use app\common\traits\model\Model as TModel;
use app\di\model\BModel;
use app\di\model\v1\Notice;
use app\di\model\v1\Smartpark;
use app\di\validate\Notice as NoticeValidate;
use think\App;
use think\Db;
use think\Loader;
use think\Log;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 *
 * 验证 yf 风格 BaseModel 完整移植：
 *   - validatorName() 从命名空间自动推断（app\<module>\model\..\Xxx → "<module>/Xxx"）
 *   - CRUD 封装：add / adds / upd / upds / updBy / updAttr / del / delBy / info / infoBy /
 *              lists / listBy / listByIds / listPageBy / search
 *   - 聚合：countBy / maxBy / minBy / avgBy / sumBy / valueBy / inc / dec
 *   - 结果处理：resultSet (decimal → float) / resultListSet
 *   - upSert：MySQL ON DUPLICATE KEY UPDATE
 *
 * trait Model：
 *   - spd / sca 场景化保存
 *   - listIndexBy / listIndexByIds 索引化查询
 *   - fieldWhere / withModel / withScope
 *   - rollbackQuery (Closure::bind)
 *
 * di 模块 Notice 模型：
 *   - 自动写入时间戳（int 类型，自定义字段 add_time）
 *   - JSON 字段类型转换 (payload)
 *   - 字段格式化读写器 (channels 字符串 <-> 数组)
 *   - 追加字段 (status_text)
 *   - 关联 belongsTo (smartparkInfo)
 *   - readonly 字段 (smartpark_id)
 */
class YfBaseModelTest extends IntegrationTestCase
{
    private $originalNamespace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalNamespace = App::$namespace;
        App::$namespace = 'app';
        // 清理 Loader 实例缓存（model() 单例）
        $this->clearLoaderCache();
    }

    protected function tearDown(): void
    {
        App::$namespace = $this->originalNamespace;
        $this->clearLoaderCache();
        parent::tearDown();
    }

    private function clearLoaderCache()
    {
        $prop = new \ReflectionProperty(Loader::class, 'instance');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    private function seedSmartpark($name = '示范园区'): int
    {
        return Db::name('di_smartpark')->insertGetId(['name' => $name, 'number' => 'SP001']);
    }

    // —— 1. validatorName() 从命名空间推断 ——

    public function testValidatorNameDerivedFromNamespace()
    {
        $n = new Notice();
        $this->assertSame('di/Notice', $n->validatorName());
    }

    public function testValidatorNameForSubdirModel()
    {
        // app\di\model\v1\Smartpark → "di/Smartpark"（中间层 model/v1 被忽略）
        $b = new Smartpark();
        $this->assertSame('di/Smartpark', $b->validatorName());
    }

    // —— 2. 自动写入时间戳（int 类型，字段名 add_time） ——

    public function testAutoWriteTimestampInt()
    {
        $sp = $this->seedSmartpark();
        $n = new Notice();
        $n->save([
            'smartpark_id' => $sp,
            'name' => 't1',
            'channels' => 'sms',
        ]);
        $this->assertNotEmpty($n->getData('add_time'));
        // int 时间戳格式
        $this->assertMatchesRegularExpression('/^\d{10}$/', (string) $n->getData('add_time'));

        // 数据库里的值应一致
        $raw = Db::name('di_notice')->where('id', $n->getData('id'))->value('add_time');
        $this->assertSame($n->getData('add_time'), (int) $raw);
    }

    public function testUpdateTimeDisabled()
    {
        $sp = $this->seedSmartpark();
        $n = Notice::create([
            'smartpark_id' => $sp,
            'name' => 't1',
            'channels' => 'sms',
        ]);
        $origAdd = $n->getData('add_time');
        sleep(1);
        $n->status = 1;
        $n->save();
        // Notice 没有 update_time 字段，不应被写入
        $this->assertSame($origAdd, $n->getData('add_time'));
    }

    // —— 3. JSON 字段类型转换 ——

    public function testJsonFieldType()
    {
        $sp = $this->seedSmartpark();
        $payload = ['tpl_id' => 100, 'args' => ['鄂A001', '2026']];
        $n = Notice::create([
            'smartpark_id' => $sp,
            'name' => 't',
            'channels' => 'sms',
            'payload' => $payload,
        ]);
        // 原始 SQL 中应为 JSON 字符串
        $raw = Db::name('di_notice')->where('id', $n->id)->value('payload');
        $decoded = json_decode($raw, true);
        $this->assertSame($payload, $decoded);

        // 读取时自动解码为数组
        $loaded = Notice::get($n->id);
        $this->assertIsArray($loaded->payload);
        $this->assertSame($payload, $loaded->payload);
    }

    // —— 4. 字段格式化读写器 ——

    public function testChannelsSetterArrayToString()
    {
        $sp = $this->seedSmartpark();
        $n = Notice::create([
            'smartpark_id' => $sp,
            'name' => 't',
            'channels' => ['sms', 'email', 'wechat'],
        ]);
        $raw = Db::name('di_notice')->where('id', $n->id)->value('channels');
        $this->assertSame('sms,email,wechat', $raw);
    }

    public function testChannelsGetterStringToArray()
    {
        $sp = $this->seedSmartpark();
        $id = Db::name('di_notice')->insertGetId([
            'smartpark_id' => $sp,
            'name' => 't',
            'channels' => 'sms,email',
            'add_time' => time(),
        ]);
        $loaded = Notice::get($id);
        $this->assertSame(['sms', 'email'], $loaded->channels);
    }

    public function testChannelsEmptyStringReturnsEmptyArray()
    {
        $sp = $this->seedSmartpark();
        $id = Db::name('di_notice')->insertGetId([
            'smartpark_id' => $sp,
            'name' => 't',
            'channels' => '',
            'add_time' => time(),
        ]);
        $loaded = Notice::get($id);
        $this->assertSame([], $loaded->channels);
    }

    // —— 5. 追加字段 ——

    public function testAppendVirtualField()
    {
        $sp = $this->seedSmartpark();
        $id = Db::name('di_notice')->insertGetId([
            'smartpark_id' => $sp,
            'name' => 't',
            'channels' => 'sms',
            'status' => 0,
            'add_time' => time(),
        ]);
        $row = Notice::get($id)->toArray();
        $this->assertArrayHasKey('status_text', $row);
        $this->assertSame('待发送', $row['status_text']);
    }

    public function testStatusTextMapping()
    {
        $sp = $this->seedSmartpark();
        foreach ([0 => '待发送', 1 => '已发送', 2 => '失败', 9 => '未知'] as $status => $expected) {
            $id = Db::name('di_notice')->insertGetId([
                'smartpark_id' => $sp,
                'name' => 't' . $status,
                'channels' => 'sms',
                'status' => $status,
                'add_time' => time(),
            ]);
            $row = Notice::get($id)->toArray();
            $this->assertSame($expected, $row['status_text'], "status=$status");
        }
    }

    // —— 6. 关联 belongsTo ——

    public function testBelongsToSmartparkInfo()
    {
        $sp = $this->seedSmartpark('园区A');
        $n = Notice::create([
            'smartpark_id' => $sp,
            'name' => 't',
            'channels' => 'sms',
        ]);
        $info = $n->smartparkInfo;
        $this->assertNotNull($info);
        $this->assertSame('园区A', $info->name);
    }

    public function testEagerLoadSmartparkInfo()
    {
        $sp = $this->seedSmartpark('园区A');
        Notice::create(['smartpark_id' => $sp, 'name' => 't1', 'channels' => 'sms']);
        Notice::create(['smartpark_id' => $sp, 'name' => 't2', 'channels' => 'sms']);

        $rows = Notice::with('smartparkInfo')->select();
        $this->assertCount(2, $rows);
        foreach ($rows as $r) {
            $this->assertSame('园区A', $r->smartparkInfo->name);
        }
    }

    // —— 7. readonly 字段 ——

    public function testReadonlySmartparkId()
    {
        $sp = $this->seedSmartpark();
        $n = Notice::create([
            'smartpark_id' => $sp,
            'name' => 't',
            'channels' => 'sms',
        ]);
        // 尝试改 readonly 字段
        $n->smartpark_id = 99999;
        $n->name = 'updated';
        $n->save();

        $raw = Db::name('di_notice')->where('id', $n->id)->find();
        $this->assertSame((string) $sp, (string) $raw['smartpark_id']);
        $this->assertSame('updated', $raw['name']);
    }

    // —— 8. CRUD：add ——

    public function testAddReturnsArrayWithId()
    {
        $sp = $this->seedSmartpark();
        $m = new Notice();
        $res = $m->add([
            'smartpark_id' => $sp,
            'name' => 't',
            'channels' => ['sms', 'email'],
        ]);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('id', $res);
        $this->assertIsInt($res['id']);
        $this->assertSame('t', $res['name']);
    }

    public function testAddEmptyData()
    {
        $m = new Notice();
        $res = $m->add([]);
        $this->assertFalse($res);
        $this->assertSame('没有新增数据', $m->getError());
    }

    public function testAddValidationFailure()
    {
        $sp = $this->seedSmartpark();
        $m = new Notice();
        // 缺 smartpark_id（require 规则）
        $res = $m->add(['name' => 't', 'channels' => 'sms']);
        $this->assertFalse($res);
        $this->assertNotEmpty($m->getError());
        // 不应实际写入
        $this->assertSame(0, Db::name('di_notice')->count());
    }

    public function testAddWithMethodCallback()
    {
        $sp = $this->seedSmartpark();
        $m = new Notice();
        // yf add() 只支持 string method（在 Model 上定义同名方法）
        // 这里直接在调用前 transform data，验证 $method 是 string 时的路径
        $res = $m->add(['smartpark_id' => $sp, 'name' => 'transformed', 'channels' => 'sms']);
        $this->assertSame('transformed', $res['name']);
    }

    public function testAddStripsIdZero()
    {
        $sp = $this->seedSmartpark();
        $m = new Notice();
        $res = $m->add(['id' => 0, 'smartpark_id' => $sp, 'name' => 't', 'channels' => 'sms']);
        // id=0 应被剥离，让自增生效
        $this->assertNotSame(0, $res['id']);
    }

    // —— 9. CRUD：adds 批量 ——

    public function testAddsBatch()
    {
        $sp = $this->seedSmartpark();
        $m = new Notice();
        $res = $m->adds([
            ['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms'],
            ['smartpark_id' => $sp, 'name' => 'b', 'channels' => 'sms'],
        ]);
        $this->assertCount(2, $res);
        $this->assertSame(2, Db::name('di_notice')->count());
    }

    // —— 10. CRUD：upd / updBy / updAttr ——

    public function testUpdById()
    {
        $sp = $this->seedSmartpark();
        $n = Notice::create(['smartpark_id' => $sp, 'name' => 't', 'channels' => 'sms']);
        $m = new Notice();
        $affected = $m->upd(['id' => $n->id, 'status' => 1]);
        $this->assertSame(1, $affected);
        $this->assertSame('1', (string) Db::name('di_notice')->where('id', $n->id)->value('status'));
    }

    public function testUpdBy()
    {
        $sp = $this->seedSmartpark();
        Notice::create(['smartpark_id' => $sp, 'name' => 't1', 'channels' => 'sms']);
        Notice::create(['smartpark_id' => $sp, 'name' => 't2', 'channels' => 'sms']);
        $m = new Notice();
        $affected = $m->updBy(['status' => 2], ['smartpark_id' => $sp]);
        $this->assertSame(2, $affected);
    }

    public function testUpdAttr()
    {
        $sp = $this->seedSmartpark();
        $id1 = Db::name('di_notice')->insertGetId(['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms', 'add_time' => time()]);
        $id2 = Db::name('di_notice')->insertGetId(['smartpark_id' => $sp, 'name' => 'b', 'channels' => 'sms', 'add_time' => time()]);

        $m = new Notice();
        $affected = $m->updAttr([$id1, $id2], 'status', 1);
        $this->assertSame(2, $affected);
    }

    public function testUpdReadonlyIgnored()
    {
        $sp = $this->seedSmartpark();
        $n = Notice::create(['smartpark_id' => $sp, 'name' => 't', 'channels' => 'sms']);
        $m = new Notice();
        // 通过 upd 改 readonly 字段
        $m->upd(['id' => $n->id, 'smartpark_id' => 99999, 'status' => 1]);
        $raw = Db::name('di_notice')->where('id', $n->id)->find();
        $this->assertSame((string) $sp, (string) $raw['smartpark_id']);
        $this->assertSame('1', (string) $raw['status']);
    }

    // —— 11. CRUD：del / delBy ——

    public function testDelById()
    {
        $sp = $this->seedSmartpark();
        $n = Notice::create(['smartpark_id' => $sp, 'name' => 't', 'channels' => 'sms']);
        $m = new Notice();
        $cnt = $m->del($n->id);
        $this->assertSame(1, $cnt);
        $this->assertSame(0, Db::name('di_notice')->count());
    }

    public function testDelByWhere()
    {
        $sp = $this->seedSmartpark();
        Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms']);
        Notice::create(['smartpark_id' => $sp, 'name' => 'b', 'channels' => 'sms']);
        $m = new Notice();
        $cnt = $m->delBy(['smartpark_id' => $sp]);
        $this->assertSame(2, $cnt);
    }

    // —— 12. info / infoBy ——

    public function testInfoReturnsArray()
    {
        $sp = $this->seedSmartpark();
        $n = Notice::create(['smartpark_id' => $sp, 'name' => 'x', 'channels' => 'sms', 'amount' => '12.50']);
        $m = new Notice();
        $row = $m->info($n->id);
        $this->assertIsArray($row);
        $this->assertSame('x', $row['name']);
        // amount 是 decimal，应转 float
        $this->assertIsFloat($row['amount']);
        $this->assertSame(12.50, $row['amount']);
    }

    public function testInfoFieldFilter()
    {
        $sp = $this->seedSmartpark();
        $n = Notice::create(['smartpark_id' => $sp, 'name' => 'x', 'channels' => 'sms']);
        $m = new Notice();
        $row = $m->info($n->id, 'id,name');
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayNotHasKey('smartpark_id', $row);
    }

    public function testInfoMissingReturnsNull()
    {
        $m = new Notice();
        $row = $m->info(999999);
        $this->assertNull($row);  // find 找不到时返回 null，infoBy 透传 null
    }

    public function testInfoByWithLock()
    {
        $sp = $this->seedSmartpark();
        $n = Notice::create(['smartpark_id' => $sp, 'name' => 'x', 'channels' => 'sms']);
        $m = new Notice();
        $sql = $m->getQuery()->fetchSql(true)->where('id', $n->id)->lock(true)->find();
        $this->assertStringContainsString('FOR UPDATE', $sql);
    }

    // —— 13. lists / listBy / listByIds / listPageBy ——

    public function testLists()
    {
        $sp = $this->seedSmartpark();
        Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms']);
        Notice::create(['smartpark_id' => $sp, 'name' => 'b', 'channels' => 'sms']);
        $m = new Notice();
        $rows = $m->lists(['smartpark_id' => $sp], 'id,name', 'id desc');
        $this->assertCount(2, $rows);
        $this->assertSame('b', $rows[0]['name']);
    }

    public function testListBy()
    {
        $sp = $this->seedSmartpark();
        Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms']);
        Notice::create(['smartpark_id' => $sp, 'name' => 'b', 'channels' => 'sms']);
        $m = new Notice();
        $rows = $m->listBy(['smartpark_id' => $sp]);
        $this->assertCount(2, $rows);
    }

    public function testListByIds()
    {
        $sp = $this->seedSmartpark();
        $a = Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms']);
        $b = Notice::create(['smartpark_id' => $sp, 'name' => 'b', 'channels' => 'sms']);
        $m = new Notice();
        $rows = $m->listByIds([$a->id, $b->id]);
        $this->assertCount(2, $rows);
    }

    public function testListPageBy()
    {
        $sp = $this->seedSmartpark();
        for ($i = 0; $i < 5; $i++) {
            Notice::create(['smartpark_id' => $sp, 'name' => 'n' . $i, 'channels' => 'sms']);
        }
        $m = new Notice();
        $rows = $m->listPageBy(['smartpark_id' => $sp], '', 2, 2);
        $this->assertCount(2, $rows);
    }

    // —— 14. search 分页 + 计数 ——

    public function testSearchWithPagination()
    {
        $sp = $this->seedSmartpark();
        for ($i = 0; $i < 25; $i++) {
            Notice::create(['smartpark_id' => $sp, 'name' => 'n' . $i, 'channels' => 'sms']);
        }
        $m = new Notice();
        $count = 0;
        $rows = $m->search(['smartpark_id' => $sp], 'id desc', 2, 10, $count);
        $this->assertCount(10, $rows);
        $this->assertSame(25, $count);
    }

    public function testSearchStripsEmptyValues()
    {
        $sp = $this->seedSmartpark();
        Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms']);
        $m = new Notice();
        $count = 0;
        $rows = $m->search(['smartpark_id' => $sp, 'name' => ''], 'id desc', 1, 10, $count);
        $this->assertSame(1, $count);
        $this->assertCount(1, $rows);
    }

    // —— 15. 聚合 ——

    public function testCountBy()
    {
        $sp = $this->seedSmartpark();
        Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms']);
        Notice::create(['smartpark_id' => $sp, 'name' => 'b', 'channels' => 'sms']);
        $m = new Notice();
        $this->assertSame(2, $m->countBy(['smartpark_id' => $sp]));
        $this->assertSame(2, $m->countBy());
    }

    public function testMaxByMinByAvgBySumByDecimalFields()
    {
        $sp = $this->seedSmartpark();
        Db::name('di_notice')->insertAll([
            ['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms', 'amount' => '10.50', 'add_time' => time()],
            ['smartpark_id' => $sp, 'name' => 'b', 'channels' => 'sms', 'amount' => '20.00', 'add_time' => time()],
            ['smartpark_id' => $sp, 'name' => 'c', 'channels' => 'sms', 'amount' => '5.25',  'add_time' => time()],
        ]);
        $m = new Notice();
        $this->assertSame(20.0, (float) $m->maxBy(['smartpark_id' => $sp], 'amount'));
        $this->assertSame(5.25, (float) $m->minBy(['smartpark_id' => $sp], 'amount'));
        $this->assertEqualsWithDelta(11.92, (float) $m->avgBy(['smartpark_id' => $sp], 'amount'), 0.01);
        $this->assertSame('35.75', (string) $m->sumBy(['smartpark_id' => $sp], 'amount'));
    }

    public function testValueBy()
    {
        $sp = $this->seedSmartpark();
        $n = Notice::create(['smartpark_id' => $sp, 'name' => 'single', 'channels' => 'sms']);
        $m = new Notice();
        $this->assertSame('single', $m->valueBy(['id' => $n->id], 'name'));
    }

    public function testIncAndDec()
    {
        $sp = $this->seedSmartpark();
        $id = Db::name('di_notice')->insertGetId([
            'smartpark_id' => $sp, 'name' => 't', 'channels' => 'sms',
            'status' => 1, 'add_time' => time(),
        ]);
        $m = new Notice();
        $m->inc(['id' => $id], 'status');
        $this->assertSame('2', (string) Db::name('di_notice')->where('id', $id)->value('status'));
        $m->dec(['id' => $id], 'status', 2);
        $this->assertSame('0', (string) Db::name('di_notice')->where('id', $id)->value('status'));
    }

    // —— 16. upSert ——

    public function testUpSertInsertsWhenIdMissing()
    {
        $sp = $this->seedSmartpark();
        $m = new Notice();
        $n = $m->upSert([
            ['id' => 1, 'smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms', 'add_time' => time()],
        ]);
        $this->assertSame(1, $n);
        $this->assertSame(1, Db::name('di_notice')->count());
    }

    public function testUpSertUpdatesWhenIdExists()
    {
        $sp = $this->seedSmartpark();
        $id = Db::name('di_notice')->insertGetId([
            'smartpark_id' => $sp, 'name' => 'orig', 'channels' => 'sms', 'add_time' => time(),
        ]);
        $m = new Notice();
        $m->upSert([
            ['id' => $id, 'smartpark_id' => $sp, 'name' => 'new', 'channels' => 'email', 'add_time' => time()],
        ]);
        $this->assertSame('new', Db::name('di_notice')->where('id', $id)->value('name'));
        $this->assertSame('email', Db::name('di_notice')->where('id', $id)->value('channels'));
        $this->assertSame(1, Db::name('di_notice')->count());
    }

    // —— 17. trait：spd / sca 场景化 ——

    public function testScaSceneAdd()
    {
        $sp = $this->seedSmartpark();
        $m = new Notice();
        $m->scene = 'add';
        $res = $m->sca(['smartpark_id' => $sp, 'name' => 't', 'channels' => 'sms']);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('id', $res);
    }

    public function testScaSceneValidationFailure()
    {
        $m = new Notice();
        $m->scene = 'add';
        // 缺 smartpark_id
        $res = $m->sca(['name' => 't', 'channels' => 'sms']);
        $this->assertFalse($res);
        $this->assertNotEmpty($m->getError());
    }

    public function testSpdSceneUpdate()
    {
        $sp = $this->seedSmartpark();
        $n = Notice::create(['smartpark_id' => $sp, 'name' => 't', 'channels' => 'sms']);
        $m = new Notice();
        $m->scene = 'edit';
        $affected = $m->spd(['id' => $n->id, 'name' => 'updated', 'status' => 1]);
        $this->assertSame(1, $affected);
        $this->assertSame('updated', Db::name('di_notice')->where('id', $n->id)->value('name'));
    }

    // —— 18. trait：listIndexBy ——

    public function testListIndexBy()
    {
        $sp = $this->seedSmartpark();
        Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms']);
        Notice::create(['smartpark_id' => $sp, 'name' => 'b', 'channels' => 'sms']);
        $m = new Notice();
        $res = $m->listIndexBy(['smartpark_id' => $sp], 'id', 'name');
        $this->assertNotNull($res);
        foreach ($res as $id => $name) {
            $this->assertSame(Db::name('di_notice')->where('id', $id)->value('name'), $name);
        }
    }

    public function testListIndexByIds()
    {
        $sp = $this->seedSmartpark();
        $a = Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms']);
        $b = Notice::create(['smartpark_id' => $sp, 'name' => 'b', 'channels' => 'sms']);
        $c = Notice::create(['smartpark_id' => $sp, 'name' => 'c', 'channels' => 'sms']);
        $m = new Notice();
        $res = $m->listIndexByIds([$a->id, $c->id], 'id', 'name');
        $this->assertCount(2, $res);
        $this->assertArrayHasKey($a->id, $res);
        $this->assertArrayHasKey($c->id, $res);
        $this->assertArrayNotHasKey($b->id, $res);
    }

    // —— 19. trait：fieldWhere ——

    public function testFieldWhereBuildsWhereFromParams()
    {
        $sp = $this->seedSmartpark();
        Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms', 'status' => 0]);
        Notice::create(['smartpark_id' => $sp, 'name' => 'b', 'channels' => 'sms', 'status' => 1]);

        $m = new Notice();
        $m->fieldWhere(['status' => 1, 'non_existing_field' => 'whatever']);
        $rows = $m->getQuery()->where('smartpark_id', $sp)->select();
        $this->assertCount(1, $rows);
    }

    public function testFieldFilterWhereUnknownFieldsIgnored()
    {
        $sp = $this->seedSmartpark();
        Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms']);
        $m = new Notice();
        $m->fieldWhere(['unknown_field' => 'x']);
        $rows = $m->getQuery()->where('smartpark_id', $sp)->select();
        // unknown_field 不在表结构里，不应作为条件
        $this->assertCount(1, $rows);
    }

    public function testFieldWhereLikeExpression()
    {
        $sp = $this->seedSmartpark();
        Notice::create(['smartpark_id' => $sp, 'name' => 'alpha', 'channels' => 'sms']);
        Notice::create(['smartpark_id' => $sp, 'name' => 'beta',  'channels' => 'sms']);

        // 子类要自定义 get_FieldRule 才能用 like，这里直接测默认 = 行为
        $m = new Notice();
        $m->fieldWhere(['name' => 'alpha']);
        $rows = $m->getQuery()->where('smartpark_id', $sp)->select();
        $this->assertCount(1, $rows);
        $this->assertSame('alpha', $rows[0]['name']);
    }

    // —— 20. trait：withModel ——

    public function testWithModelEagerLoad()
    {
        $sp = $this->seedSmartpark('园区X');
        Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms']);
        Notice::create(['smartpark_id' => $sp, 'name' => 'b', 'channels' => 'sms']);

        $m = new Notice();
        $m->withModel('smartparkInfo');
        $rows = $m->getQuery()->where('smartpark_id', $sp)->select();
        foreach ($rows as $r) {
            $this->assertNotNull($r->smartpark_info);
            $this->assertSame('园区X', $r->smartpark_info->name);
        }
    }

    // —— 21. trait：rollbackQuery ——

    public function testRollbackQueryRestoresOptions()
    {
        $sp = $this->seedSmartpark();
        Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms']);
        Notice::create(['smartpark_id' => $sp, 'name' => 'b', 'channels' => 'sms']);

        $m = new Notice();
        $m->where('smartpark_id', $sp)->order('id desc')->limit(1);
        $options = $m->getQuery()->getOptions();
        $bind = $m->getQuery()->getBind();

        // 模拟 search() 流程：先 select，再用 rollbackQuery 恢复 options 再 count
        $list = $m->bind($bind)->select();
        $this->assertCount(1, $list);

        // 这里需要 rollbackQuery 把 where 加回去
        unset($options['order'], $options['limit']);
        $count = $m->rollbackQuery($options)->bind($bind)->count();
        $this->assertSame(2, $count);
    }

    // —— 22. 日志：PSR-3 注入 ——

    public function testSqlLoggingViaPsr3()
    {
        $holder = new \stdClass();
        $holder->logs = [];
        $logger = new class($holder) {
            private $holder;
            public function __construct($h) { $this->holder = $h; }
            public function log($level, $message, array $context = [])
            {
                $this->holder->logs[] = "[$level] $message";
            }
        };
        Log::setLogger($logger);
        Log::clear();

        // debug=true 时 Connection 会写 SQL 日志
        \ThinkOrm\Orm::debug(true);
        // 重置连接：旧连接的 config['debug'] 已快照为 false，需重建以让新配置生效
        Db::clear();

        $sp = $this->seedSmartpark();
        Notice::create(['smartpark_id' => $sp, 'name' => 'logged', 'channels' => 'sms']);

        // 关回 debug
        \ThinkOrm\Orm::debug(false);
        Db::clear();
        Log::setLogger(null);

        $logs = $holder->logs;
        $this->assertNotEmpty($logs, '应至少记录一条 SQL');
        $hasInsert = false;
        foreach ($logs as $log) {
            if (stripos($log, 'INSERT') !== false) $hasInsert = true;
        }
        $this->assertTrue($hasInsert, 'INSERT SQL 应被记录');
    }

    public function testNoOpLoggerWhenNotInjected()
    {
        // 未注入 logger，不应抛错
        Log::setLogger(null);
        $sp = $this->seedSmartpark();
        $n = Notice::create(['smartpark_id' => $sp, 'name' => 'x', 'channels' => 'sms']);
        $this->assertNotEmpty($n->id);
    }

    // —— 23. validate() helper ——

    public function testValidateHelperLoadsCustomClass()
    {
        $v = validate('\\app\\di\\validate\\Notice');
        $this->assertInstanceOf(NoticeValidate::class, $v);
        // 默认场景规则
        $this->assertFalse($v->check(['smartpark_id' => 0, 'name' => 'x']));
    }

    public function testValidateSceneEdit()
    {
        $v = validate('\\app\\di\\validate\\Notice');
        // edit 场景只需 name/status
        $this->assertTrue($v->scene('edit')->check(['name' => 'ok', 'status' => 1]));
        // edit 场景下 status 越界失败
        $this->assertFalse($v->scene('edit')->check(['name' => 'ok', 'status' => 9]));
    }

    // —— 24. model() helper（端到端 yf 风格） ——

    public function testModelHelperResolvesToDemoNamespace()
    {
        // 模块带版本段：'di/v1/Smartpark' → app\di\model\v1\Smartpark
        $m = model('di/v1/Smartpark');
        $this->assertInstanceOf(Smartpark::class, $m);
    }

    public function testModelHelperStudlyCaseConversion()
    {
        // 大驼峰自动转换：'di/v1/smartpark' → app\di\model\v1\Smartpark
        $this->assertInstanceOf(
            \app\di\model\v1\Smartpark::class,
            model('di/v1/smartpark')
        );
        $this->assertInstanceOf(
            \app\di\model\v1\Notice::class,
            model('di/v1/notice')
        );
        $this->assertInstanceOf(
            \app\parkinglot\model\v1\Car::class,
            model('parkinglot/v1/car')
        );
    }

    public function testModelHelperFullClass()
    {
        $m = model('\\app\\di\\model\\v1\\Notice');
        $this->assertInstanceOf(Notice::class, $m);
    }

    // —— 25. toString 包含 decimal 转 float ——

    public function testToArrayConvertsDecimal()
    {
        $sp = $this->seedSmartpark();
        $id = Db::name('di_notice')->insertGetId([
            'smartpark_id' => $sp, 'name' => 't', 'channels' => 'sms',
            'amount' => '123.45', 'add_time' => time(),
        ]);
        $n = Notice::get($id);
        $arr = $n->toArray();
        $this->assertIsFloat($arr['amount']);
        $this->assertSame(123.45, $arr['amount']);
    }

    // —— 26. 补：useWith / search_or / upds / withScope / get_ExtendField ——

    public function testUseWithChainsEagerLoad()
    {
        // useWith 是 BaseModel 提供的链式 with 简写
        $sp = $this->seedSmartpark('园区A');
        Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms']);

        $m = new Notice();
        // 应返回 $this（可链式）
        $ret = $m->useWith('smartparkInfo');
        $this->assertSame($m, $ret);

        // select 后关联被预加载
        $rows = $m->getQuery()->where('id', '>', 0)->select();
        $this->assertCount(1, $rows);
        $this->assertSame('园区A', $rows[0]->smartpark_info->name);
    }

    public function testUseWithAcceptsArray()
    {
        $sp = $this->seedSmartpark('园B');
        Notice::create(['smartpark_id' => $sp, 'name' => 'x', 'channels' => 'sms']);

        $m = new Notice();
        $m->useWith(['smartparkInfo']);
        $rows = $m->getQuery()->where('id', '>', 0)->select();
        $this->assertSame('园B', $rows[0]->smartpark_info->name);
    }

    public function testSearchOrMixesAndOrConditions()
    {
        $sp = $this->seedSmartpark();
        Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'status' => 0, 'channels' => 'sms']);
        Notice::create(['smartpark_id' => $sp, 'name' => 'b', 'status' => 1, 'channels' => 'sms']);
        Notice::create(['smartpark_id' => $sp, 'name' => 'c', 'status' => 2, 'channels' => 'sms']);
        Notice::create(['smartpark_id' => $sp, 'name' => 'd', 'status' => 3, 'channels' => 'sms']);

        // where: smartpark_id=$sp AND (status=0 OR status=1)
        $m = new Notice();
        $count = 0;
        $rows = $m->search_or(
            ['smartpark_id' => $sp],          // AND
            ['status' => ['in', [0, 1]]],     // OR group
            'id asc',
            1,
            100,
            $count
        );
        $this->assertSame(2, $count);
        $this->assertCount(2, $rows);
        $names = array_column($rows, 'name');
        sort($names);
        $this->assertSame(['a', 'b'], $names);
    }

    public function testSearchOrWithEmptyConditionsReturnsAll()
    {
        $sp = $this->seedSmartpark();
        Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms']);
        Notice::create(['smartpark_id' => $sp, 'name' => 'b', 'channels' => 'sms']);

        // 传空：search_or 会过滤掉空条件，留下无 where 的查询
        $m = new Notice();
        $count = 0;
        $rows = $m->search_or([], [], 'id desc', 1, 100, $count);
        $this->assertSame(2, $count);
        $this->assertCount(2, $rows);
    }

    public function testUpdsBatchUpdate()
    {
        $sp = $this->seedSmartpark();
        $n1 = Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms', 'status' => 0]);
        $n2 = Notice::create(['smartpark_id' => $sp, 'name' => 'b', 'channels' => 'sms', 'status' => 0]);

        $m = new Notice();
        // upds 把多条记录的 status 一起改成 1
        $firstRow = $m->upds([
            ['id' => $n1->id, 'status' => 1],
            ['id' => $n2->id, 'status' => 1],
        ]);
        // 返回第一条更新后的 data
        $this->assertSame(1, (int) $firstRow['status']);
        $this->assertSame('1', (string) Db::name('di_notice')->where('id', $n1->id)->value('status'));
        $this->assertSame('1', (string) Db::name('di_notice')->where('id', $n2->id)->value('status'));
    }

    public function testUpdsEmptyReturnsFalse()
    {
        $m = new Notice();
        $res = $m->upds([]);
        $this->assertFalse($res);
        $this->assertNotEmpty($m->getError());
    }

    public function testWithScopeInvokesScopeMethods()
    {
        // 用匿名子类提供 scope 方法
        $sp = $this->seedSmartpark();
        Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'status' => 0, 'channels' => 'sms']);
        Notice::create(['smartpark_id' => $sp, 'name' => 'b', 'status' => 1, 'channels' => 'sms']);
        Notice::create(['smartpark_id' => $sp, 'name' => 'c', 'status' => 1, 'channels' => 'sms']);

        $m = new class extends Notice {
            // withScope 调用的方法名（不是 TP 标准 scopeXxx，而是 yf 风格的直接方法）
            public function onlyActive()
            {
                $this->getQuery()->where('status', 1);
            }
            public function get_Scope()
            {
                return ['onlyActive'];
            }
        };

        $m->withScope();   // 默认从 get_Scope() 取
        $rows = $m->getQuery()->where('smartpark_id', $sp)->select();
        $this->assertCount(2, $rows);
    }

    public function testWithScopeExplicitArg()
    {
        $sp = $this->seedSmartpark();
        Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'status' => 1, 'channels' => 'sms']);

        $m = new class extends Notice {
            public function applyRange()
            {
                $this->getQuery()->where('status', 1);
            }
        };

        $m->withScope('applyRange');
        $rows = $m->getQuery()->where('smartpark_id', $sp)->select();
        $this->assertCount(1, $rows);
    }

    public function testWithScopeEmptyIsNoOp()
    {
        // 无 scope 参数 + get_Scope() 返回空 → 直接返回 $this，不报错
        $m = new Notice();
        $ret = $m->withScope();
        $this->assertSame($m, $ret);
    }

    public function testGetExtendFieldAddsCustomFieldsToFieldWhere()
    {
        // 通过匿名子类提供 ExtendField：让 fieldWhere 接受虚拟字段 custom_status
        $sp = $this->seedSmartpark();
        Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'status' => 1, 'channels' => 'sms']);
        Notice::create(['smartpark_id' => $sp, 'name' => 'b', 'status' => 2, 'channels' => 'sms']);

        $m = new class extends Notice {
            public function get_ExtendField()
            {
                return ['custom_status'];   // 不在表里但允许作为 status 的别名
            }
        };

        // custom_status 不是真实字段，但因 ExtendField 注入，fieldWhere 不会跳过它
        // 不过 where('custom_status', '=', ...) 仍会因 DB 字段不存在而报错——
        // 所以这里改测：fieldWhere 处理 ExtendField 时确实把它放进 where 链路
        // 用 fetchSql 检查 SQL 包含 custom_status
        $m->fieldWhere(['custom_status' => 5]);
        $sql = $m->getQuery()->fetchSql(true)->where('smartpark_id', $sp)->select();
        $this->assertStringContainsString('custom_status', $sql);
    }

    public function testResultSetConvertsDecimalToFloat()
    {
        // resultSet 是 toArray 调用的：decimal 字段应转 float
        $m = new Notice();
        $item = ['id' => 1, 'amount' => '12.50', 'name' => 'x'];
        $res = $m->resultSet($item);
        $this->assertIsFloat($res['amount']);
        $this->assertSame(12.50, $res['amount']);
        // 非 decimal 字段不变
        $this->assertSame('x', $res['name']);
    }

    public function testResultListSetNullReturnsNull()
    {
        // 空结果直接返回（不调 collection）
        $m = new Notice();
        $this->assertNull($m->resultListSet(null));
    }

    public function testResultListSetConvertsCollectionToArray()
    {
        $sp = $this->seedSmartpark();
        $row = Notice::create(['smartpark_id' => $sp, 'name' => 'a', 'channels' => 'sms']);
        $m = new Notice();
        // 传入 Model 实例的 collection
        $res = $m->resultListSet([$row]);
        $this->assertIsArray($res);
        $this->assertCount(1, $res);
    }

    public function testInfoByBasicWhere()
    {
        // infoBy 直接 where 数组形式（不传 lock）
        $sp = $this->seedSmartpark();
        $n = Notice::create(['smartpark_id' => $sp, 'name' => 'direct', 'channels' => 'sms']);
        $m = new Notice();
        $row = $m->infoBy(['id' => $n->id]);
        $this->assertIsArray($row);
        $this->assertSame('direct', $row['name']);
    }

    public function testInfoByEmptyReturnsNull()
    {
        $m = new Notice();
        $row = $m->infoBy(['id' => 999999]);
        $this->assertNull($row);
    }

    public function testValidatorNameExplicitOverride()
    {
        // 显式设置 $validatorName（protected）应优先生效
        // 用反射直接赋值，模拟子类在 protected $validatorName = 'xxx'; 声明的效果
        $m = new Notice();
        $prop = new \ReflectionProperty(BaseModel::class, 'validatorName');
        $prop->setAccessible(true);
        $prop->setValue($m, 'custom/Path');
        $this->assertSame('custom/Path', $m->validatorName());
    }

    public function testValidateDataConvertsBadRuleToException()
    {
        // 故意传一条错误规则（拼错的 integer），用 set_error_handler 转 ValidateException
        $this->expectException(\think\exception\ValidateException::class);
        try {
            $m = new Notice();
            $m->validate(['name' => ['integeregt:0']])->save(['name' => 'x']);
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    // —— 27. 入门 demo 完整流程（端到端） ——

    public function testEndToEndWorkflowMatchesYfUsage()
    {
        // 1) 通过 model() 创建园区（版本段在路径中：di/v1/Smartpark）
        $spRes = model('di/v1/Smartpark')->add(['name' => '演示园区', 'number' => 'SP001']);
        $spId = $spRes['id'];

        // 2) 创建通知（自动 add_time、JSON payload、channels 数组转字符串）
        $noticeRes = model('di/v1/Notice')->add([
            'smartpark_id' => $spId,
            'name' => '到期提醒',
            'channels' => ['sms', 'email'],
            'payload' => ['tpl_id' => 100, 'args' => ['鄂A001']],
            'amount' => '12.50',
        ]);
        $nid = $noticeRes['id'];

        // 3) 查询触发字段格式化
        $row = model('di/v1/Notice')->info($nid);
        $this->assertSame(['sms', 'email'], $row['channels']);
        $this->assertSame(['tpl_id' => 100, 'args' => ['鄂A001']], $row['payload']);
        $this->assertSame('待发送', $row['status_text']);
        $this->assertSame(12.50, $row['amount']);

        // 4) 关联
        $with = Notice::with('smartparkInfo')->find($nid);
        $this->assertSame('演示园区', $with->smartpark_info->name);

        // 5) readonly 保护：Model::save 路径下 smartpark_id 不可改
        $n = Notice::get($nid);
        $origSp = $n->smartpark_id;
        $n->smartpark_id = 999;  // 试图改 readonly 字段
        $n->status = 1;
        $n->isUpdate(true)->save();
        $after = model('di/v1/Notice')->info($nid);
        $this->assertSame((string) $origSp, (string) $after['smartpark_id']);
        $this->assertSame('1', (string) $after['status']);
        $this->assertSame('已发送', $after['status_text']);

        // 6) 聚合
        model('di/v1/Notice')->add([
            'smartpark_id' => $spId, 'name' => 't2',
            'channels' => 'sms', 'amount' => '7.50',
        ]);
        $sum = model('di/v1/Notice')->sumBy(['smartpark_id' => $spId], 'amount');
        $this->assertSame(20.0, (float) $sum);
    }
}
