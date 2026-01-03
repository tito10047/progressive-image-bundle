<?php

/*
 * This file is part of the Progressive Image Bundle.
 *
 * (c) Jozef Môstka <https://github.com/tito10047/progressive-image-bundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tito10047\ProgressiveImageBundle\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tito10047\ProgressiveImageBundle\Event\KernelResponseEventListener;
use Tito10047\ProgressiveImageBundle\Service\PreloadCollector;

class KernelResponseEventListenerTest extends TestCase
{
    private PreloadCollector $preloadCollector;
    private KernelResponseEventListener $listener;

    protected function setUp(): void
    {
        $this->preloadCollector = $this->createMock(PreloadCollector::class);
        $this->listener = new KernelResponseEventListener($this->preloadCollector);
    }

    public function testDoNothingWhenNoPreloads(): void
    {
        $this->preloadCollector->method('getUrls')->willReturn([]);

        $response = new Response('<html><head></head><body></body></html>');
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $this->listener->__invoke($event);

        $this->assertFalse($response->headers->has('Link'));
        $this->assertSame('<html><head></head><body></body></html>', $response->getContent());
    }

    public function testSetLinkHeaderAndInjectHtml(): void
    {
        $this->preloadCollector->method('getUrls')->willReturn([
            '/image1.jpg' => ['as' => 'image', 'priority' => 'high'],
            '/image2.jpg' => ['as' => 'image', 'priority' => 'low'],
        ]);

        $response = new Response('<html><head><title>Test</title></head><body></body></html>');
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $this->listener->__invoke($event);

        // Verify Link header
        $this->assertTrue($response->headers->has('Link'));
        $linkHeader = $response->headers->get('Link');
        $this->assertStringContainsString('</image1.jpg>; rel=preload; as=image; fetchpriority=high', $linkHeader);
        $this->assertStringContainsString('</image2.jpg>; rel=preload; as=image; fetchpriority=low', $linkHeader);

        // Verify HTML injection
        $content = $response->getContent();
        $this->assertStringContainsString('<link rel="preload" href="/image1.jpg" as="image" fetchpriority="high">', $content);
        $this->assertStringContainsString('<link rel="preload" href="/image2.jpg" as="image" fetchpriority="low">', $content);
        $this->assertStringContainsString('</head>', $content);

        // Ensure it's injected before </head>
        $this->assertStringContainsString('<link rel="preload" href="/image1.jpg" as="image" fetchpriority="high"><link rel="preload" href="/image2.jpg" as="image" fetchpriority="low"></head>', $content);
    }

    public function testInjectHtmlEvenWithoutHeadTag(): void
    {
        // str_replace will just not replace anything if </head> is missing.
        // Let's verify this behavior.
        $this->preloadCollector->method('getUrls')->willReturn([
            '/image1.jpg' => ['as' => 'image', 'priority' => 'high'],
        ]);

        $initialContent = '<html><body>No head here</body></html>';
        $response = new Response($initialContent);
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $this->listener->__invoke($event);

        // Link header should still be set
        $this->assertTrue($response->headers->has('Link'));

        // Content remains unchanged because </head> is missing
        $this->assertSame($initialContent, $response->getContent());
    }
}
