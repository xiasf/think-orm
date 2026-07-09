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
}
