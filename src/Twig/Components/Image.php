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

use Psr\Log\LoggerInterface;
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
    private array $breakpoints = [];

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
        private readonly ?LoggerInterface $logger = null,
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

        // Materialized once: $this->pathDecorator is typed `iterable` and may be a
        // one-shot \Generator, which would silently yield nothing on a second pass.
        $decorators = is_array($this->pathDecorator) ? $this->pathDecorator : iterator_to_array($this->pathDecorator, false);

        $this->decoratedSrc = $this->src;
        foreach ($decorators as $decorator) {
            $this->decoratedSrc = $decorator->decorate($this->decoratedSrc, $this->context);
        }

        try {
            $this->metadata = $this->analyzer->getMetadata($this->src);
        } catch (PathResolutionException $e) {
            $this->metadata = null;
            $this->logger?->warning('Could not resolve metadata for image "{src}": {message}', [
                'src' => $this->src,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
        $metadata = $this->metadata;
        $this->breakpoints = $this->sizes ? BreakpointAssignment::parseSegments($this->sizes, $this->ratio) : [];
        if ($this->breakpoints && $this->responsiveAttributeGenerator) {
            $context = $this->context;
            if ($this->filter) {
                $context['filter'] = $this->filter;
            }
            $metadataWidth = null !== $metadata ? $metadata->width : 0;
            $metadataHeight = null !== $metadata ? $metadata->height : 0;
            $this->responsiveAttributes = $this->responsiveAttributeGenerator->generate($this->src, $this->breakpoints, $metadataWidth, $this->preload, $this->pointInterest, $context, $this->retina, $metadataHeight);
        } else {
            $this->decoratedWidth = null !== $metadata ? $metadata->width : null;
            $this->decoratedHeight = null !== $metadata ? $metadata->height : null;
            foreach ($decorators as $decorator) {
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

    /**
     * @return array<string, string>
     */
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
