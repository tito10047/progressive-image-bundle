<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Service;

use Psr\Log\LoggerInterface;
use Tito10047\ProgressiveImageBundle\DTO\BreakpointAssignment;
use Tito10047\ProgressiveImageBundle\DTO\ResponsiveAttributes;
use Tito10047\ProgressiveImageBundle\DTO\ResponsiveAttributesInterface;
use Tito10047\ProgressiveImageBundle\DTO\ResponsiveSource;
use Tito10047\ProgressiveImageBundle\Modifier\ModifierProvider;
use Tito10047\ProgressiveImageBundle\UrlGenerator\ResponsiveImageUrlGeneratorInterface;

final class ResponsiveAttributeGenerator
{
    /**
     * @param array{
     *      layouts: array<string, array{
     *      min_viewport: int,
     *      max_container: int|null
     *      }>,
     *      columns: int
     *      } $gridConfig
     * @param array<string, string>                                 $ratioConfig
     * @param int[]                                                 $retinaMultipliers
     * @param array<string, array{mime: string, quality: int|null}> $pictureFormats    formats rendered as extra
     *                                                                                 <picture><source type="..."> candidates, most preferred first
     */
    public function __construct(
        private array $gridConfig,
        private array $ratioConfig,
        private readonly array $retinaMultipliers,
        private readonly PreloadCollector $preloadCollector,
        private ResponsiveImageUrlGeneratorInterface $urlGenerator,
        private readonly ?ModifierProvider $modifierProvider = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly int $fluidMaxWidth = 1920,
        private readonly array $pictureFormats = [],
    ) {
    }

    /**
     * @param BreakpointAssignment[] $assignments
     */
    public function generate(string $path, array $assignments, int $originalWidth, bool $preload, ?string $pointInterest = null, array $context = [], bool $retina = false, int $originalHeight = 0): ResponsiveAttributesInterface
    {
        $assignments = $this->sortAssignments($assignments);

        $sources = [];
        $variables = [];
        $defaultSource = null;

        // Collected across all breakpoints and sent as a single preload hint after the
        // loop, mirroring how a real <img srcset sizes> combines every candidate/condition
        // into one attribute pair instead of one <link rel=preload> per breakpoint.
        $preloadUrl = null;
        $preloadSrcsetParts = [];
        $preloadSizeParts = [];

        $originalRatio = $originalHeight > 0 ? $originalWidth / $originalHeight : null;

        foreach ($assignments as $assignment) {
            $layout = $this->gridConfig['layouts'][$assignment->breakpoint] ?? null;
            if (!$layout && 'default' === $assignment->breakpoint) {
                foreach ($this->gridConfig['layouts'] as $l) {
                    if (0 === $l['min_viewport']) {
                        $layout = $l;
                        break;
                    }
                }
            }

            if (!$layout) {
                throw new \InvalidArgumentException(sprintf('Breakpoint "%s" is not defined in the grid configuration.', $assignment->breakpoint));
            }

            [$pixelWidth, $sizeValue, $cssValue] = $this->calculateDimensions($assignment, $layout);

            $size = $this->formatSizePart($layout['min_viewport'], $sizeValue);

            $ratio = $this->resolveRatio($assignment) ?? $originalRatio;
            $assignmentContext = ($this->modifierProvider && $assignment->modifiers)
                ? $this->modifierProvider->applyModifiers($assignment->modifiers, $context)
                : $context;

            $multipliers = $retina ? $this->retinaMultipliers : [1];
            $media = $layout['min_viewport'] > 0 ? "(min-width: {$layout['min_viewport']}px)" : null;

            foreach ($this->pictureFormats as $formatId => $formatInfo) {
                $formatContext = $assignmentContext;
                $formatContext['format'] = $formatId;
                if (null !== $formatInfo['quality']) {
                    $formatContext['quality'] = $formatInfo['quality'];
                }

                [$formatSrcsetParts] = $this->buildSrcsetParts($path, $pixelWidth, $originalWidth, $pointInterest, $formatContext, $ratio, $multipliers);
                $sources[] = new ResponsiveSource($media, implode(', ', $formatSrcsetParts), $sizeValue, $formatInfo['mime']);
            }

            [$srcsetParts, $firstUrl] = $this->buildSrcsetParts($path, $pixelWidth, $originalWidth, $pointInterest, $assignmentContext, $ratio, $multipliers);

            if ($preload && $firstUrl && $srcsetParts) {
                $preloadUrl ??= $firstUrl;
                array_push($preloadSrcsetParts, ...$srcsetParts);
                $preloadSizeParts[] = $size;
            }

            $suffix = '-'.$assignment->breakpoint;
            $variables['--img-width'.$suffix] = $cssValue;
            if (0 === $layout['min_viewport']) {
                $variables['--img-width'] = $cssValue;
                if ($ratio) {
                    $variables['--img-aspect'] = (string) $ratio;
                }
            }
            if ($ratio) {
                $variables['--img-aspect'.$suffix] = (string) $ratio;
            }

            $srcset = implode(', ', $srcsetParts);
            $source = new ResponsiveSource($media, $srcset, $sizeValue);

            if (null === $media) {
                $defaultSource = $source;
            } else {
                $sources[] = $source;
            }
        }

        if ($preloadUrl) {
            $this->preloadCollector->add($preloadUrl, 'image', 'high', implode(', ', $preloadSrcsetParts), implode(', ', $preloadSizeParts));
        }

        if (null === $defaultSource) {
            $this->logger?->warning('No breakpoint with min_viewport 0 resolved for image "{path}": the <img> fallback source will have an empty srcset/sizes.', ['path' => $path]);
            $defaultSource = new ResponsiveSource(null, '', '');
        }

        return new ResponsiveAttributes($sources, $defaultSource, $variables);
    }

