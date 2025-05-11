<?php

namespace Tourze\LockServiceBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;

class LockServiceBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            \Snc\RedisBundle\SncRedisBundle::class => ['all' => true],
        ];
    }
}
