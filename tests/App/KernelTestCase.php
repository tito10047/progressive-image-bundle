<?php

namespace Tito10047\ProgressiveImageBundle\Tests\App;

use Symfony\Component\HttpKernel\KernelInterface;

class KernelTestCase extends \Symfony\Bundle\FrameworkBundle\Test\KernelTestCase
{
    protected static function bootKernel(array $options = []): KernelInterface
    {
        static::ensureKernelShutdown();

        $kernel = new Kernel('test', $options['configDir'] ?? null, $options['preBoot'] ?? null);
        $kernel->boot();
        static::$kernel = $kernel;
        static::$booted = true;

        return static::$kernel;
    }
}
