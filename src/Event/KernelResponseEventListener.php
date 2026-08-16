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

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;

final class KernelResponseEventListener
{
    public function __construct(
        private readonly PreloadCollector $preloadCollector,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $preloads = $this->preloadCollector->getUrls();
        if (empty($preloads)) {
            return;
        }

        $response = $event->getResponse();

        // The HTTP Link header is symfony/web-link's job: PreloadCollector::add() already
        // registers a Link on the request's GenericLinkProvider, which FrameworkBundle's
        // AddLinkHeaderListener turns into the response header automatically. Building it
        // again here duplicated the header with slightly different formatting.
        $contentType = $response->headers->get('Content-Type');
        if (null !== $contentType && !str_starts_with($contentType, 'text/html')) {
            return;
        }

        $content = $response->getContent();
        if (false === $content) {
            // e.g. StreamedResponse/BinaryFileResponse, which don't support setContent().
            return;
        }

        $html = '';
        foreach ($preloads as $url => $attr) {
            $html .= sprintf('<link rel="preload" href="%s" as="%s" fetchpriority="%s"',
                htmlspecialchars($url, ENT_QUOTES),
                htmlspecialchars($attr['as'], ENT_QUOTES),
                htmlspecialchars($attr['priority'], ENT_QUOTES));
            if (!empty($attr['imagesrcset'])) {
                $html .= sprintf(' imagesrcset="%s"', htmlspecialchars($attr['imagesrcset'], ENT_QUOTES));
            }
            if (!empty($attr['imagesizes'])) {
                $html .= sprintf(' imagesizes="%s"', htmlspecialchars($attr['imagesizes'], ENT_QUOTES));
            }
            $html .= '>';
        }

        $newContent = str_replace('</head>', $html.'</head>', $content);
        $response->setContent($newContent);
    }
}
