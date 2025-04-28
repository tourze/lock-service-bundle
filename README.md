# Lock Service Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/lock-service-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/lock-service-bundle)
[![Build Status](https://img.shields.io/travis/tourze/lock-service-bundle/master.svg?style=flat-square)](https://travis-ci.org/tourze/lock-service-bundle)
[![Quality Score](https://img.shields.io/scrutinizer/g/tourze/lock-service-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/lock-service-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/lock-service-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/lock-service-bundle)

A flexible distributed lock service bundle for Symfony, supporting Redis Cluster, database, and file-based backends. Ideal for resource mutual exclusion and synchronization in high concurrency scenarios.

## Features

- Supports Redis Cluster, database, and file lock backends
- SmartLockStore for automatic backend switching
- Provides read/write locks, blocking locks, and multi-resource locking
- Seamless integration with Symfony ecosystem
- Easy to extend and customize

## Installation

### Requirements

- PHP >= 8.1
- Symfony >= 6.4
- Redis, database, or file backend configured

### Composer

```bash
composer require tourze/lock-service-bundle
```

## Quick Start

```php
use Tourze\LockServiceBundle\Service\LockService;

$lockService = ... // Get via dependency injection

// Blocking execution
$lockService->blockingRun('resource-key', function () {
    // Logic protected by lock
});

// Multi-resource locking
$lockService->blockingRun(['key1', 'key2'], function () {
    // Logic protected by multiple locks
});
```

## Configuration

Select lock type via `APP_LOCK_TYPE` environment variable:

- redis
- redis-cluster
- dbal
- flock

Example:

```dotenv
APP_LOCK_TYPE=redis
```

## Advanced Usage

- Automatic backend switching via SmartLockStore
- Retry and wait mechanism for lock acquisition
- Read/write lock support (see RedisClusterStore)
- Extensible: implement `LockEntity` interface for custom lock resources

## Entity Design

This bundle provides a `LockEntity` interface for defining lock resources:

```php
interface LockEntity {
    public function retrieveLockResource(): string;
}
```

Implement this interface for your business entities to enable fine-grained distributed locking.

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details. Follow PSR standards and provide tests.

## License

MIT License

## Changelog

See CHANGELOG.md
