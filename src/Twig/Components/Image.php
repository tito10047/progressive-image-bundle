<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tito10047\ProgressiveImageBundle\Twig\Components;

use Tito10047\ProgressiveImageBundle\DTO\BreakpointAssignment;
use Tito10047\ProgressiveImageBundle\Service\ResponsiveAttributeGenerator;
use Tito10047\ProgressiveImageBundle\SrcsetGenerator\SrcsetGeneratorInterface;
use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;
use Tito10047\ProgressiveImageBundle\Decorators\PathDecoratorInterface;
use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;
use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;
use Tito10047\ProgressiveImageBundle\ProgressiveImageBundle;
use Tito10047\ProgressiveImageBundle\Service\MetadataReaderInterface;

#[AsTwigComponent]
final class Image {


	public ?string         $src = null;
	public ?string         $filter = null;
	public ?string         $alt = null;
	public ?string         $pointInterest = null;
	public array           $context = [];
	private ?ImageMetadata $metadata;
	private string         $decoratedSrc;
	private ?int           $decoratedWidth;
	private ?int           $decoratedHeight;
	public bool            $preload = false;
	public string  $priority = 'high';
	public ?string $sizes    = null;
	public ?string $ratio    = null;
	private array          $breakpoinsts = [];
	/**
	 * @var array{sizes: string, srcset: string}|null
	 */
	private ?array         $responsiveAttributes = null;

	/**
	 * @param iterable<PathDecoratorInterface> $pathDecorator
	 * @param $defaultPreset null|array{
	 *     widths: array<string, int>,
	 *     sizes: string
	 * }
	 * @param $presets null|array<string,array{
	 *      widths: array<string, int>,
	 *      sizes: string
	 *  }>
	 */
	public function __construct(
		private readonly MetadataReaderInterface $analyzer,
		private readonly iterable       $pathDecorator,
		private readonly ?ResponsiveAttributeGenerator $responsiveAttributeGenerator,
		private readonly PreloadCollector            $preloadCollector,
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
		$this->breakpoinsts = $this->sizes ? BreakpointAssignment::parseSegments($this->sizes, $this->ratio) : [];
		if ($this->breakpoinsts) {
			$this->responsiveAttributes = $this->responsiveAttributeGenerator?->generate($this->src, $this->breakpoinsts, $this->decoratedWidth ?? 0, $this->preload, $this->pointInterest);
		}elseif ($this->preload){
			$this->preloadCollector->add($this->decoratedSrc, "image", $this->priority);
		}
	}

	public function getSrcset():string {
		if (!$this->responsiveAttributes){
			return "";
		}
		return "srcset=\"{$this->responsiveAttributes['srcset']}\"";
	}

	public function getResponsiveSizes():string {
		if (!$this->responsiveAttributes){
			return "";
		}
		return "sizes=\"{$this->responsiveAttributes["sizes"]}\"";
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
