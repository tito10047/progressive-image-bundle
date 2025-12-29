<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class PGITestCase extends KernelTestCase {
	protected static function bootKernel(array $options = []): KernelInterface
	{
		static::ensureKernelShutdown();

		$kernel = new ProgressiveImageTestingKernel($options);
		$kernel->boot();
		static::$kernel = $kernel;
		static::$booted = true;

		return static::$kernel;
	}
}