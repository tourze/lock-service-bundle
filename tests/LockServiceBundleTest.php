<?php

declare(strict_types=1);

namespace Tourze\LockServiceBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LockServiceBundle\LockServiceBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(LockServiceBundle::class)]
#[RunTestsInSeparateProcesses]
final class LockServiceBundleTest extends AbstractBundleTestCase
{
}
