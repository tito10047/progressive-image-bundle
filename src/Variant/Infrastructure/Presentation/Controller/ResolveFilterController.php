<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Application\Handler\GenerateVariantHandler;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\OriginalUrlResolver;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\VariantSpecFactory;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\SourcePath;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Variant;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\VariantStorage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;

/**
 * The Liip-style on-the-fly resolve route: GET a (filterSet, path) pair, block until the
 * variant is generated, redirect to it. Deliberately unsigned, unlike ImageVariantController
 * — the spec is derived only from a named filter_sets entry (never arbitrary attacker-chosen
 * filter params), so there is no way to make this endpoint do anything other than generate
 * one of the filter sets the app owner already configured, for whatever source path the
 * caller names. That's the same trust boundary Liip's classic
 * /media/cache/resolve/<filter>/<path> route has always had.
 *
 * Always redirects, never streams — the next hit goes straight to the storage's public URL
 * without touching PHP again, same as ImageVariantController.
 */
final readonly class ResolveFilterController
{
    public function __construct(
        private VariantSpecFactory $specFactory,
        private VariantIdHasher $hasher,
        private VariantStorage $storage,
        private GenerateVariantHandler $generateHandler,
        private OriginalUrlResolver $originalUrlResolver,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function resolve(string $filterSet, string $path): RedirectResponse
    {
        $sourcePath = new SourcePath($path);
        $spec = $this->specFactory->createFromFilterSet($filterSet);
        $variant = Variant::request($sourcePath, $spec, $this->hasher);
        $variantPath = $variant->path();

        if (!$this->storage->exists($variantPath)) {
            try {
                ($this->generateHandler)(new GenerateVariant($sourcePath, $spec));
            } catch (\Throwable $e) {
                // GenerateVariantHandler already recorded the fail marker and published
                // VariantGenerationFailed — this falls through to the original-image
                // fallback below, but the failure itself must still be visible somewhere,
                // even in an app that never registered a PSR-3 logger service (the DI
                // wiring uses IGNORE_ON_INVALID_REFERENCE, so $this->logger can be null).
                if ($this->logger) {
                    $this->logger->warning('Synchronous variant generation failed; falling back to the original image.', [
                        'source' => $sourcePath->value,
                        'filterSet' => $filterSet,
                        'exception' => $e,
                    ]);
                } else {
                    error_log(sprintf('Synchronous variant generation failed for source "%s" (filter "%s"): %s', $sourcePath->value, $filterSet, $e->getMessage()));
                }
            }
        }

        if ($this->storage->exists($variantPath)) {
            return new RedirectResponse($this->storage->publicPath($variantPath), 302, ['Cache-Control' => 'no-store, must-revalidate']);
        }

        return new RedirectResponse($this->originalUrlResolver->resolve($sourcePath), 302, ['Cache-Control' => 'no-store, must-revalidate']);
    }
}
