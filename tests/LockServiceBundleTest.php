<?php

namespace Tourze\LockServiceBundle\Tests;

use PHPUnit\Framework\TestCase;
use Snc\RedisBundle\SncRedisBundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\LockServiceBundle\LockServiceBundle;

/**
 * 测试 LockServiceBundle
 */
class LockServiceBundleTest extends TestCase
{
    private LockServiceBundle $bundle;

    protected function setUp(): void
    {
        $this->bundle = new LockServiceBundle();
    }

    /**
     * 测试 bundle 实例化
     */
    public function test_constructor_createsBundleInstance(): void
    {
        $this->assertInstanceOf(BundleInterface::class, $this->bundle);
        $this->assertInstanceOf(LockServiceBundle::class, $this->bundle);
    }

    /**
     * 测试实现 BundleDependencyInterface
     */
    public function test_implementsBundleDependencyInterface(): void
    {
        $this->assertInstanceOf(BundleDependencyInterface::class, $this->bundle);
    }

    /**
     * 测试 getBundleDependencies 返回正确的依赖
     */
    public function test_getBundleDependencies_returnsCorrectDependencies(): void
    {
        $dependencies = LockServiceBundle::getBundleDependencies();

        $this->assertArrayHasKey(SncRedisBundle::class, $dependencies);
        $this->assertEquals(['all' => true], $dependencies[SncRedisBundle::class]);
    }

    /**
     * 测试依赖数组不为空
     */
    public function test_getBundleDependencies_isNotEmpty(): void
    {
        $dependencies = LockServiceBundle::getBundleDependencies();

        $this->assertNotEmpty($dependencies);
        $this->assertCount(1, $dependencies);
    }

    /**
     * 测试依赖包含 SncRedisBundle
     */
    public function test_getBundleDependencies_includesSncRedisBundle(): void
    {
        $dependencies = LockServiceBundle::getBundleDependencies();

        $this->assertArrayHasKey(SncRedisBundle::class, $dependencies);
        $this->assertIsArray($dependencies[SncRedisBundle::class]);
        $this->assertTrue($dependencies[SncRedisBundle::class]['all']);
    }

    /**
     * 测试 Bundle 名称
     */
    public function test_getName_returnsCorrectName(): void
    {
        $expectedName = 'LockServiceBundle';
        $actualName = $this->bundle->getName();

        $this->assertEquals($expectedName, $actualName);
    }

    /**
     * 测试 Bundle 路径
     */
    public function test_getPath_returnsCorrectPath(): void
    {
        $expectedPath = dirname(__DIR__) . '/src';
        $actualPath = $this->bundle->getPath();

        $this->assertEquals($expectedPath, $actualPath);
    }

    /**
     * 测试 Bundle 命名空间
     */
    public function test_getNamespace_returnsCorrectNamespace(): void
    {
        $expectedNamespace = 'Tourze\LockServiceBundle';
        $actualNamespace = $this->bundle->getNamespace();

        $this->assertEquals($expectedNamespace, $actualNamespace);
    }
}
