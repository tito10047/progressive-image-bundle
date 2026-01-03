<?php

namespace Tito10047\ProgressiveImageBundle\DTO;

final class ImageMetadata
{
	public function __construct(
		public readonly string $originalHash,
		public readonly int $width,
		public readonly int $height,
	) {}
}