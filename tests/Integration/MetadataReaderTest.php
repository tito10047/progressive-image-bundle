<?php

namespace Tito10047\ProgressiveImageBundle\Tests\Integration;

use Symfony\Component\Filesystem\Filesystem;
use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;
use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;
use Tito10047\ProgressiveImageBundle\Service\MetadataReader;
use Tito10047\ProgressiveImageBundle\Tests\Integration\Kernel\AssetMapperKernelTestCase;

class MetadataReaderTest extends PGITestCase {

	private string     $tempDir;
	private Filesystem $fs;

	protected function setUp(): void {
		$this->fs      = new Filesystem();
		$this->tempDir = sys_get_temp_dir() . '/progressive_image_test_' . uniqid();
		$this->fs->mkdir($this->tempDir);
	}

	protected function tearDown(): void {
		$this->fs->remove($this->tempDir);
		parent::tearDown();
	}

	public function testMetadataReaderInitialization(): void {
		self::bootKernel();

		$container      = self::getContainer();
		$metadataReader = $container->get(MetadataReader::class);

		$this->assertInstanceOf(MetadataReader::class, $metadataReader);
	}

	public function testGetMetadataForExistingFile(): void {
		$imagePath = $this->tempDir . '/test.png';
		$this->fs->copy(__DIR__ . '/../Fixtures/test.png', $imagePath);

		self::bootKernel([
			"progressive_image" => [
				'resolvers' => [
					'test' => [
						'type'  => 'filesystem',
						'roots' => [realpath($this->tempDir)]
					]
				],
				'resolver'  => 'test'
			]
		]);

		$metadataReader = self::getContainer()->get(MetadataReader::class);
		$metadata       = $metadataReader->getMetadata('test.png');

		$this->assertInstanceOf(ImageMetadata::class, $metadata);
		$this->assertNotEmpty($metadata->originalHash);
		$this->assertGreaterThan(0, $metadata->width);
		$this->assertGreaterThan(0, $metadata->height);
	}

	public function testGetMetadataThrowsExceptionForNonExistentFile(): void {
		self::bootKernel([
			"progressive_image" => [
				'resolvers' => [
					'test' => [
						'type'  => 'filesystem',
						'roots' => [realpath($this->tempDir)]
					]
				],
				'resolver'  => 'test'
			]
		]);

		$metadataReader = self::getContainer()->get(MetadataReader::class);

		$this->expectException(PathResolutionException::class);
		$metadataReader->getMetadata('non-existent.png');
	}

	public function testGetMetadataWithFallback(): void {
		$fallbackPath = $this->tempDir . '/fallback.png';
		$this->fs->copy(__DIR__ . '/../Fixtures/test.png', $fallbackPath);

		self::bootKernel([
			"progressive_image" => [
				'resolvers'      => [
					'test' => [
						'type'  => 'filesystem',
						'roots' => [$this->tempDir]
					]
				],
				'resolver'       => 'test',
				'fallback_image' => 'fallback.png'
			]
		]);

		/** @var MetadataReader $metadataReader */
		$metadataReader = self::getContainer()->get(MetadataReader::class);

		// non-existent.png doesn't exist, but it should return metadata for fallback.png
		$metadata = $metadataReader->getMetadata('non-existent.png');

		$this->assertInstanceOf(ImageMetadata::class, $metadata);
		$this->assertNotEmpty($metadata->originalHash);
	}
}
