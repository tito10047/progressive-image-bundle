<?php

namespace Tito10047\ProgressiveImageBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TransparentCacheExtension extends AbstractExtension
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ?int $ttl
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('pgi_cache_save', [$this, 'saveToCache'], ['is_safe' => ['html']]),
        ];
    }

    public function saveToCache(string $content, string $key): string
    {
        $this->cache->get($key, function (ItemInterface $item) use ($content) {
            if ($this->ttl) {
                $item->expiresAfter($this->ttl);
            }
            return $content;
        });

        return $content;
    }
}
