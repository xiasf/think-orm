<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use ThinkOrm\Tests\Fixture\MetaUser;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * @group integration
 *
 * JSON 字段测试：meta 字段为 LONGTEXT，通过 type=json 自动编解码
 */
class JsonFieldTest extends IntegrationTestCase
{
    public function testWriteJsonArray()
    {
        $meta = ['tags' => ['php', 'orm'], 'score' => 88];
        $id = Db::name('users')->insertGetId([
            'name' => 'j', 'email' => 'j@x', 'age' => 1,
            'meta' => json_encode($meta),
        ]);
        $raw = Db::name('users')->where('id', $id)->value('meta');
        $decoded = json_decode($raw, true);
        $this->assertSame(['php', 'orm'], $decoded['tags']);
        $this->assertSame(88, $decoded['score']);
    }

    public function testModelAutoEncodeJson()
    {
        $meta = ['color' => 'red', 'price' => 9.9];
        $user = MetaUser::create([
            'name' => 'm', 'email' => 'm@x', 'age' => 1, 'meta' => $meta,
        ]);
        $raw = Db::name('users')->where('id', $user->id)->value('meta');
        $decoded = json_decode($raw, true);
        $this->assertSame('red', $decoded['color']);

        // 读取时应自动解码
        $loaded = MetaUser::get($user->id);
        $this->assertIsArray($loaded->meta);
        $this->assertSame('red', $loaded->meta['color']);
    }

    public function testUpdateJson()
    {
        $id = Db::name('users')->insertGetId([
            'name' => 'u', 'email' => 'u@x', 'age' => 1,
            'meta' => json_encode(['k' => 'v1']),
        ]);
        Db::name('users')->where('id', $id)->update([
            'meta' => json_encode(['k' => 'v2']),
        ]);
        $raw = Db::name('users')->where('id', $id)->value('meta');
        $this->assertSame('v2', json_decode($raw, true)['k']);
    }

    public function testNullJson()
    {
        $id = Db::name('users')->insertGetId([
            'name' => 'u', 'email' => 'u@x', 'age' => 1, 'meta' => null,
        ]);
        $this->assertNull(Db::name('users')->where('id', $id)->value('meta'));
    }
}
