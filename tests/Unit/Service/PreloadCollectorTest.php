<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\WebLink\GenericLinkProvider;
use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;

class PreloadCollectorTest extends TestCase
{
    public function testAddingTheSameUrlTwiceKeepsOnlyOneLinkInTheWebLinkProvider(): void
    {
        $request = new Request();
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $collector = new PreloadCollector($requestStack);

        $collector->add('/image.jpg', 'image', 'low');
        $collector->add('/image.jpg', 'image', 'high');

        $this->assertCount(1, $collector->getUrls());

        /** @var GenericLinkProvider $linkProvider */
        $linkProvider = $request->attributes->get('_links');
        $links = $linkProvider->getLinks();

        $matching = array_filter($links, static fn ($link) => '/image.jpg' === $link->getHref());
        $this->assertCount(1, $matching, 'the WebLink provider must not accumulate a duplicate Link for the same URL');

        $link = array_values($matching)[0];
        $this->assertSame('high', $link->getAttributes()['fetchpriority'], 'the second call must replace, not just append to, the WebLink entry');
    }
}
