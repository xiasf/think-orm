<?php

namespace ThinkOrm\Tests\Integration;

use think\Db;
use think\Paginator;
use think\Request;
use ThinkOrm\Tests\Fixture\Post;
use ThinkOrm\Tests\Fixture\Profile;
use ThinkOrm\Tests\Fixture\User;
use ThinkOrm\Tests\IntegrationTestCase;

/**
 * 补齐 Paginator URL 辅助方法（appends/fragment/getUrlRange/getCurrentPage/getCurrentPath）
 * 与 Model 高级查询 API（has / hasWhere / together）的集成测试。
 *
 * @group integration
 */
class AdvancedApiTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // 默认 Request 桩 param() 返回 null，page 默认 1
        Request::setInstance(new Request());
    }

    // —— Paginator URL 辅助方法（注意 url() 是 protected，公开 API 通过 getUrlRange）——

    public function testPaginatorAppendsAddsQueryParams()
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->seedUser();
        }
        $pager = Db::name('users')->order('id')->paginate(5);
        $pager->appends(['sort' => 'name', 'order' => 'asc']);
        // 用 getUrlRange 拿到第 2 页 URL，验证 query 被附加上
        $urls = $pager->getUrlRange(2, 2);
        $url  = $urls[2];
        $this->assertStringContainsString('sort=name', $url);
        $this->assertStringContainsString('order=asc', $url);
        $this->assertStringContainsString('page=2', $url);
    }

    public function testPaginatorAppendsScalarForm()
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->seedUser();
        }
        $pager = Db::name('users')->order('id')->paginate(5);
        $pager->appends('foo', 'bar');
        $urls = $pager->getUrlRange(1, 1);
        $this->assertStringContainsString('foo=bar', $urls[1]);
    }

    public function testPaginatorAppendsIgnoresPageVar()
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->seedUser();
        }
        $pager = Db::name('users')->order('id')->paginate(5);
        // appends 不能覆盖 var_page（默认 'page'）
        $pager->appends('page', '999');
        $urls = $pager->getUrlRange(2, 2);
        $this->assertStringContainsString('page=2', $urls[2]);
        $this->assertStringNotContainsString('page=999', $urls[2]);
    }

    public function testPaginatorFragment()
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->seedUser();
        }
        $pager = Db::name('users')->order('id')->paginate(5);
        $pager->fragment('comments');
        $urls = $pager->getUrlRange(1, 1);
        $this->assertStringContainsString('#comments', $urls[1]);
    }

    public function testPaginatorFragmentEmptyByDefault()
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->seedUser();
        }
        $pager = Db::name('users')->order('id')->paginate(5);
        $urls = $pager->getUrlRange(1, 1);
        $this->assertStringNotContainsString('#', $urls[1]);
    }

    public function testPaginatorGetUrlRange()
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->seedUser();
        }
        $pager = Db::name('users')->order('id')->paginate(10);
        $urls = $pager->getUrlRange(2, 3);
        $this->assertCount(2, $urls);
        $this->assertStringContainsString('page=2', $urls[2]);
        $this->assertStringContainsString('page=3', $urls[3]);
    }

    public function testPaginatorGetCurrentPageDefaultsToOne()
    {
        // 默认 Request 桩 param() 返回 null，page 默认 1
        $this->assertSame(1, Paginator::getCurrentPage('page'));
        $this->assertSame(5, Paginator::getCurrentPage('page', 5));
    }

    public function testPaginatorGetCurrentPageFromRequest()
    {
        $mockReq = new PaginatorRequestMock(['page' => '7']);
        Request::setInstance($mockReq);
        $this->assertSame(7, Paginator::getCurrentPage('page'));
        Request::setInstance(new Request());
    }

    public function testPaginatorGetCurrentPageIgnoresInvalidValue()
    {
        $mockReq = new PaginatorRequestMock(['page' => 'abc']);
        Request::setInstance($mockReq);
        $this->assertSame(1, Paginator::getCurrentPage('page'));
        Request::setInstance(new Request());
    }

    public function testPaginatorGetCurrentPageIgnoresZero()
    {
        $mockReq = new PaginatorRequestMock(['page' => '0']);
        Request::setInstance($mockReq);
        $this->assertSame(1, Paginator::getCurrentPage('page'));
        Request::setInstance(new Request());
    }

    public function testPaginatorGetCurrentPath()
    {
        $mockReq = new PaginatorRequestMock([], '/users/list');
        Request::setInstance($mockReq);
        $this->assertSame('/users/list', Paginator::getCurrentPath());
        Request::setInstance(new Request());
    }

    public function testPaginatorHasPages()
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->seedUser();
        }
        $pager = Db::name('users')->order('id')->paginate(10);
        $this->assertTrue($pager->hasPages());

        // 第 1 页且无更多 → false
        Db::execute("TRUNCATE TABLE `users`");
        $this->seedUser();
        $pager = Db::name('users')->order('id')->paginate(10);
        $this->assertFalse($pager->hasPages());
    }

    public function testPaginatorListRows()
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->seedUser();
        }
        $pager = Db::name('users')->order('id')->paginate(7);
        $this->assertSame(7, $pager->listRows());
    }

    public function testPaginatorIsEmpty()
    {
        $pager = Db::name('users')->order('id')->paginate(10);
        $this->assertTrue($pager->isEmpty());
        for ($i = 1; $i <= 3; $i++) {
            $this->seedUser();
        }
        $pager = Db::name('users')->order('id')->paginate(10);
        $this->assertFalse($pager->isEmpty());
    }

    public function testPaginatorEach()
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->seedUser(['name' => 'u' . $i]);
        }
        $pager = Db::name('users')->order('id')->paginate(10);
        $count = 0;
        $pager->each(function ($item) use (&$count) {
            $count++;
        });
        $this->assertSame(3, $count);
    }

    public function testPaginatorArrayAccessAndCount()
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->seedUser(['age' => $i]);
        }
        $pager = Db::name('users')->order('id')->paginate(10);
        $this->assertSame(5, count($pager));
        $this->assertSame(5, count($pager->items()));
        // ArrayAccess
        $this->assertArrayHasKey(0, $pager);
        $this->assertNotNull($pager[0]);
    }

    public function testPaginatorRenderProducesBootstrapHtml()
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->seedUser();
        }
        $pager = Db::name('users')->order('id')->paginate(10);
        $html = $pager->render();
        // Bootstrap 风格的 ul.pagination 包裹
        $this->assertIsString($html);
        $this->assertStringContainsString('class="pagination"', $html);
        // 当前页是 active span（不含 href）；其它页是带 page=N 的链接
        // 第 1 页是 active；第 2、3 页是可点击的链接
        $this->assertStringContainsString('page=2', $html);
        $this->assertStringContainsString('page=3', $html);
    }

    // —— Model::has() —— 用 hasMany 关联筛选 ——

    public function testModelHasFiltersByRelationCount()
    {
        $u1 = $this->seedUser(['name' => 'with_posts']);
        $u2 = $this->seedUser(['name' => 'no_posts']);
        Db::name('posts')->insert(['user_id' => $u1, 'title' => 'p1']);
        Db::name('posts')->insert(['user_id' => $u1, 'title' => 'p2']);
        Db::name('posts')->insert(['user_id' => $u1, 'title' => 'p3']);

        // has('posts') 默认 operator='>=', count=1
        $users = User::has('posts')->select();
        $this->assertCount(1, $users);
        $this->assertSame($u1, (int) $users[0]->id);
    }

    public function testModelHasWithGreaterThanOperator()
    {
        $u1 = $this->seedUser(['name' => 'many']);
        $u2 = $this->seedUser(['name' => 'few']);
        for ($i = 0; $i < 5; $i++) {
            Db::name('posts')->insert(['user_id' => $u1, 'title' => "p{$i}"]);
        }
        Db::name('posts')->insert(['user_id' => $u2, 'title' => 'only']);

        // 至少 3 条
        $users = User::has('posts', '>=', 3)->select();
        $ids = array_map(function ($u) { return (int) $u->id; }, $users);
        sort($ids);
        $this->assertEquals([$u1], $ids);
    }

    // —— Model::hasWhere() —— 用关联字段筛选 ——

    public function testModelHasWhereFiltersByRelatedField()
    {
        $u1 = $this->seedUser(['name' => 'alice']);
        $u2 = $this->seedUser(['name' => 'bob']);
        Db::name('posts')->insert(['user_id' => $u1, 'title' => 'special_one']);
        Db::name('posts')->insert(['user_id' => $u2, 'title' => 'regular']);

        // hasWhere: posts.title = 'special_one'
        $users = User::hasWhere('posts', ['title' => 'special_one'])->select();
        $this->assertCount(1, $users);
        $this->assertSame($u1, (int) $users[0]->id);
    }

    // —— Model::together() —— 关联数据一起写入（hasOne）——

    public function testModelTogetherCreatesHasOneRelation()
    {
        $user = new User();
        $user->name  = 'carol';
        $user->email = 'c@x.com';
        $user->age   = 30;

        // User hasOne Profile（Profile 实际写入 posts 表，user_id 关联）
        $profile = new Profile(['title' => 'bio', 'body' => 'about carol']);
        $user->profile = $profile;
        $user->together('profile')->save();

        $this->assertNotEmpty($user->id);
        $row = Db::name('posts')->where('user_id', $user->id)->find();
        $this->assertNotEmpty($row);
        $this->assertSame('bio', $row['title']);
        $this->assertSame('about carol', $row['body']);
    }

    public function testModelTogetherUpdateHasOneRelation()
    {
        // 先创建
        $user = new User();
        $user->name  = 'dave';
        $user->email = 'd@x.com';
        $user->age   = 25;
        $profile = new Profile(['title' => 'first', 'body' => 'aa']);
        $user->profile = $profile;
        $user->together('profile')->save();

        $postId = Db::name('posts')->where('user_id', $user->id)->value('id');
        $this->assertNotEmpty($postId);

        // 重新取出（isUpdate=true），改字段，更新主表 + 关联表
        $loaded = User::get($user->id);
        $loaded->age = 26;
        $existingProfile = Profile::get($postId);
        $existingProfile->title = 'updated_title';
        $loaded->profile = $existingProfile;
        $loaded->together('profile')->save();

        $this->assertSame('updated_title', Db::name('posts')->where('id', $postId)->value('title'));
        $this->assertSame(26, Db::name('users')->where('id', $user->id)->value('age'));
    }
}

/**
 * 用于 Paginator 测试的 Request 桩：支持自定义 param() / baseUrl() 返回值
 */
class PaginatorRequestMock extends Request
{
    private $params;
    private $baseUrl;

    public function __construct(array $params = [], string $baseUrl = '/')
    {
        $this->params   = $params;
        $this->baseUrl  = $baseUrl;
    }

    public function param($name = '', $default = null)
    {
        if ($name === '') return $this->params;
        return $this->params[$name] ?? $default;
    }

    public function baseUrl()
    {
        return $this->baseUrl;
    }
}
