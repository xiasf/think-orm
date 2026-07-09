<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use think\exception\ValidateException;
use ThinkOrm\Tests\Fixture\ValidUser;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 */
class ModelValidationTest extends IntegrationTestCase
{
    public function testValidDataPasses()
    {
        $u = ValidUser::create(['name' => 'ok', 'email' => 'ok@x.com', 'age' => 10]);
        $this->assertNotEmpty($u->id);
    }

    public function testMissingRequiredFails()
    {
        $this->expectException(ValidateException::class);
        ValidUser::create(['email' => 'a@x.com']); // 缺 name
    }

    public function testInvalidEmailFails()
    {
        try {
            ValidUser::create(['name' => 'ok', 'email' => 'not-email', 'age' => 1]);
            $this->fail('expected ValidateException');
        } catch (ValidateException $e) {
            $errors = $e->getError();
            $this->assertNotEmpty($errors);
            // 不应实际写入
            $this->assertSame(0, Db::name('users')->count());
        }
    }

    public function testAgeIntegerRule()
    {
        $this->expectException(ValidateException::class);
        ValidUser::create(['name' => 'ok', 'email' => 'ok@x.com', 'age' => 'abc']);
    }
}
