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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\WebLink\GenericLinkProvider;
use Symfony\Component\WebLink\Link;

class PreloadCollector
{
    private array $urls = [];

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function add(string $url, string $as = 'image', string $priority = 'high', ?string $srcset = null, ?string $sizes = null): void
    {
        $this->urls[$url] = [
            'as' => $as,
            'priority' => $priority,
            'imagesrcset' => $srcset,
            'imagesizes' => $sizes,
        ];

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $link = (new Link('preload', $url))
            ->withAttribute('as', $as)
            ->withAttribute('fetchpriority', $priority);

        if ($srcset) {
            $link = $link->withAttribute('imagesrcset', $srcset);
        }

        if ($sizes) {
            $link = $link->withAttribute('imagesizes', $sizes);
        }

        $linkProvider = $request->attributes->get('_links', new GenericLinkProvider());
        $request->attributes->set('_links', $linkProvider->withLink($link));
    }

    public function getUrls(): array
    {
        return $this->urls;
    }
}
