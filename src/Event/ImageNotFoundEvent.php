<?php

namespace Tito10047\ProgressiveImageBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ImageNotFoundEvent extends Event
{
	public const NAME = 'progressive_image.not_found';

	public function __construct(
		private readonly string    $path,
		private readonly string    $loaderClass,
		private readonly \DateTimeImmutable $occurredAt = new \DateTimeImmutable()
	) {}

	public function getPath(): string { return $this->path; }
	public function getLoaderClass(): string { return $this->loaderClass; }
	public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}