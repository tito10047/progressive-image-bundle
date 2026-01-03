<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class PGITestCase extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return ProgressiveImageTestingKernel::class;
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new ProgressiveImageTestingKernel($options);
    }

    protected static function bootKernel(array $options = []): KernelInterface
    {
        static::ensureKernelShutdown();

        $kernel = static::createKernel($options);
        $kernel->boot();
        static::$kernel = $kernel;
        static::$booted = true;

        return static::$kernel;
    }
}
