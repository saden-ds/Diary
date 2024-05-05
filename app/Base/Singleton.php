<?php

namespace App\Base;

use RuntimeException;

abstract class Singleton
{
    private static array $instances = [];

    /**
     * @throws RuntimeException
     */
    public function __wakeup()
    {
        throw new RuntimeException('Cannot unserialize singleton');
    }

    public static function init()
    {
        $subclass = static::class;

        if (!isset(self::$instances[$subclass])) {
            self::$instances[$subclass] = new static;
        }

        return self::$instances[$subclass];
    }

    private function __clone()
    {
    }
}