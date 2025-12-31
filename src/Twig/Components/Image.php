<?php

namespace Tito10047\ProgressiveImageBundle\Twig\Components;

use Tito10047\ProgressiveImageBundle\SrcsetGenerator\SrcsetGeneratorInterface;
use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;
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
	public bool $preload = false;
	public string $priority = 'high';
	public ?string $preset = null;

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
		private readonly MetadataReader $analyzer,
		private readonly iterable       $pathDecorator,
		private readonly PreloadCollector $preloadCollector,
		private readonly ?SrcsetGeneratorInterface $srcsetGenerator,
		private ?array $breakpoints,
		private ?array $defaultPreset,
		private ?array $presets,
	) {
	}

	#[PostMount]
	public function postMount(): void {
		try {
			$this->metadata = $this->analyzer->getMetadata($this->src);
		}catch (PathResolutionException){
			$this->metadata = null;
		}
		$this->context["filter"] ??= $this->preset;
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
		if ($this->preload){
			$this->preloadCollector->add($this->decoratedSrc,"image",$this->priority);
		}
	}

	public function getSrcSet():string {
		if (!$this->srcsetGenerator){
			return '';
		}
		$presetName = $this->preset ?? '';
		$preset = $this->presets[$presetName] ?? $this->defaultPreset;
		if (empty($preset['widths'])) {
			return '';
		}

		$breakpoints = [];
		foreach ($preset['widths'] as $name) {
			if (isset($this->breakpoints[$name])) {
				$breakpoints[$name] = $this->breakpoints[$name];
			}
		}

		if (empty($breakpoints)) {
			return '';
		}

		$urls = $this->srcsetGenerator->generate(
			$this->src,
			$breakpoints,
			$this->context
		);
		$src="";
		foreach ($urls as $breakpoint=>$url){
			$width = $breakpoints[$breakpoint];
			$src.="\n{$url} {$width}w,";
		}
		$src = rtrim($src,",");
		return $src ? "srcset=\"$src\"" : "";
	}

	public function getSizes():string {
		$presetName = $this->preset ?? '';
		$preset = $this->presets[$presetName] ?? $this->defaultPreset;
		$sizes = $preset["sizes"] ?? null;
		if (!$sizes){
			return '';
		}
		return "sizes=\"{$sizes}\"";
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
