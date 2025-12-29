<?php

namespace Tito10047\ProgressiveImageBundle\DTO;

class ImageMetadata
{
	public function __construct(
		public readonly string $originalHash,
		public readonly int $width,
		public readonly int $height,
	) {}
}