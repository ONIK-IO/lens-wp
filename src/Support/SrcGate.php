<?php

namespace OnikImages\Support;

/**
 * Class facade over should_alter_image_based_on_src in support.php.
 *
 * Heads-up: tests mock the procedural name via Brain Monkey
 * (Functions\when('should_alter_image_based_on_src')->justReturn(...)). When
 * those tests run, this delegation returns the mocked value automatically.
 */
class SrcGate
{
    public static function shouldAlter(string $src): bool
    {
        return should_alter_image_based_on_src($src);
    }
}
