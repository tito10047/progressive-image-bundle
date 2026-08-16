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
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\PendingUrlBuilder;
use Tito10047\ProgressiveImageBundle\Variant\Application\Port\UrlSigner;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\PendingFallbackStrategy;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\ResolvedUrl;
use Tito10047\ProgressiveImageBundle\Variant\Application\Query\ResolveVariantUrl;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\PendingGenerationTracker;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\VariantSpecFactory;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Model\Variant;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Port\VariantStorage;
use Tito10047\ProgressiveImageBundle\Variant\Domain\Service\VariantIdHasher;

/**
 * The hot path — runs on every render. Stateful by design: it memoizes per VariantId for
 * its own lifetime so that rendering the same image twice on one page dispatches
 * generation once, not twice. Infrastructure must wire this as a non-shared (or
 * request-scoped) service, never as a singleton shared across requests.
 */
final class ResolveVariantUrlHandler
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
        private readonly ?PendingUrlBuilder $pendingUrlBuilder,
        private readonly UrlSigner $urlSigner,
        private readonly PendingFallbackStrategy $fallback,
    ) {
    }

    public function __invoke(ResolveVariantUrl $query): ResolvedUrl
    {
        // SVGs are never rasterized (already scalable, and Intervention Image can't decode
        // them anyway) — resolving straight to the original avoids both pointless repeated
        // failed-generation attempts and, more importantly, permanently poisoning the page's
        // HTTP cache: without this, resolve() would report "pending" forever for an SVG, and
        // ResponseCacheOverrideListener forces every such response to no-store.
        if ($query->source->isSvg()) {
            return new ResolvedUrl($this->originalUrlResolver->resolve($query->source), false);
        }

        $spec = $this->specFactory->create(
            $query->width,
            $query->height,
            $query->filterSet,
            $query->poi,
            $query->originalDimensions,
            $query->context
        );

        $variant = Variant::request($query->source, $spec, $this->hasher);

        if (isset($this->memo[$variant->id->value])) {
            return $this->memo[$variant->id->value];
        }

        return $this->memo[$variant->id->value] = $this->resolve($variant, $query);
    }

    private function resolve(Variant $variant, ResolveVariantUrl $query): ResolvedUrl
    {
        $path = $variant->path();

        if ($this->storage->exists($path)) {
            return new ResolvedUrl($this->storage->publicPath($path), false);
        }

        $this->dispatcher->dispatch(new GenerateVariant($variant->source, $variant->spec));

        // A synchronous dispatcher (generation.strategy: sync) may have already produced
        // the variant by the time dispatch() returns — async/terminate dispatchers never
        // do (they only ever queue the work), so this check is a no-op for them, but for
        // sync it's the difference between serving the real generated image on the very
        // first request and needlessly falling back to the original for one extra request.
        if ($this->storage->exists($path)) {
            return new ResolvedUrl($this->storage->publicPath($path), false);
        }

        // Only mark (and therefore force the response to no-store, see
        // ResponseCacheOverrideListener) once it's confirmed the variant is still not
        // ready — a page whose sync generation just succeeded above has nothing pending
        // and can be cached normally.
        $this->tracker->markPending($variant->id);

        $url = match ($this->fallback) {
            PendingFallbackStrategy::Original => $this->originalUrlResolver->resolve($variant->source),
            PendingFallbackStrategy::Wait => $this->urlSigner->sign(
                ($this->pendingUrlBuilder ?? throw new \LogicException('fallback_while_pending is "wait" but no PendingUrlBuilder was configured.'))
                    ->build($query)
            ),
        };

        return new ResolvedUrl($url, true);
    }
}