    private function formatSizePart(int $minViewport, string $sizeValue): string
    {
        return $minViewport > 0
            ? "(min-width: {$minViewport}px) {$sizeValue}"
            : $sizeValue;
    }

    /**
     * @param BreakpointAssignment[] $assignments
     *
     * @return BreakpointAssignment[]
     */
    private function sortAssignments(array $assignments): array
    {
        usort($assignments, fn ($a, $b) => ($this->gridConfig['layouts'][$b->breakpoint]['min_viewport'] ?? 0) <=>
            ($this->gridConfig['layouts'][$a->breakpoint]['min_viewport'] ?? 0)
        );

        return $assignments;
    }

    /**
     * @param array{min_viewport: int, max_container: int|null} $layout
     *
     * @return array{0: float, 1: string, 2: string}
     */
    private function calculateDimensions(BreakpointAssignment $assignment, array $layout): array
    {
        if (null !== $assignment->widthPercent) {
            $percentValue = (float) rtrim($assignment->widthPercent, '%');
            $cssValue = $assignment->widthPercent;

            $maxContainer = $layout['max_container'];
            if ($maxContainer) {
                $pixelWidth = ($percentValue / 100) * $maxContainer;
            } else {
                $pixelWidth = ($percentValue / 100) * $this->fluidMaxWidth;
            }

            return [$pixelWidth, round($pixelWidth).'px', $cssValue];
        }

        if (null !== $assignment->width) {
            $pixelWidth = (float) $assignment->width;
            $sizeValue = $assignment->width.'px';

            return [$pixelWidth, $sizeValue, $sizeValue];
        }

        $totalCols = $this->gridConfig['columns'];
        $maxContainer = $layout['max_container'];

        if ($maxContainer) {
            // Fixed container (e.g. 1320px) -> width in px
            $pixelWidth = ($assignment->columns / $totalCols) * $maxContainer;
            $sizeValue = round($pixelWidth).'px';
        } else {
            // Fluid (null) -> width in vw
            $vwWidth = ($assignment->columns / $totalCols) * 100;
            $sizeValue = round($vwWidth).'vw';
            // For URL calculation we estimate px width from a configurable assumed max
            // viewport width (see $fluidMaxWidth).
            $pixelWidth = ($vwWidth / 100) * $this->fluidMaxWidth;
        }

        return [$pixelWidth, $sizeValue, $sizeValue];
    }

    /**
     * @param array<string, mixed> $context
     * @param int[]                $multipliers
     *
     * @return array{0: string[], 1: ?string} [srcsetParts ("url Nw" strings), firstUrl]
     */
    private function buildSrcsetParts(
        string $path,
        float $pixelWidth,
        int $originalWidth,
        ?string $pointInterest,
        array $context,
        ?float $ratio,
        array $multipliers,
    ): array {
        $srcsetParts = [];
        $firstUrl = null;

        foreach ($multipliers as $multiplier) {
            $mPixelWidth = (int) round($pixelWidth * $multiplier);
            $url = $this->generateUrl($path, $mPixelWidth, $originalWidth, $pointInterest, $context, $ratio);

            if ($url) {
                if (null === $firstUrl) {
                    $firstUrl = $url;
                }
                $srcsetParts[] = $url." {$mPixelWidth}w";
            }
        }

        return [$srcsetParts, $firstUrl];
    }

    private function generateUrl(
        string $path,
        int $basePixelWidth,
        int $originalWidth,
        ?string $pointInterest,
        array $context,
        ?float $ratio,
    ): string {
        if ($originalWidth > 0 && $basePixelWidth > $originalWidth) {
            $basePixelWidth = $originalWidth;
        }

        $targetH = $ratio ? (int) round($basePixelWidth / $ratio) : null;

        return $this->urlGenerator->generateUrl($path, $basePixelWidth, $targetH, $pointInterest, $context);
    }

    private function resolveRatio(BreakpointAssignment $assignment): ?float
    {
        $ratioString = $assignment->ratio ?? null;
        if (!$ratioString) {
            return null;
        }

        // If it's a key in ratioConfig, use that
        if (isset($this->ratioConfig[$ratioString])) {
            $ratioString = $this->ratioConfig[$ratioString];
        }

        if (is_numeric($ratioString)) {
            return (float) $ratioString;
        }

        // Otherwise try to parse format "16/9" or "3-4"
        if (preg_match('/^(\d+)[\/-](\d+)$/', $ratioString, $matches)) {
            return (float) $matches[1] / (float) $matches[2];
        }

        // Or format "400x500"
        if (preg_match('/^(\d+)x(\d+)$/', $ratioString, $matches)) {
            return (float) $matches[1] / (float) $matches[2];
        }

        throw new \InvalidArgumentException(sprintf('Invalid ratio format or missing ratio configuration for: "%s"', $ratioString));
    }
}
