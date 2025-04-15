<?php

namespace Tourze\LockServiceBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Tourze\LockServiceBundle\LockServiceBundle;

/**
 * 测试 LockServiceBundle
 */
class LockServiceBundleTest extends TestCase
{
    /**
     * 测试 bundle 实例化
     */
    public function testBundleInstance(): void
    {
        $bundle = new LockServiceBundle();

        $this->assertInstanceOf(BundleInterface::class, $bundle);
    }
}
