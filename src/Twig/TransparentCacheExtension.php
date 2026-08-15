<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Twig;

use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\PendingGenerationTracker;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class TransparentCacheExtension extends AbstractExtension
{
    public function __construct(
        private readonly ?TagAwareCacheInterface $cache,
        private readonly ?int $ttl,
        private readonly ?PendingGenerationTracker $tracker = null,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('pgi_cache_save', [$this, 'saveToCache'], ['is_safe' => ['html']]),
        ];
    }

    public function saveToCache(string $content, string $key, ?string $tag = null, ?int $ttl = null): string
    {
        if (!$this->cache) {
            return $content;
        }

        if (true === $this->tracker?->hasPending()) {
            // A variant referenced by this fragment is still generating — caching it now
            // would freeze the pending state (original-image fallback, no-store headers)
            // into the fragment cache long after the real image is ready.
            return $content;
        }

        $this->cache->get($key, function (ItemInterface $item) use ($content, $tag, $ttl) {
            $effectiveTtl = $ttl ?? $this->ttl;
            if ($effectiveTtl) {
                $item->expiresAfter($effectiveTtl);
            }
            if ($tag) {
                $item->tag($tag);
            }

            return $content;
        });

        return $content;
    }
}
