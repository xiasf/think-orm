<?php

namespace ThinkOrm\Tests\Fixture;

class InvokeTarget
{
    public static function hello($name)
    {
        return 'hello ' . $name;
    }
}
