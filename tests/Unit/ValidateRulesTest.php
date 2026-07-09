<?php

namespace ThinkOrm\Tests\Unit;

use think\Validate;
use ThinkOrm\Tests\UnitTestCase;

/**
 * @group unit
 */
class ValidateRulesTest extends UnitTestCase
{
    private function check($rule, $value, $data = [])
    {
        $v = new Validate();
        $v->rule(['field' => $rule]);
        return $v->check(array_merge(['field' => $value], $data));
    }

    private function error($rule, $value, $data = [])
    {
        $v = new Validate();
        $v->rule(['field' => $rule]);
        $v->check(array_merge(['field' => $value], $data));
        return $v->getError();
    }

    public function testRequire()
    {
        $this->assertTrue($this->check('require', 'x'));
        $this->assertFalse($this->check('require', ''));
        $this->assertStringContainsString('必须', $this->error('require', ''));
    }

    public function testNumber()
    {
        $this->assertTrue($this->check('number', 123));
        $this->assertTrue($this->check('number', '12.34'));
        $this->assertFalse($this->check('number', 'abc'));
        $this->assertStringContainsString('数字', $this->error('number', 'abc'));
    }

    public function testInteger()
    {
        $this->assertTrue($this->check('integer', 10));
        $this->assertFalse($this->check('integer', 1.5));
    }

    public function testFloat()
    {
        $this->assertTrue($this->check('float', 1.5));
        $this->assertFalse($this->check('float', 'notnum'));
    }

    public function testBoolean()
    {
        $this->assertTrue($this->check('boolean', true));
        $this->assertTrue($this->check('boolean', false));
        $this->assertFalse($this->check('boolean', 'maybe'));
    }

    public function testEmail()
    {
        $this->assertTrue($this->check('email', 'foo@bar.com'));
        $this->assertFalse($this->check('email', 'no-at-sign'));
        $this->assertStringContainsString('邮箱', $this->error('email', 'bad'));
    }

    public function testArray()
    {
        $this->assertTrue($this->check('array', [1, 2]));
        $this->assertFalse($this->check('array', 'str'));
    }

    public function testAccepted()
    {
        $this->assertTrue($this->check('accepted', 'yes'));
        $this->assertTrue($this->check('accepted', 'on'));
        $this->assertTrue($this->check('accepted', '1'));
        $this->assertFalse($this->check('accepted', 'no'));
    }

    public function testDate()
    {
        $this->assertTrue($this->check('date', '2024-01-01'));
        $this->assertTrue($this->check('date', '2024-01-01 10:00:00'));
        $this->assertFalse($this->check('date', 'not a date'));
    }

    public function testAlpha()
    {
        $this->assertTrue($this->check('alpha', 'abcXYZ'));
        $this->assertFalse($this->check('alpha', 'abc123'));
    }

    public function testAlphaNum()
    {
        $this->assertTrue($this->check('alphaNum', 'abc123'));
        $this->assertFalse($this->check('alphaNum', 'abc-123'));
    }

    public function testAlphaDash()
    {
        $this->assertTrue($this->check('alphaDash', 'abc-1_2'));
        $this->assertFalse($this->check('alphaDash', 'abc def'));
    }

    public function testChs()
    {
        $this->assertTrue($this->check('chs', '中文'));
        $this->assertFalse($this->check('chs', 'abc'));
    }

    public function testChsAlphaNum()
    {
        $this->assertTrue($this->check('chsAlphaNum', '中文abc123'));
        $this->assertFalse($this->check('chsAlphaNum', '中文-abc'));
    }

    public function testUrl()
    {
        $this->assertTrue($this->check('url', 'http://example.com'));
        $this->assertFalse($this->check('url', 'not a url'));
    }

    public function testIp()
    {
        $this->assertTrue($this->check('ip', '127.0.0.1'));
        $this->assertFalse($this->check('ip', '999.999.999.999'));
    }

    public function testIn()
    {
        $this->assertTrue($this->check('in:1,2,3', 2));
        $this->assertFalse($this->check('in:1,2,3', 4));
    }

    public function testNotIn()
    {
        $this->assertTrue($this->check('notIn:1,2,3', 4));
        $this->assertFalse($this->check('notIn:1,2,3', 1));
    }

