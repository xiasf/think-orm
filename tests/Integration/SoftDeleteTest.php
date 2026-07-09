<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use ThinkOrm\Tests\Fixture\SoftUser;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class SoftDeleteTest extends IntegrationTestCase
{
    public function testDeleteSetsDeleteTime()
    {
        $id = $this->seedUser(['name' => 'sd']);
        $user = SoftUser::get($id);
        $user->delete();

        // 物理记录仍在，delete_time 不为 NULL
        $row = Db::name('users')->where('id', $id)->find();
        $this->assertNotNull($row);
        $this->assertNotEmpty($row['delete_time']);
    }

    public function testSoftDeletedExcludedFromQuery()
    {
        $id1 = $this->seedUser(['name' => 'keep']);
        $id2 = $this->seedUser(['name' => 'gone']);

        SoftUser::destroy($id2);

        $all = SoftUser::all();
        $ids = [];
        foreach ($all as $m) {
            $ids[] = (int) $m->id;
        }
        $this->assertNotContains($id2, $ids);
        $this->assertContains($id1, $ids);
    }

    public function testTrashedIncludesDeleted()
    {
        $id = $this->seedUser(['name' => 'gone']);
        SoftUser::destroy($id);

        $withTrashed = SoftUser::withTrashed()->select();
        $found = false;
        foreach ($withTrashed as $m) {
            if ($m->id == $id) { $found = true; break; }
        }
        $this->assertTrue($found, 'withTrashed 应包含软删记录');
    }

    public function testOnlyTrashed()
    {
        $id1 = $this->seedUser(['name' => 'keep']);
        $id2 = $this->seedUser(['name' => 'gone']);
        SoftUser::destroy($id2);

        $onlyTrashed = SoftUser::onlyTrashed()->select();
        $this->assertCount(1, $onlyTrashed);
        $this->assertSame((string) $id2, (string) $onlyTrashed[0]->id);
    }

    public function testRestore()
    {
        $id = $this->seedUser(['name' => 'r']);
        $user = SoftUser::get($id);
        $user->delete();

        $trashed = SoftUser::onlyTrashed()->find();
        $this->assertNotNull($trashed);
        $trashed->restore();
        $this->assertNotNull(SoftUser::get($id));
    }

    /**
     * 物理删除（force=true）应真删，不留 delete_time
     * 覆盖 README 差异章节 #13 的物理删除 API 用法
     */
    public function testDeleteWithForceTruePhysicallyRemoves()
    {
        $id = $this->seedUser(['name' => 'phys']);
        $user = SoftUser::get($id);
        $user->delete(true);

        // 物理记录应不存在
        $row = Db::name('users')->where('id', $id)->find();
        $this->assertNull($row);
    }

    /**
     * destroy($ids, true) 批量物理删除
     */
    public function testDestroyWithForceTruePhysicallyRemoves()
    {
        $id1 = $this->seedUser(['name' => 'a']);
        $id2 = $this->seedUser(['name' => 'b']);
        SoftUser::destroy([$id1, $id2], true);

        $this->assertNull(Db::name('users')->where('id', $id1)->find());
        $this->assertNull(Db::name('users')->where('id', $id2)->find());
    }

    /**
     * ⚠️ force()->delete() 仍是软删（消除 Laravel 肌肉记忆混淆）
     * force 标志只影响 update，对 delete 无影响
     */
    public function testForceChainBeforeDeleteIsStillSoftDelete()
    {
        $id = $this->seedUser(['name' => 'x']);
        $user = SoftUser::get($id);

        // Laravel 风格的写法 —— 在本包里仍是软删！
        $user->force(true)->delete();

        // 物理记录仍在
        $row = Db::name('users')->where('id', $id)->find();
        $this->assertNotNull($row, 'force()->delete() 不应物理删除');
        $this->assertNotEmpty($row['delete_time'], '应是软删，delete_time 有值');
    }
}
