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
	public array           $context = [];
	private ?ImageMetadata $metadata;
	private string         $decoratedSrc;
	private ?int           $decoratedWidth;
	private ?int           $decoratedHeight;

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
		$this->decoratedSrc = $this->src;
		$this->decoratedWidth = $this->metadata?->width;
		$this->decoratedHeight = $this->metadata?->height;
		foreach ($this->pathDecorator as $decorator) {
			$this->decoratedSrc = $decorator->decorate($this->decoratedSrc, $this->context);
			$size = $decorator->getSize($this->decoratedSrc, $this->context);
			if ($size){
				$this->decoratedWidth = $size["width"];
				$this->decoratedHeight = $size["height"];
			}
		}
	}

	public function getHash(): ?string {
		return $this->metadata?->originalHash;
	}

	public function getWidth():?int {
		return $this->decoratedWidth??$this->metadata?->width;
	}

	public function getHeight():?int {
		return $this->decoratedHeight??$this->metadata?->height;
	}

	public function getDecoratedSrc(): string {
		return $this->decoratedSrc??$this->src;
	}

	public function getController(): ?string {
		return ProgressiveImageBundle::STIMULUS_CONTROLLER;
	}
}