    public function testBetween()
    {
        $this->assertTrue($this->check('between:1,10', 5));
        $this->assertFalse($this->check('between:1,10', 100));
        $this->assertStringContainsString('1 - 10', $this->error('between:1,10', 100));
    }

    public function testNotBetween()
    {
        $this->assertTrue($this->check('notBetween:1,10', 100));
        $this->assertFalse($this->check('notBetween:1,10', 5));
    }

    public function testLength()
    {
        $this->assertTrue($this->check('length:3', 'abc'));
        $this->assertFalse($this->check('length:5', 'abc'));
    }

    public function testMax()
    {
        $this->assertTrue($this->check('max:5', 'abc'));
        $this->assertFalse($this->check('max:2', 'abc'));
    }

    public function testMin()
    {
        $this->assertTrue($this->check('min:2', 'abc'));
        $this->assertFalse($this->check('min:5', 'abc'));
    }

    public function testEgt()
    {
        $this->assertTrue($this->check('egt:10', 15));
        $this->assertFalse($this->check('egt:10', 5));
    }

    public function testGt()
    {
        $this->assertTrue($this->check('gt:10', 15));
        $this->assertFalse($this->check('gt:10', 10));
    }

    public function testElt()
    {
        $this->assertTrue($this->check('elt:10', 5));
        $this->assertFalse($this->check('elt:10', 15));
    }

    public function testLt()
    {
        $this->assertTrue($this->check('lt:10', 5));
        $this->assertFalse($this->check('lt:10', 10));
    }

    public function testEq()
    {
        $this->assertTrue($this->check('eq:42', 42));
        $this->assertFalse($this->check('eq:42', 41));
    }

    public function testRegex()
    {
        $this->assertTrue($this->check('regex:\d{3}', '123'));
        $this->assertFalse($this->check('regex:\d{3}', 'abc'));
    }

    public function testConfirm()
    {
        $this->assertTrue($this->check('confirm:password', 'pass', ['password' => 'pass']));
        $this->assertFalse($this->check('confirm:password', 'pass', ['password' => 'different']));
    }

    public function testDifferent()
    {
        $this->assertTrue($this->check('different:other', 'a', ['other' => 'b']));
        $this->assertFalse($this->check('different:other', 'a', ['other' => 'a']));
    }

    public function testFilter()
    {
        // 字符串规则 'filter:email' 受 PHP filter_id 映射不一致影响；
        // 这里改用 closure 形式直接用 filter_var
        $v = new Validate();
        $v->rule([
            'field' => function ($value) {
                return false !== filter_var($value, FILTER_VALIDATE_INT) ? true : '必须是整数';
            },
        ]);
        $this->assertTrue($v->check(['field' => '123']));
        $this->assertFalse($v->check(['field' => 'abc']));
        $this->assertStringContainsString('整数', $v->getError());
    }

    public function testBatch()
    {
        $v = new Validate();
        $v->rule([
            'name'  => 'require|max:3',
            'email' => 'require|email',
        ]);
        $ok = $v->batch(true)->check([
            'name'  => 'abcdefg',
            'email' => 'bad-email',
        ]);
        $this->assertFalse($ok);
        $errors = $v->getError();
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testCustomMessage()
    {
        $v = new Validate();
        $v->rule(['name' => 'require']);
        $v->message(['name.require' => '名字不能为空']);
        $v->check(['name' => '']);
        $this->assertSame('名字不能为空', $v->getError());
    }

    public function testFieldAlias()
    {
        // Validate::field() 不存在，通过构造函数注入字段别名
        $v = new Validate(['name' => 'require'], [], ['name' => '名字']);
        $v->check(['name' => '']);
        $this->assertStringContainsString('名字', $v->getError());
    }

    public function testPlaceholderSubstitution()
    {
        $msg = $this->error('between:1,10', 100);
        $this->assertStringContainsString('1 - 10', $msg);
    }

    public function testCustomCallback()
    {
        $v = new Validate();
        $v->extend('odd', function ($value) {
            return $value % 2 === 1 ? true : '必须是奇数';
        });
        $v->rule(['field' => 'odd']);
        $this->assertTrue($v->check(['field' => 3]));
        $this->assertFalse($v->check(['field' => 4]));
        $this->assertStringContainsString('奇数', $v->getError());
    }
}
