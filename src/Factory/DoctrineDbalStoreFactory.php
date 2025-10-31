<?php

namespace Tourze\LockServiceBundle\Factory;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\Store\DoctrineDbalStore;

readonly class DoctrineDbalStoreFactory
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.lock_connection')] private Connection $connection,
    ) {
    }

    public function create(): DoctrineDbalStore
    {
        return new DoctrineDbalStore($this->connection);
    }
}
