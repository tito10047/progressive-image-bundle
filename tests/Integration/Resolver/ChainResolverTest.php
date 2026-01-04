<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Integration\Resolver;

use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;
use Tito10047\ProgressiveImageBundle\Service\MetadataReader;

class ChainResolverTest extends PGITestCase {

	public function testChainResolverIsRegistered(): void {
		$kernel = $this->createKernel([
			'progressive_image' => [
				'resolvers' => [
					'public_files' => [
						'type'  => 'filesystem',
						'roots' => ['%kernel.project_dir%/public'],
					],
					'assets'       => [
						'type' => 'asset_mapper',
					],
					'my_chain'     => [
						'type'      => 'chain',
						'resolvers' => [
							'public_files',
							'assets',
						],
					],
				],
				'resolver'  => 'my_chain',
			],
		]);
		$kernel->boot();
		$container = $kernel->getContainer();

		// Verify through MetadataReader, which has the resolver injected.
		// In the Symfony test container, we can obtain services that are otherwise private if the correct container is used.
		// But MetadataReader is public (see services.php), so this is a safe way.
		$metadataReader = $container->get(MetadataReader::class);
		$reflection     = new \ReflectionClass($metadataReader);
		$property       = $reflection->getProperty('pathResolver');
		$property->setAccessible(true);
		$resolver = $property->getValue($metadataReader);

		$this->assertInstanceOf(\Tito10047\ProgressiveImageBundle\Resolver\ChainResolver::class, $resolver);
	}

	public function testDefaultResolverIsUsedWhenNotSpecified(): void {
		$kernel = $this->createKernel([
			'progressive_image' => [
				'resolvers' => [
					'custom' => [
						'type'  => 'filesystem',
						'roots' => ['%kernel.project_dir%/public'],
					],
				],
				// resolver is not specified, should default to 'default' which should pick the first one 'custom'
			],
		]);
		$kernel->boot();
		$container = $kernel->getContainer();

		$metadataReader = $container->get(MetadataReader::class);
		$reflection     = new \ReflectionClass($metadataReader);
		$property       = $reflection->getProperty('pathResolver');
		$property->setAccessible(true);
		$resolver = $property->getValue($metadataReader);

		$this->assertInstanceOf(\Tito10047\ProgressiveImageBundle\Resolver\FileSystemResolver::class, $resolver);
	}
}
