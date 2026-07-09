<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use ThinkOrm\Tests\Fixture\TimeUser;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class ModelAutoTimestampTest extends IntegrationTestCase
{
    public function testCreateSetsCreateAndUpdate()
    {
        $u = TimeUser::create(['name' => 't', 'email' => 't@x', 'age' => 1]);
        $this->assertNotEmpty($u->create_time);
        $this->assertNotEmpty($u->update_time);

        $row = Db::name('users')->where('id', $u->id)->find();
        $this->assertNotEmpty($row['create_time']);
        $this->assertNotEmpty($row['update_time']);
    }

    public function testUpdateOnlySetsUpdate()
    {
        $u = TimeUser::create(['name' => 't', 'email' => 't@x', 'age' => 1]);
        $originalUpdate = $u->update_time;
        sleep(1);
        $u->age = 50;
        $u->save();
        $this->assertNotSame($originalUpdate, $u->update_time);
    }

    public function testDatetimeFormat()
    {
        $u = TimeUser::create(['name' => 't', 'email' => 't@x', 'age' => 1]);
        // Y-m-d H:i:s 格式
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $u->create_time);
    }
}
