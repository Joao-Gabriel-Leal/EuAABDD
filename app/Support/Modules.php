<?php

namespace App\Support;

class Modules
{
    public static function enabled(string $module): bool
    {
        return (bool) config("modules.enabled.{$module}", false);
    }
}
