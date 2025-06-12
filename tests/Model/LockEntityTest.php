<?php

namespace Tourze\LockServiceBundle\Tests\Model;

use PHPUnit\Framework\TestCase;
use Tourze\LockServiceBundle\Model\LockEntity;

/**
 * LockEntity 接口测试
 */
class LockEntityTest extends TestCase
{
    /**
     * 测试接口方法存在
     */
    public function test_interface_hasRequiredMethod(): void
    {
        $reflection = new \ReflectionClass(LockEntity::class);

        $this->assertTrue($reflection->hasMethod('retrieveLockResource'));

        $method = $reflection->getMethod('retrieveLockResource');
        $this->assertTrue($method->isPublic());
        $this->assertEquals('string', $method->getReturnType()?->getName());
    }

    /**
     * 测试接口实现
     */
    public function test_implementation_worksCorrectly(): void
    {
        $implementation = new class implements LockEntity {
            public function retrieveLockResource(): string
            {
                return 'test-lock-resource';
            }
        };

        $this->assertInstanceOf(LockEntity::class, $implementation);
        $this->assertEquals('test-lock-resource', $implementation->retrieveLockResource());
    }

    /**
     * 测试 TestLockEntity 实现
     */
    public function test_testLockEntity_implementation(): void
    {
        $entity = new TestLockEntity('my-resource');

        $this->assertInstanceOf(LockEntity::class, $entity);
        $this->assertEquals('my-resource', $entity->retrieveLockResource());
    }

    /**
     * 测试不同资源标识符
     */
    public function test_implementation_withDifferentResources(): void
    {
        $entity1 = new TestLockEntity('resource-1');
        $entity2 = new TestLockEntity('resource-2');

        $this->assertEquals('resource-1', $entity1->retrieveLockResource());
        $this->assertEquals('resource-2', $entity2->retrieveLockResource());
        $this->assertNotEquals($entity1->retrieveLockResource(), $entity2->retrieveLockResource());
    }

    /**
     * 测试空资源标识符
     */
    public function test_implementation_withEmptyResource(): void
    {
        $entity = new TestLockEntity('');

        $this->assertEquals('', $entity->retrieveLockResource());
    }

    /**
     * 测试复杂资源标识符
     */
    public function test_implementation_withComplexResource(): void
    {
        $complexResource = 'user:123:action:update:timestamp:' . time();
        $entity = new TestLockEntity($complexResource);

        $this->assertEquals($complexResource, $entity->retrieveLockResource());
    }

    /**
     * 测试接口可以用作类型提示
     */
    public function test_interface_canBeUsedAsTypeHint(): void
    {
        $processor = new class {
            public function processLockEntity(LockEntity $entity): string
            {
                return 'processed:' . $entity->retrieveLockResource();
            }
        };

        $entity = new TestLockEntity('test-resource');
        $result = $processor->processLockEntity($entity);

        $this->assertEquals('processed:test-resource', $result);
    }
}
