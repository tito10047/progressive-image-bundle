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

namespace Tito10047\ProgressiveImageBundle\Variant\Application\Handler;

use Tito10047\ProgressiveImageBundle\Variant\Application\Command\GenerateVariant;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\GenerationDispatcher;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\OriginalUrlResolver;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\ResolvedUrl;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\ResolveFilterUrl;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\PendingGenerationTracker;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\VariantSpecFactory;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Variant;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\VariantStorage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;

/**
 * Sibling of ResolveVariantUrlHandler for the no-breakpoint, filter-set-only case
 * (pgi_filter(), the on-the-fly resolve route). Deliberately simpler: there is no
 * fallback_while_pending="wait" here — the signed "wait" endpoint's contract is to rebuild
 * the exact spec from (width, height, filterSet, ...) via VariantSpecFactory::create(),
 * which this handler never calls, so it always falls back to the original URL while a
 * variant is generating. That fits this handler's use cases anyway (API responses,
 * og:image, emails): there is no page render to block/retry against.
 *
 * Same statefulness caveat as ResolveVariantUrlHandler: infrastructure must wire this as
 * non-shared, never as a cross-request singleton.
 */
final class ResolveFilterUrlHandler
{
    /** @var array<string, ResolvedUrl> */
    private array $memo = [];

    public function __construct(
        private readonly VariantSpecFactory $specFactory,
        private readonly VariantIdHasher $hasher,
        private readonly VariantStorage $storage,
        private readonly PendingGenerationTracker $tracker,
        private readonly GenerationDispatcher $dispatcher,
        private readonly OriginalUrlResolver $originalUrlResolver,
    ) {
    }

    public function __invoke(ResolveFilterUrl $query): ResolvedUrl
    {
        // See ResolveVariantUrlHandler's identical check for why: SVGs are never rasterized,
        // and skipping straight to the original avoids permanently poisoning the response
        // into no-store via PendingGenerationTracker.
        if ($query->source->isSvg()) {
            return new ResolvedUrl($this->originalUrlResolver->resolve($query->source), false);
        }

        $spec = $this->specFactory->createFromFilterSet($query->filterSet, $query->context);
        $variant = Variant::request($query->source, $spec, $this->hasher);

        if (isset($this->memo[$variant->id->value])) {
            return $this->memo[$variant->id->value];
        }

        return $this->memo[$variant->id->value] = $this->resolve($variant);
    }

    private function resolve(Variant $variant): ResolvedUrl
    {
        $path = $variant->path();

        if ($this->storage->exists($path)) {
            return new ResolvedUrl($this->storage->publicPath($path), false);
        }

        $this->dispatcher->dispatch(new GenerateVariant($variant->source, $variant->spec));

        if ($this->storage->exists($path)) {
            return new ResolvedUrl($this->storage->publicPath($path), false);
        }

        $this->tracker->markPending($variant->id);

        return new ResolvedUrl($this->originalUrlResolver->resolve($variant->source), true);
    }
}
