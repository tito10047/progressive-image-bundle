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

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class TransparentCacheExtension extends AbstractExtension
{
    public function __construct(
        private readonly ?TagAwareCacheInterface $cache,
        private readonly ?int $ttl
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('pgi_cache_save', [$this, 'saveToCache'], ['is_safe' => ['html']]),
        ];
    }

    public function saveToCache(string $content, string $key, ?string $tag = null): string
    {
        if (!$this->cache) {
            return $content;
        }

        $this->cache->get($key, function (ItemInterface $item) use ($content, $tag) {
            if ($this->ttl) {
                $item->expiresAfter($this->ttl);
            }
            if ($tag) {
                $item->tag($tag);
            }
            return $content;
        });

        return $content;
    }
}
