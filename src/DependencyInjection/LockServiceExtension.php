<?php

namespace Tourze\LockServiceBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AppendDoctrineConnectionExtension;

class LockServiceExtension extends AppendDoctrineConnectionExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }

    protected function getDoctrineConnectionName(): string
    {
        return 'lock';
    }
}
