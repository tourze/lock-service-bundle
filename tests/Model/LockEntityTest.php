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
     * 创建测试用的LockEntity实现
     */
    private function createTestEntity(string $resource): LockEntity
    {
        return new class($resource) implements LockEntity {
            public function __construct(private readonly string $resource) {}
            public function retrieveLockResource(): string { return $this->resource; }
        };
    }
    /**
     * 测试接口方法存在
     */
    public function test_interface_hasRequiredMethod(): void
    {
        $reflection = new \ReflectionClass(LockEntity::class);

        $this->assertTrue($reflection->hasMethod('retrieveLockResource'));

        $method = $reflection->getMethod('retrieveLockResource');
        $this->assertTrue($method->isPublic());
        $this->assertEquals('string', (string)$method->getReturnType());
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
     * 测试具体实现类
     */
    public function test_concreteImplementation(): void
    {
        $entity = new class('my-resource') implements LockEntity {
            public function __construct(private readonly string $resource) {}
            public function retrieveLockResource(): string { return $this->resource; }
        };

        $this->assertInstanceOf(LockEntity::class, $entity);
        $this->assertEquals('my-resource', $entity->retrieveLockResource());
    }

    /**
     * 测试不同资源标识符
     */
    public function test_implementation_withDifferentResources(): void
    {
        $entity1 = $this->createTestEntity('resource-1');
        $entity2 = $this->createTestEntity('resource-2');

        $this->assertEquals('resource-1', $entity1->retrieveLockResource());
        $this->assertEquals('resource-2', $entity2->retrieveLockResource());
        $this->assertNotEquals($entity1->retrieveLockResource(), $entity2->retrieveLockResource());
    }

    /**
     * 测试空资源标识符
     */
    public function test_implementation_withEmptyResource(): void
    {
        $entity = $this->createTestEntity('');

        $this->assertEquals('', $entity->retrieveLockResource());
    }

    /**
     * 测试复杂资源标识符
     */
    public function test_implementation_withComplexResource(): void
    {
        $complexResource = 'user:123:action:update:timestamp:' . time();
        $entity = $this->createTestEntity($complexResource);

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

        $entity = $this->createTestEntity('test-resource');
        $result = $processor->processLockEntity($entity);

        $this->assertEquals('processed:test-resource', $result);
    }
}
