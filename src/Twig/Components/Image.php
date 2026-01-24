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

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;
use Tito10047\ProgressiveImageBundle\Decorators\PathDecoratorInterface;
use Tito10047\ProgressiveImageBundle\DTO\BreakpointAssignment;
use Tito10047\ProgressiveImageBundle\DTO\ImageMetadata;
use Tito10047\ProgressiveImageBundle\DTO\ResponsiveAttributesInterface;
use Tito10047\ProgressiveImageBundle\Exception\PathResolutionException;
use Tito10047\ProgressiveImageBundle\ProgressiveImageBundle;
use Tito10047\ProgressiveImageBundle\Service\MetadataReaderInterface;
use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;
use Tito10047\ProgressiveImageBundle\Service\ResponsiveAttributeGenerator;

#[AsTwigComponent]
final class Image
{
    public ?string $src = null;
    public ?string $filter = null;
    public ?string $alt = null;
    public ?string $pointInterest = null;
    /**
     * @var array<string, mixed>
     */
    public array $context = [];
    private ?ImageMetadata $metadata = null;
    private ?string $decoratedSrc = null;
    private ?int $decoratedWidth = null;
    private ?int $decoratedHeight = null;
    public bool $preload = false;
    public ?int $ttl = null;
    public string $priority = 'high';
    public ?string $sizes = null;
    public ?string $ratio = null;
    public ?bool $retina = null;
    /**
     * @var BreakpointAssignment[]
     */
    private array $breakpoinsts = [];

    private ?ResponsiveAttributesInterface $responsiveAttributes = null;

    /**
     * @param iterable<PathDecoratorInterface> $pathDecorator
     */
    public function __construct(
        private readonly MetadataReaderInterface $analyzer,
        private readonly iterable $pathDecorator,
        private readonly ?ResponsiveAttributeGenerator $responsiveAttributeGenerator,
        private readonly PreloadCollector $preloadCollector,
        private readonly string $framework = 'custom',
        private readonly bool $defaultRetina = true,
    ) {
    }

    #[PostMount]
    public function postMount(): void
    {
        if (null === $this->retina) {
            $this->retina = $this->defaultRetina;
        }

        if (!$this->src) {
            return;
        }

        $this->decoratedSrc = $this->src;
        foreach ($this->pathDecorator as $decorator) {
            $this->decoratedSrc = $decorator->decorate($this->decoratedSrc, $this->context);
        }

        try {
            $this->metadata = $this->analyzer->getMetadata($this->src);
        } catch (PathResolutionException) {
            $this->metadata = null;
        }
        $this->breakpoinsts = $this->sizes ? BreakpointAssignment::parseSegments($this->sizes, $this->ratio) : [];
        if ($this->breakpoinsts && $this->responsiveAttributeGenerator) {
            $context = $this->context;
            if ($this->filter) {
                $context['filter'] = $this->filter;
            }
            $this->responsiveAttributes = $this->responsiveAttributeGenerator->generate($this->src, $this->breakpoinsts, $this->metadata->width ?? 0, $this->preload, $this->pointInterest, $context, $this->retina);
        } else {
            $this->decoratedWidth = $this->metadata?->width;
            $this->decoratedHeight = $this->metadata?->height;
            foreach ($this->pathDecorator as $decorator) {
                $size = $decorator->getSize($this->decoratedSrc, $this->context);
                if ($size) {
                    $this->decoratedWidth = $size['width'];
                    $this->decoratedHeight = $size['height'];
                }
            }

            if ($this->preload) {
                $this->preloadCollector->add($this->decoratedSrc, 'image', $this->priority);
            }
        }
    }

    public function getSrcset(): string
    {
        if (!$this->responsiveAttributes) {
            return '';
        }

        return "srcset=\"{$this->responsiveAttributes->getDefaultSource()->getSrcset()}\"";
    }

    public function getResponsiveSizes(): string
    {
        if (!$this->responsiveAttributes) {
            return '';
        }

        return "sizes=\"{$this->responsiveAttributes->getDefaultSource()->getSizes()}\"";
    }

    public function getResponsiveAttributes(): ?ResponsiveAttributesInterface
    {
        return $this->responsiveAttributes;
    }

    public function hasResponsiveAttributes(): bool
    {
        return null !== $this->responsiveAttributes;
    }

    public function getHash(): ?string
    {
        return $this->metadata?->originalHash;
    }

    public function getWidth(): ?int
    {
        return $this->decoratedWidth;
    }

    public function getHeight(): ?int
    {
        return $this->decoratedHeight;
    }

    public function getVariables(): array
    {
        return $this->responsiveAttributes?->getVariables() ?? [];
    }

    public function getDecoratedSrc(): ?string
    {
        return $this->decoratedSrc ?? $this->src;
    }

    public function getController(): string
    {
        return ProgressiveImageBundle::STIMULUS_CONTROLLER;
    }

    public function getFramework(): string
    {
        return $this->framework;
    }
}
