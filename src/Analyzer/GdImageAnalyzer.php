<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tito10047\ProgressiveImageBundle\Analyzer;

use kornrunner\Blurhash\Blurhash;
use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;
use Tito10047\ProgressiveImageBundle\Exception\ImageProcessingException;
use Tito10047\ProgressiveImageBundle\Loader\LoaderInterface;

final class GdImageAnalyzer implements ImageAnalyzerInterface {

	public function __construct(
		private readonly int $componentsX = 4,
		private readonly int $componentsY = 3
	) {
	}

	public function analyze(LoaderInterface $loader, string $path): ImageMetadata {
		$stream = $loader->load($path);
		$data   = stream_get_contents($stream);

		if ($data === false) {
			throw new ImageProcessingException("Nepodarilo sa načítať dáta z loadera pre cestu: " . $path);
		}

		$image = @imagecreatefromstring($data);
		if ($image === false) {
			throw new ImageProcessingException("GD nedokázal načítať obrázok z dát pre cestu: " . $path);
		}

		$width  = imagesx($image);
		$height = imagesy($image);

		// Zmenšenie na max 64x64 pre Blurhash (zachovanie pomeru strán)
		$targetWidth  = 64;
		$targetHeight = 64;

		if ($width > $height) {
			$targetHeight = (int) ($height * (64 / $width));
		} else {
			$targetWidth = (int) ($width * (64 / $height));
		}

		$resizedImage = imagecreatetruecolor($targetWidth, $targetHeight);
		imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

		$pixels = [];
		for ($y = 0; $y < $targetHeight; $y++) {
			$row = [];
			for ($x = 0; $x < $targetWidth; $x++) {
				$rgb   = imagecolorat($resizedImage, $x, $y);
				$r     = ($rgb >> 16) & 0xFF;
				$g     = ($rgb >> 8) & 0xFF;
				$b     = $rgb & 0xFF;
				$row[] = [$r, $g, $b];
			}
			$pixels[] = $row;
		}

		$hash = Blurhash::encode($pixels, $this->componentsX, $this->componentsY);

		return new ImageMetadata(
			$hash,
			$width,
			$height
		);
	}
}