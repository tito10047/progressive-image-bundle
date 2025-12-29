<?php

namespace Tito10047\ProgressiveImageBundle\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;
use Tito10047\ProgressiveImageBundle\Decorators\PathDecoratorInterface;
use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;
use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;
use Tito10047\ProgressiveImageBundle\ProgressiveImageBundle;
use Tito10047\ProgressiveImageBundle\Service\MetadataReader;

#[AsTwigComponent]
class Image {


	public ?string         $src = null;
	public ?string         $filter = null;
	public ?string         $alt = null;
	private ?ImageMetadata $metadata;

	/**
	 * @param iterable<PathDecoratorInterface> $pathDecorator
	 */
	public function __construct(
		private readonly MetadataReader $analyzer,
		private readonly iterable       $pathDecorator,
	) {
	}

	#[PostMount]
	public function postMount(): void {
		try {
			$this->metadata = $this->analyzer->getMetadata($this->src);
		}catch (PathResolutionException){
			$this->metadata = null;
		}
	}

	public function getHash(): ?string {
		return $this->metadata?->originalHash;
	}

	public function getWidth():?int {
		return $this->metadata?->width;
	}

	public function getHeight():?int {
		return $this->metadata?->height;
	}

	public function getDecoratedSrc(): string {
		$src = $this->src;
		foreach ($this->pathDecorator as $decorator) {
			$src = $decorator->decorate($src);
		}
		return $src;
	}

	public function getController(): ?string {
		return ProgressiveImageBundle::STIMULUS_CONTROLLER;
	}
}
