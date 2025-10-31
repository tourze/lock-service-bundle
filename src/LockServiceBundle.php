<?php

namespace Tourze\LockServiceBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\RedisDedicatedConnectionBundle\RedisDedicatedConnectionBundle;

class LockServiceBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            RedisDedicatedConnectionBundle::class => ['all' => true],
        ];
    }
}
