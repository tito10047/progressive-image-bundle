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

use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;
use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;
use Tito10047\ProgressiveImageBundle\Exception\UnsupportedImageTypeException;
use Tito10047\ProgressiveImageBundle\Loader\LoaderInterface;

/**
 * Zodpovedá za extrakciu technických informácií z obrázka.
 */
interface ImageAnalyzerInterface
{
	/**
	 * Analyzuje obrázok a vráti objekt s metadátami (rozmery, hash, atď.)
	 * @throws PathResolutionException Ak cesta neexistuje
	 * @throws UnsupportedImageTypeException Ak formát nie je podporovaný
	 */
	public function analyze(LoaderInterface $loader, string $path): ImageMetadata;
}