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
use Symfony\Component\HttpFoundation\StreamedResponse;
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

    public function testInjectsPreloadLinkTagsIntoHtml(): void
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

        // The HTTP Link header is symfony/web-link's job (via PreloadCollector's
        // GenericLinkProvider registration) — this listener must not duplicate it.
        $this->assertFalse($response->headers->has('Link'));

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

        // Content remains unchanged because </head> is missing
        $this->assertSame($initialContent, $response->getContent());
    }

    public function testSkipsSubRequests(): void
    {
        $this->preloadCollector->method('getUrls')->willReturn([
            '/image1.jpg' => ['as' => 'image', 'priority' => 'high'],
        ]);

        $initialContent = '<html><head></head><body></body></html>';
        $response = new Response($initialContent);
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::SUB_REQUEST,
            $response
        );

        $this->listener->__invoke($event);

        $this->assertSame($initialContent, $response->getContent());
    }

    public function testSkipsResponsesWithAnExplicitNonHtmlContentType(): void
    {
        $this->preloadCollector->method('getUrls')->willReturn([
            '/image1.jpg' => ['as' => 'image', 'priority' => 'high'],
        ]);

        // Contains a literal "</head>" string on purpose: a JSON/XML API response must
        // never have markup spliced into it just because that substring happens to occur.
        $initialContent = '{"note":"a stray </head> substring"}';
        $response = new Response($initialContent);
        $response->headers->set('Content-Type', 'application/json');
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $this->listener->__invoke($event);

        $this->assertSame($initialContent, $response->getContent());
    }

    public function testSkipsResponsesWithoutStringContent(): void
    {
        $this->preloadCollector->method('getUrls')->willReturn([
            '/image1.jpg' => ['as' => 'image', 'priority' => 'high'],
        ]);

        $response = new StreamedResponse(function (): void {
            echo 'streamed';
        });
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        // Must not throw despite getContent() being false for a StreamedResponse.
        $this->listener->__invoke($event);
        $this->addToAssertionCount(1);
    }

    public function testEscapesHtmlSpecialCharactersInPreloadAttributes(): void
    {
        $this->preloadCollector->method('getUrls')->willReturn([
            '/img.jpg?a=1&b="><script>alert(1)</script>' => [
                'as' => 'image',
                'priority' => 'high',
                'imagesrcset' => '/img.jpg?x="onerror="alert(1)',
                'imagesizes' => null,
            ],
        ]);

        $response = new Response('<html><head></head><body></body></html>');
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $this->listener->__invoke($event);

        $content = $response->getContent();
        $this->assertStringNotContainsString('<script>', $content);
        $this->assertStringNotContainsString('"><script>', $content);
        $this->assertStringNotContainsString('"onerror="', $content);
    }
}
