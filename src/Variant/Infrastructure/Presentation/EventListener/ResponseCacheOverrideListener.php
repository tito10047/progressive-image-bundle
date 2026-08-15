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

namespace Tito10047\ProgressiveImageBundle\Variant\Infrastructure\Presentation\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Tito10047\ProgressiveImageBundle\Variant\Application\Service\PendingGenerationTracker;

/**
 * kernel.response, priority -1024 — DI wiring registers this after Symfony's own
 * CacheAttributeListener so it runs last and actually wins. If this request dispatched a
 * variant generation, the response references an image that isn't ready yet: it must never
 * be cached (by the browser, a reverse proxy, or a CDN), regardless of what #[Cache] or
 * manual headers said.
 */
final readonly class ResponseCacheOverrideListener
{
    public function __construct(private PendingGenerationTracker $tracker)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$this->tracker->hasPending()) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0');
        $response->headers->remove('ETag');
        $response->headers->remove('Last-Modified');
        $response->headers->remove('Expires');
        $response->headers->set('Surrogate-Control', 'no-store');
    }
}
