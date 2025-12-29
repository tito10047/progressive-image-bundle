<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 7. 2024
 * Time: 16:03
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Functional\Twig;


use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Tito10047\ProgressiveImageBundle\Tests\Integration\PGITestCase;
use Tito10047\ProgressiveImageBundle\Twig\Components\Image;

class ImageComponentTest extends PGITestCase {

	use InteractsWithTwigComponents;


	function testDefaultRendered() {
		self::bootKernel([

		]);

		$component = $this->mountTwigComponent(
			name: 'pgi:Image',
			data:[
				"src"=>"/foo.jpg"
			]
		);

		$this->assertInstanceOf(Image::class, $component);
		$this->assertSame("/foo.jpg", $component->src);
	}
}
