<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\UX\TwigComponent\Event\PreCreateForRenderEvent;
use Symfony\UX\TwigComponent\Event\PreRenderEvent;

final class TransparentImageCacheSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ?TagAwareCacheInterface $cache,
        private readonly bool $enabled,
        private readonly ?int $ttl = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreCreateForRenderEvent::class => ['onPreCreate', 10],
            PreRenderEvent::class => ['onPreRender', 10],
        ];
    }

    public function onPreCreate(PreCreateForRenderEvent $event): void
    {
        if (!$this->enabled || !$this->cache || 'pgi:Image' !== $event->getName()) {
            return;
        }

        $key = $this->generateKey($event->getInputProps());
        /** @var string|null $cachedHtml */
        $cachedHtml = $this->cache->get($key, function (ItemInterface $item) use ($event) {
            $ttl = $event->getInputProps()['ttl'] ?? $this->ttl;
            if ($ttl) {
                $item->expiresAfter($ttl);
            }

            return null;
        });

        if (null !== $cachedHtml) {
            $event->setRenderedString($cachedHtml);
        }
    }

    public function onPreRender(PreRenderEvent $event): void
    {
        if (!$this->enabled || 'pgi:Image' !== $event->getMetadata()->getName()) {
            return;
        }

		// If we get here, it means there was nothing in the cache (otherwise PreCreateForRenderEvent would have stopped the rendering)
        $variables = $event->getVariables();
        $key = $this->generateKey($variables);

		// Wrap the original template in a wrapper that stores the result in the cache
        $variables['pgi_original_template'] = $event->getTemplate();
        $variables['pgi_cache_key'] = $key;
        $variables['pgi_cache_ttl'] = $variables['ttl'] ?? $this->ttl;
        $variables['pgi_cache_tag'] = isset($variables['src']) ? 'pgi_tag_'.md5($variables['src']) : null;

        $event->setVariables($variables);
        $event->setTemplate('@ProgressiveImage/cache_wrapper.html.twig');
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function generateKey(array $vars): string
    {
		// Remove internal pgi variables if present, so they don't affect the key
        unset($vars['pgi_original_template'], $vars['pgi_cache_key']);

		// The key must contain everything that changes the HTML output
        return 'pgi_comp_'.md5(serialize($vars));
    }
}
