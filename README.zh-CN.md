# Lock Service Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/lock-service-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/lock-service-bundle)
[![Build Status](https://img.shields.io/travis/tourze/lock-service-bundle/master.svg?style=flat-square)](https://travis-ci.org/tourze/lock-service-bundle)
[![Quality Score](https://img.shields.io/scrutinizer/g/tourze/lock-service-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/lock-service-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/lock-service-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/lock-service-bundle)

Lock Service Bundle 为 Symfony 提供灵活的分布式锁服务，支持 Redis 集群、数据库、文件等多种后端，适用于高并发场景下的资源互斥与同步。

## 功能特性

- 支持 Redis 集群、数据库、文件等多种锁存储后端
- 提供 SmartLockStore 自动切换后端实现
- 支持读写锁、阻塞锁、多资源锁等
- 与 Symfony 生态无缝集成
- 易于扩展和自定义

## 安装说明

### 环境要求

- PHP >= 8.1
- Symfony >= 6.4
- 需配置 Redis、数据库等后端服务

### Composer 安装

```bash
composer require tourze/lock-service-bundle
```

## 快速开始

```php
use Tourze\LockServiceBundle\Service\LockService;

$lockService = ... // 通过依赖注入获取

// 阻塞执行
$lockService->blockingRun('resource-key', function () {
    // 受锁保护的逻辑
});

// 多资源加锁
$lockService->blockingRun(['key1', 'key2'], function () {
    // 受多把锁保护的逻辑
});
```

## 配置说明

通过环境变量 `APP_LOCK_TYPE` 选择锁类型：

- redis
- redis-cluster
- dbal
- flock

示例：

```dotenv
APP_LOCK_TYPE=redis
```

## 高级特性

- SmartLockStore 自动切换后端存储
- 加锁支持重试与阻塞等待机制
- 支持读写锁（参见 RedisClusterStore）
- 可扩展：实现 `LockEntity` 接口自定义锁资源

## 实体设计

本模块提供 `LockEntity` 接口用于定义锁资源：

```php
interface LockEntity {
    public function retrieveLockResource(): string;
}
```

业务实体实现该接口后，可实现细粒度的分布式锁。

## 贡献指南

请参阅 [CONTRIBUTING.md](CONTRIBUTING.md) 获取详情。代码需遵循 PSR 标准并补充测试。

## 版权和许可

MIT License

## 更新日志

详见 CHANGELOG.md
