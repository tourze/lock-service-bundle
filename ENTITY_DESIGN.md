# Lock Service Bundle 数据库实体设计

本模块包含以下 Entity：

## LockEntity

- 位置：src/Model/LockEntity.php
- 类型：接口
- 说明：用于定义锁资源的唯一标识，所有需要加锁的业务实体需实现该接口。

```php
interface LockEntity {
    /**
     * 获取锁id
     */
    public function retrieveLockResource(): string;
}
```

### 设计说明

LockEntity 仅定义接口，实际锁资源的唯一标识由实现类自行决定，保证锁粒度灵活可控。适合多业务场景下的分布式锁需求。
