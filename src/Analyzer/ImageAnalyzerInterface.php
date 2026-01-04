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
 * Responsible for extracting technical information from an image.
 */
interface ImageAnalyzerInterface
{
    /**
	 * Analyzes an image and returns a metadata object (dimensions, hash, etc.).
     *
	 * @throws PathResolutionException       If the path does not exist
	 * @throws UnsupportedImageTypeException If the format is not supported
     */
    public function analyze(LoaderInterface $loader, string $path): ImageMetadata;
}
