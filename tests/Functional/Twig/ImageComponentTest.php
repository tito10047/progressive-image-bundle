<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 7. 2024
 * Time: 16:03
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\Twig;


use Symfony\Component\Filesystem\Filesystem;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Tito10047\ProgressiveImageBundle\ProgressiveImageBundle;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;
use Tito10047\ProgressiveImageBundle\Twig\Components\Image;

class ImageComponentTest extends PGITestCase {

	use InteractsWithTwigComponents;

	private string     $tempDir;
	private Filesystem $fs;

	protected function setUp(): void {
		$this->fs      = new Filesystem();
		$this->tempDir = sys_get_temp_dir() . '/progressive_image_test_' . uniqid();
		$this->fs->mkdir($this->tempDir);
	}

	function testDefaultRendered() {
		self::bootKernel();

		$component = $this->mountTwigComponent(
			name: 'pgi:Image',
			data: [
				"src" => "/foo.jpg"
			]
		);

		$this->assertInstanceOf(Image::class, $component);
		$this->assertSame("/foo.jpg", $component->src);
	}

	public function testGenerateHash(): void {
		$this->_bootKernel();

		/** @var Image $component */
		$component = $this->mountTwigComponent(
			name: 'pgi:Image',
			data: [
				"src" => "/test.png"
			]
		);
		$this->assertInstanceOf(Image::class, $component);

		$this->assertNotEmpty($component->getHash());
		$this->assertGreaterThan(0, $component->getWidth());
		$this->assertGreaterThan(0, $component->getHeight());
	}

	public function testRender(): void {
		$this->bootKernel();

		$html = $this->renderTwigComponent(
			name: "pgi:Image",
			data: [
				"src" => "/test.png",
				"alt" => "test image"
			]
		);

		$stimulus = ProgressiveImageBundle::STIMULUS_CONTROLLER;

		$this->assertStringContainsString("data-{$stimulus}-src-value=\"/test.png\"", $html);
		$this->assertStringContainsString("data-controller=\"{$stimulus}\"", $html);
		$this->assertStringContainsString("data-{$stimulus}-target=\"placeholder\"", $html);
		$this->assertStringContainsString("data-{$stimulus}-target=\"highRes\"", $html);
		$this->assertStringContainsString("data-{$stimulus}-target=\"errorOverlay\"", $html);
	}

	private function _bootKernel(): void {
		$imagePath = $this->tempDir . '/test.png';
		$this->fs->copy(__DIR__ . '/../../Fixtures/test.png', $imagePath);

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
	}
}
