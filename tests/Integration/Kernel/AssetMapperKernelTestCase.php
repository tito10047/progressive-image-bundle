<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Integration\Kernel;

use Symfony\Component\HttpKernel\KernelInterface;
use Tito10047\ProgressiveImageBundle\Tests\App\Kernel;

class AssetMapperKernelTestCase extends \Symfony\Bundle\FrameworkBundle\Test\KernelTestCase
{
    protected static function bootKernel(array $options = []): KernelInterface
    {
        static::ensureKernelShutdown();

        $kernel = new Kernel('test', 'AssetMapper/config' ?? null, $options['preBoot'] ?? null);
        $kernel->boot();
        static::$kernel = $kernel;
        static::$booted = true;

        return static::$kernel;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        static::ensureKernelShutdown();
    }
}
